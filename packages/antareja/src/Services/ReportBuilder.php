<?php

namespace Antareja\Services;

use Antareja\Models\Incident;
use Antareja\Models\MaintenanceWindow;
use Antareja\Models\Problem;
use Antareja\Models\QualityTarget;
use Antareja\Models\Service;
use Antareja\Models\Site;
use Antareja\Models\Subnet;
use App\Models\Device;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Data laporan bulanan (dipakai Dashboard Manajemen, halaman laporan, dan export CSV / XLSX / cetak PDF).
 * Semua angka dihitung dari data tersimpan; tidak ada pengukuran baru.
 */
class ReportBuilder
{
    public readonly CarbonImmutable $from;

    public readonly CarbonImmutable $to;

    private ?SlaCalculator $calc = null;

    private ?array $deviceRows = null;

    public function __construct(CarbonImmutable $month, public readonly ?int $siteId = null)
    {
        $this->from = $month->startOfMonth();
        $this->to = $this->from->addMonth();
    }

    public function calc(): SlaCalculator
    {
        return $this->calc ??= SlaCalculator::forMonth($this->from);
    }

    public function site(): ?Site
    {
        return $this->siteId ? Site::find($this->siteId) : null;
    }

    public function title(): string
    {
        return 'Laporan ICT ' . $this->from->locale('id')->translatedFormat('F Y') . ($this->site() ? ' — ' . $this->site()->code . ' ' . $this->site()->name : '');
    }

    /** device_id → site_id */
    private function deviceSites(): \Illuminate\Support\Collection
    {
        return DB::table('ant_device_details')->pluck('site_id', 'device_id');
    }

    /** Perangkat aktif yang masuk laporan (tidak disabled/ignore, bukan Rejected/Retired). */
    public function devices(): array
    {
        if ($this->deviceRows !== null) {
            return $this->deviceRows;
        }
        $sites = $this->deviceSites();
        $siteCodes = Site::pluck('code', 'id');
        $skip = DB::table('ant_device_details')->whereIn('verification_status', ['rejected', 'retired'])->pluck('device_id')->flip();
        $rows = [];
        foreach (Device::where('disabled', 0)->where('ignore', 0)->orderBy('hostname')->get() as $d) {
            if (isset($skip[$d->device_id])) {
                continue;
            }
            $sid = $sites[$d->device_id] ?? null;
            if ($this->siteId && $sid !== $this->siteId) {
                continue;
            }
            $since = $d->inserted ? \Carbon\Carbon::parse($d->inserted)->getTimestamp() : null;
            if ($since !== null && $since >= $this->to->getTimestamp()) {
                continue; // belum dipantau pada periode ini
            }
            $s = $this->calc()->device($d->device_id, $since);
            $rows[] = [
                'device_id' => $d->device_id, 'name' => $d->displayName(), 'ip' => $d->overwrite_ip ?: $d->ip,
                'site_id' => $sid, 'site' => $sid ? ($siteCodes[$sid] ?? null) : null, 'type' => $d->type,
                'status' => $d->status ? 'up' : 'down',
            ] + $s;
        }
        usort($rows, fn ($a, $b) => ($a['availability'] ?? 101) <=> ($b['availability'] ?? 101) ?: $b['downtime'] <=> $a['downtime']);

        return $this->deviceRows = $rows;
    }

    public function services(): array
    {
        $stats = SlaCalculator::monthlyForServices($this->from);
        $rows = [];
        foreach (Service::with(['site', 'parent'])->withCount('devices')->where('active', true)->orderBy('sort')->orderBy('name')->get() as $s) {
            if ($this->siteId && $s->site_id !== $this->siteId) {
                continue;
            }
            $st = $stats[$s->id] ?? null;
            if (! $st) {
                continue;
            }
            $rows[] = ['service_id' => $s->id, 'name' => $s->name, 'parent' => $s->parent?->name, 'site' => $s->site?->code,
                'calc' => $s->calcLabel(), 'devices' => $s->devices_count] + $st;
        }

        return $rows;
    }

