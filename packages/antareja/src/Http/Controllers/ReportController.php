<?php

namespace Antareja\Http\Controllers;

use Antareja\Models\Incident;
use Antareja\Models\MaintenanceWindow;
use Antareja\Models\Problem;
use Antareja\Models\Site;
use Antareja\Services\AuditLogger;
use Antareja\Services\ReportBuilder;
use Antareja\Services\SlaCalculator;
use Antareja\Support\Severity;
use Antareja\Support\XlsxWriter;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public const SECTIONS = [
        'summary' => 'Ringkasan',
        'services' => 'SLA layanan',
        'sites' => 'Availability per site',
        'devices' => 'Availability perangkat',
        'incidents' => 'Incident',
        'maintenance' => 'Maintenance',
        'quality' => 'Kualitas jaringan',
        'ipam' => 'Utilisasi IP',
        'problems' => 'Problem',
    ];

    public function index(): View
    {
        $months = collect(range(0, 11))->map(fn ($i) => CarbonImmutable::now()->startOfMonth()->subMonths($i));

        return view('antareja::reports.index', [
            'months' => $months,
            'sites' => Site::orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function monthly(Request $request): View
    {
        $r = $this->builder($request);
        $view = $request->boolean('print') ? 'antareja::reports.print' : 'antareja::reports.monthly';

        return view($view, [
            'r' => $r,
            'month' => $r->from,
            'summary' => $r->summary(),
            'services' => $r->services(),
            'sites' => $r->sites(),
            'devices' => $r->devices(),
            'incidents' => $r->incidents(),
            'maintenance' => $r->maintenance(),
            'quality' => $r->quality(),
            'ipam' => $r->ipam(),
            'problems' => $r->problems(),
            'allSites' => Site::orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function export(Request $request, AuditLogger $audit): Response
    {
        $r = $this->builder($request);
        $format = $request->query('format') === 'xlsx' ? 'xlsx' : 'csv';
        $tables = self::tables($r);
        $base = 'antareja-laporan-' . $r->from->format('Y-m') . ($r->siteId ? '-' . (Site::find($r->siteId)?->code ?? 'site') : '');
        $base = preg_replace('/[^A-Za-z0-9._-]/', '_', $base);
        $audit->log('exported', 'report', "Export laporan {$r->from->format('Y-m')} ({$format})");

        if ($format === 'xlsx') {
            $x = new XlsxWriter();
            foreach ($tables as $t) {
                $x->addSheet($t['title'], $t['header'], $t['rows']);
            }
            $tmp = tempnam(sys_get_temp_dir(), 'antx');
            $x->save($tmp);

            return response()->download($tmp, $base . '.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend();
        }

        $section = $request->query('section', 'summary');
        abort_unless(isset($tables[$section]), 404);
        $t = $tables[$section];

        return new StreamedResponse(function () use ($t): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
            fputcsv($out, $t['header'], ';');
            foreach ($t['rows'] as $row) {
                fputcsv($out, array_map([self::class, 'csvSafe'], $row), ';');
            }
            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $base . '-' . $section . '.csv"',
        ]);
    }

    /** Cegah formula injection saat CSV dibuka di spreadsheet. */
    public static function csvSafe(mixed $v): mixed
    {
        if (is_string($v) && $v !== '' && in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $v;
        }

        return $v;
    }

    /**
     * Semua bagian laporan sebagai tabel datar (untuk CSV / XLSX).
     *
     * @return array<string, array{title:string, header:array, rows:array}>
     */
    public static function tables(ReportBuilder $r): array
    {
        $dt = fn ($d) => $d ? $d->format('Y-m-d H:i') : null;
        $min = fn ($s) => $s === null ? null : round($s / 60, 1);
        $s = $r->summary();

        return [
            'summary' => ['title' => 'Ringkasan', 'header' => ['Indikator', 'Nilai'], 'rows' => [
                ['Periode', $r->from->format('Y-m-d') . ' s/d ' . $s['period_to']->format('Y-m-d H:i')],
                ['Site', $r->site() ? $r->site()->code . ' ' . $r->site()->name : 'Semua site'],
                ['Perangkat aktif', $s['devices']],
                ['Availability rata-rata perangkat (%)', $s['device_availability']],
                ['Perangkat yang pernah down', $s['devices_with_outage']],
                ['Total downtime perangkat (jam)', round($s['downtime'] / 3600, 2)],
                ['Layanan memenuhi SLA', $s['services_met'] . ' / ' . $s['services']],
                ['Incident', $s['incidents']],
                ['Incident masih aktif', $s['incidents_open']],
                ['MTTA (menit)', $min($s['mtta'])],
                ['MTTR (menit)', $min($s['mttr'])],
                ['Problem', $s['problems']],
                ['Maintenance', $s['maintenance']],
                ['Jam maintenance', $s['maintenance_hours']],
            ]],
            'services' => ['title' => 'SLA layanan', 'header' => ['Layanan', 'Induk', 'Site', 'Aturan', 'Perangkat', 'Target (%)', 'Availability (%)', 'Tercapai', 'Downtime (menit)', 'Gangguan', 'MTTR (menit)', 'MTBF (jam)', 'Dikecualikan maintenance (menit)'],
                'rows' => array_map(fn ($x) => [$x['name'], $x['parent'], $x['site'], $x['calc'], $x['devices'], $x['target'], $x['availability'],
                    $x['met'] === null ? null : ($x['met'] ? 'Ya' : 'Tidak'), $min($x['downtime']), $x['outages'], $min($x['mttr']),
                    $x['mtbf'] === null ? null : round($x['mtbf'] / 3600, 1), $min($x['excluded'])], $r->services())],
            'sites' => ['title' => 'Availability per site', 'header' => ['Kode', 'Site', 'Perangkat', 'Availability rata-rata (%)', 'Downtime (menit)', 'Gangguan', 'Perangkat pernah down', 'Incident'],
                'rows' => array_map(fn ($x) => [$x['code'], $x['name'], $x['devices'], $x['availability'], $min($x['downtime']), $x['outages'], $x['devices_with_outage'], $x['incidents']], $r->sites())],
            'devices' => ['title' => 'Availability perangkat', 'header' => ['Perangkat', 'IP', 'Site', 'Tipe', 'Status kini', 'Availability (%)', 'Downtime (menit)', 'Gangguan', 'MTTR (menit)', 'Dikecualikan maintenance (menit)'],
                'rows' => array_map(fn ($x) => [$x['name'], $x['ip'], $x['site'], $x['type'], $x['status'], $x['availability'], $min($x['downtime']), $x['outages'], $min($x['mttr']), $min($x['excluded'])], $r->devices())],
            'incidents' => ['title' => 'Incident', 'header' => ['Nomor', 'Judul', 'Severity', 'Status', 'Site', 'Perangkat', 'PIC', 'Mulai', 'Acknowledge', 'Resolved', 'Waktu ack (menit)', 'Waktu pulih (menit)', 'Root cause', 'Corrective action'],
                'rows' => array_map(fn ($x) => [$x['number'], $x['title'], Severity::incident($x['severity'])['label'], Incident::STATUSES[$x['status']]['label'] ?? $x['status'],
                    $x['site'], $x['device'], $x['pic'], $dt($x['started_at']), $dt($x['acknowledged_at']), $dt($x['resolved_at']), $min($x['tta']), $min($x['ttr']), $x['root_cause'], $x['corrective_action']], $r->incidents())],
            'maintenance' => ['title' => 'Maintenance', 'header' => ['Judul', 'Jenis', 'Site', 'Perangkat', 'Mulai', 'Selesai', 'Durasi (jam)', 'Status', 'Hasil', 'Dikecualikan dari SLA'],
                'rows' => array_map(fn ($x) => [$x['title'], MaintenanceWindow::TYPES[$x['type']] ?? $x['type'], $x['site'], $x['devices'], $dt($x['starts_at']), $dt($x['ends_at']), $x['hours'],
                    MaintenanceWindow::STATUSES[$x['status']]['label'] ?? $x['status'], MaintenanceWindow::RESULTS[$x['result']] ?? null, $x['exclude_from_sla'] ? 'Ya' : 'Tidak'], $r->maintenance())],
            'quality' => ['title' => 'Kualitas jaringan', 'header' => ['Target', 'Host', 'Site', 'Grup', 'Sampel', 'Ketersediaan ping (%)', 'Packet loss rata-rata (%)', 'Latency median rata-rata (ms)', 'Latency maks (ms)', 'Jitter rata-rata (ms)', 'Status kini'],
                'rows' => array_map(fn ($x) => [$x['name'], $x['host'], $x['site'], $x['group'], $x['samples'], $x['availability'], $x['avg_loss'], $x['avg_latency'], $x['max_latency'], $x['avg_jitter'], $x['status']], $r->quality())],
            'ipam' => ['title' => 'Utilisasi IP', 'header' => ['Subnet', 'Nama', 'Site', 'Usable', 'Teralokasi', 'Tersedia', 'Utilisasi (%)', 'Konflik'],
                'rows' => array_map(fn ($x) => [$x['cidr'], $x['name'], $x['site'], $x['usable'], $x['allocated'], $x['available'], $x['utilization'], $x['conflicts']], $r->ipam())],
            'problems' => ['title' => 'Problem', 'header' => ['Jenis', 'Severity', 'Jumlah'],
                'rows' => array_map(fn ($x) => [Problem::CLASSES[$x['class']] ?? $x['class'], Severity::problem($x['severity'])['label'], $x['count']], $r->problems())],
        ];
    }

    private function builder(Request $request): ReportBuilder
    {
        $m = $request->query('month');
        $month = (is_string($m) && preg_match('/^\d{4}-\d{2}$/', $m))
            ? CarbonImmutable::createFromFormat('Y-m-d', $m . '-01')->startOfDay()
            : CarbonImmutable::now()->startOfMonth();
        abort_if($month->isFuture(), 404);

        return new ReportBuilder($month, $request->integer('site') ?: null);
    }
}