    public function sites(): array
    {
        $bySite = collect($this->devices())->groupBy('site_id');
        $incidents = $this->incidentQuery()->selectRaw('site_id, COUNT(*) AS n')->groupBy('site_id')->pluck('n', 'site_id');
        $rows = [];
        $sites = Site::orderBy('code')->get();
        foreach ($sites as $site) {
            if ($this->siteId && $site->id !== $this->siteId) {
                continue;
            }
            $devs = $bySite[$site->id] ?? collect();
            $rows[] = $this->siteRow($site->code, $site->name, $devs, (int) ($incidents[$site->id] ?? 0));
        }
        if (! $this->siteId && isset($bySite[''])) {
            $rows[] = $this->siteRow('—', 'Belum ada site', $bySite[''], (int) ($incidents[''] ?? 0));
        }

        return $rows;
    }

    private function siteRow(string $code, string $name, $devs, int $incidents): array
    {
        $withAvail = $devs->whereNotNull('availability');

        return [
            'code' => $code, 'name' => $name, 'devices' => $devs->count(),
            'availability' => $withAvail->isNotEmpty() ? round($withAvail->avg('availability'), 4) : null,
            'downtime' => (int) $devs->sum('downtime'), 'outages' => (int) $devs->sum('outages'),
            'devices_with_outage' => $devs->where('outages', '>', 0)->count(), 'incidents' => $incidents,
        ];
    }

    private function incidentQuery()
    {
        $q = Incident::query()->where('started_at', '>=', $this->from)->where('started_at', '<', $this->to);
        if ($this->siteId) {
            $q->where('site_id', $this->siteId);
        }

        return $q;
    }

    public function incidents(): array
    {
        return $this->incidentQuery()->with(['device', 'site', 'assignee'])->orderBy('started_at')->get()->map(fn (Incident $i) => [
            'number' => $i->number, 'title' => $i->title, 'severity' => $i->severity, 'status' => $i->status,
            'site' => $i->site?->code, 'device' => $i->device?->displayName(), 'pic' => $i->picLabel(),
            'started_at' => $i->started_at, 'acknowledged_at' => $i->acknowledged_at, 'resolved_at' => $i->resolved_at,
            'tta' => $i->secondsToAck(), 'ttr' => $i->secondsToResolve(),
            'root_cause' => $i->root_cause, 'corrective_action' => $i->corrective_action, 'id' => $i->id,
        ])->all();
    }

    public function maintenance(): array
    {
        $q = MaintenanceWindow::with('site')->withCount('devices')->where('status', '!=', 'cancelled')
            ->where('starts_at', '<', $this->to)->where('ends_at', '>', $this->from)->orderBy('starts_at');
        if ($this->siteId) {
            $q->where('site_id', $this->siteId);
        }

        return $q->get()->map(fn (MaintenanceWindow $w) => [
            'id' => $w->id, 'title' => $w->title, 'type' => $w->type, 'site' => $w->site?->code, 'devices' => $w->devices_count,
            'starts_at' => $w->starts_at, 'ends_at' => $w->ends_at, 'status' => $w->effectiveStatus(),
            'result' => $w->result, 'exclude_from_sla' => $w->exclude_from_sla,
            'hours' => round($w->starts_at->diffInSeconds($w->ends_at) / 3600, 1),
        ])->all();
    }

    /**
     * Ringkasan Quality Probe per target: rollup harian + sampel hari yang belum di-rollup.
     */
    public function quality(): array
    {
        $q = QualityTarget::with('site')->orderBy('name');
        if ($this->siteId) {
            $q->where('site_id', $this->siteId);
        }
        $rows = [];
        foreach ($q->get() as $t) {
            $daily = DB::table('ant_quality_daily')->where('target_id', $t->id)
                ->where('day', '>=', $this->from->toDateString())->where('day', '<', $this->to->toDateString())->get();
            $after = $daily->max('day') ? CarbonImmutable::parse($daily->max('day'))->addDay() : $this->from;
            $raw = DB::table('ant_quality_samples')->where('target_id', $t->id)
                ->where('measured_at', '>=', $after)->where('measured_at', '<', $this->to)
                ->selectRaw('COUNT(*) AS n, SUM(loss_pct) AS loss_sum, SUM(CASE WHEN loss_pct >= 100 THEN 1 ELSE 0 END) AS down_n,
                    SUM(median_ms) AS med_sum, COUNT(median_ms) AS med_n, MAX(max_ms) AS max_ms, SUM(jitter_ms) AS jit_sum, COUNT(jitter_ms) AS jit_n')->first();

            $n = (int) $daily->sum('samples') + (int) $raw->n;
            if ($n === 0) {
                $rows[] = ['id' => $t->id, 'name' => $t->name, 'host' => $t->host, 'site' => $t->site?->code, 'group' => $t->group_name,
                    'samples' => 0, 'avg_loss' => null, 'availability' => null, 'avg_latency' => null, 'max_latency' => null, 'avg_jitter' => null, 'status' => $t->last_status];
                continue;
            }
            $lossSum = $daily->sum(fn ($d) => $d->avg_loss_pct * $d->samples) + (float) $raw->loss_sum;
            $downN = (int) $daily->sum('samples_down') + (int) $raw->down_n;
            $medN = (int) $daily->filter(fn ($d) => $d->avg_median_ms !== null)->sum(fn ($d) => $d->samples - $d->samples_down) + (int) $raw->med_n;
            $medSum = $daily->sum(fn ($d) => $d->avg_median_ms === null ? 0 : $d->avg_median_ms * ($d->samples - $d->samples_down)) + (float) $raw->med_sum;
            $jitN = (int) $daily->filter(fn ($d) => $d->avg_jitter_ms !== null)->sum(fn ($d) => $d->samples - $d->samples_down) + (int) $raw->jit_n;
            $jitSum = $daily->sum(fn ($d) => $d->avg_jitter_ms === null ? 0 : $d->avg_jitter_ms * ($d->samples - $d->samples_down)) + (float) $raw->jit_sum;

            $rows[] = [
                'id' => $t->id, 'name' => $t->name, 'host' => $t->host, 'site' => $t->site?->code, 'group' => $t->group_name,
                'samples' => $n,
                'avg_loss' => round($lossSum / $n, 2),
                'availability' => round(($n - $downN) / $n * 100, 3),
                'avg_latency' => $medN > 0 ? round($medSum / $medN, 2) : null,
                'max_latency' => max((float) $daily->max('max_ms'), (float) $raw->max_ms) ?: null,
                'avg_jitter' => $jitN > 0 ? round($jitSum / $jitN, 2) : null,
                'status' => $t->last_status,
            ];
        }

        return $rows;
    }

    public function ipam(): array
    {
        $ipam = app(IpamService::class);
        $q = Subnet::with('site')->where('family', 4)->orderBy('network');
        if ($this->siteId) {
            $q->where('site_id', $this->siteId);
        }

        return $q->get()->map(fn (Subnet $s) => ['cidr' => $s->cidr, 'name' => $s->name, 'site' => $s->site?->code] + $ipam->stats($s))
            ->sortByDesc('utilization')->values()->all();
    }

    public function problems(): array
    {
        $q = Problem::query()->where('started_at', '>=', $this->from)->where('started_at', '<', $this->to);
        if ($this->siteId) {
            $q->where('site_id', $this->siteId);
        }

        return $q->selectRaw('class, severity, COUNT(*) AS n')->groupBy('class', 'severity')->get()
            ->map(fn ($r) => ['class' => $r->class, 'severity' => $r->severity, 'count' => (int) $r->n])->all();
    }

    public function summary(): array
    {
        $devices = collect($this->devices());
        $avail = $devices->whereNotNull('availability');
        $services = collect($this->services())->whereNotNull('availability');
        $incidents = collect($this->incidents());
        $acked = $incidents->whereNotNull('tta');
        $resolved = $incidents->whereNotNull('ttr');
        $maint = collect($this->maintenance());

        return [
            'period_from' => $this->from, 'period_to' => min($this->to, CarbonImmutable::now()),
            'devices' => $devices->count(),
            'devices_down_now' => $devices->where('status', 'down')->count(),
            'devices_with_outage' => $devices->where('outages', '>', 0)->count(),
            'device_availability' => $avail->isNotEmpty() ? round($avail->avg('availability'), 4) : null,
            'downtime' => (int) $devices->sum('downtime'),
            'services' => $services->count(),
            'services_met' => $services->where('met', true)->count(),
            'incidents' => $incidents->count(),
            'incidents_by_severity' => $incidents->countBy('severity')->all(),
            'incidents_open' => $incidents->whereIn('status', Incident::ACTIVE)->count(),
            'mtta' => $acked->isNotEmpty() ? (int) $acked->avg('tta') : null,
            'mttr' => $resolved->isNotEmpty() ? (int) $resolved->avg('ttr') : null,
            'problems' => array_sum(array_column($this->problems(), 'count')),
            'maintenance' => $maint->count(),
            'maintenance_hours' => round($maint->sum('hours'), 1),
        ];
    }
}
