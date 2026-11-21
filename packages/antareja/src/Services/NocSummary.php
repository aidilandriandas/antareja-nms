<?php

namespace Antareja\Services;

use Antareja\Models\CctvStream;
use Antareja\Models\Incident;
use Antareja\Models\MaintenanceWindow;
use Antareja\Models\Problem;
use Antareja\Support\Catalog;
use Antareja\Support\Severity;
use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Ringkasan untuk layar NOC (dipanggil berulang tiap ±15 detik; query dibuat ringan & berbasis agregat). */
class NocSummary
{
    public function __construct(private readonly DeviceGroupSync $groups)
    {
    }

    public function build(User $user, ?int $siteId = null): array
    {
        $access = Device::hasAccess($user)->select('devices.device_id');
        $base = DB::table('devices')->whereIn('devices.device_id', $access)
            ->when($siteId, fn ($q) => $q->whereIn('devices.device_id', DB::table('ant_device_details')->where('site_id', $siteId)->select('device_id')));
        $c = (clone $base)->selectRaw('COUNT(*) AS total,
            SUM(status = 1 AND disabled = 0 AND `ignore` = 0) AS up,
            SUM(status = 0 AND disabled = 0 AND `ignore` = 0) AS down, MAX(last_polled) AS last_polled')->first();
        $up = (int) $c->up;
        $down = (int) $c->down;

        $out = [
            'time' => now()->toIso8601String(),
            'devices' => [
                'total' => (int) $c->total, 'up' => $up, 'down' => $down, 'off' => max(0, (int) $c->total - $up - $down),
                'availability' => ($up + $down) > 0 ? round($up / ($up + $down) * 100, 2) : null,
                'last_polled' => $c->last_polled,
            ],
            'down_list' => (clone $base)->where('devices.status', 0)->where('devices.disabled', 0)->where('devices.ignore', 0)
                ->leftJoin('ant_device_details as dd', 'dd.device_id', '=', 'devices.device_id')
                ->leftJoin('ant_sites as s', 's.id', '=', 'dd.site_id')
                ->orderByDesc('devices.last_polled')->limit(12)
                ->get(['devices.device_id', 'devices.display', 'devices.hostname', 'devices.status_reason', 's.code as site', 'dd.category'])
                ->map(fn ($d) => ['id' => $d->device_id, 'name' => $d->display ?: $d->hostname, 'ip' => $d->hostname, 'site' => $d->site,
                    'group' => Catalog::GROUP_NAMES[$d->category] ?? null, 'reason' => $d->status_reason ?: null])->all(),
            'problems' => null, 'severity' => null, 'incidents' => null, 'maintenance' => null, 'cctv' => null,
        ];

        if ($user->can('ant-problem.view')) {
            $open = Problem::open()->whereNull('suppressed_reason')->when($siteId, fn ($q) => $q->where('site_id', $siteId));
            $sev = (clone $open)->selectRaw('severity, COUNT(*) n')->groupBy('severity')->pluck('n', 'severity');
            $out['severity'] = collect(['disaster', 'high', 'average', 'warning', 'information'])
                ->map(fn ($s) => ['key' => $s, 'n' => (int) ($sev[$s] ?? 0)] + Severity::problem($s))->values()->all();
            // Urutan: belum ACK dulu, lalu severity, lalu terbaru. "Baru" = mulai ≤ 60 menit; "lama" = > 24 jam.
            $out['problems'] = (clone $open)->with(['device:device_id,display,hostname', 'site:id,code'])
                ->orderByRaw('acknowledged_at IS NOT NULL')
                ->orderByRaw("FIELD(severity, 'disaster', 'high', 'average', 'warning', 'information')")->orderByDesc('started_at')->limit(20)->get()
                ->map(fn (Problem $p) => [
                    'id' => $p->id, 'title' => $p->title, 'severity' => $p->severityMeta(), 'device' => $p->device?->displayName(),
                    'site' => $p->site?->code, 'since' => $p->started_at->toIso8601String(), 'ack' => $p->acknowledged_at !== null,
                    'new' => $p->started_at->gte(now()->subHour()), 'old' => $p->started_at->lt(now()->subDay()),
                    'url' => route('antareja.problems.show', $p),
                ])->all();
            $out['problem_count'] = (int) (clone $open)->count();
        }
        if ($user->can('ant-incident.view')) {
            $out['incidents'] = Incident::active()->count();
        }
        if ($user->can('ant-maintenance.view')) {
            $out['maintenance'] = MaintenanceWindow::whereIn('status', ['planned', 'in_progress'])->where('starts_at', '<=', now())->where('ends_at', '>=', now())->count();
        }
        if ($user->can('ant-cctv.view')) {
            $s = CctvStream::where('enabled', true)->where('health_check', true);
            $out['cctv'] = ['total' => (clone $s)->count(), 'ok' => (clone $s)->where('health', 'ok')->count()];
        }

        // Status per site & per grup otomatis
        $out['sites'] = DB::table('ant_sites')->where('is_active', true)
            ->leftJoin('ant_device_details as dd', 'dd.site_id', '=', 'ant_sites.id')
            ->leftJoin('devices', fn ($j) => $j->on('devices.device_id', '=', 'dd.device_id')->whereIn('devices.device_id', $access))
            ->groupBy('ant_sites.id', 'ant_sites.code', 'ant_sites.name')
            ->selectRaw('ant_sites.id, ant_sites.code, ant_sites.name, COUNT(devices.device_id) total,
                COALESCE(SUM(devices.status = 1 AND devices.disabled = 0 AND devices.`ignore` = 0), 0) up,
                COALESCE(SUM(devices.status = 0 AND devices.disabled = 0 AND devices.`ignore` = 0), 0) down')
            ->orderByDesc('down')->orderBy('ant_sites.code')->get()
            ->map(fn ($s) => ['id' => $s->id, 'code' => $s->code, 'name' => $s->name, 'total' => (int) $s->total, 'up' => (int) $s->up, 'down' => (int) $s->down])->all();

        $auto = collect($this->groups->groups());
        $counts = $auto->isEmpty() ? collect() : DB::table('device_group_device')
            ->join('devices', 'devices.device_id', '=', 'device_group_device.device_id')
            ->whereIn('devices.device_id', (clone $base)->select('devices.device_id'))
            ->whereIn('device_group_id', $auto->map->id)
            ->groupBy('device_group_id')
            ->selectRaw('device_group_id gid, COUNT(*) total, SUM(devices.status = 1 AND devices.disabled = 0 AND devices.`ignore` = 0) up,
                SUM(devices.status = 0 AND devices.disabled = 0 AND devices.`ignore` = 0) down')
            ->get()->keyBy('gid');
        $out['groups'] = $auto->map(fn ($g, $cat) => ['name' => $g->name, 'icon' => Catalog::CATEGORY_ICONS[$cat] ?? 'layer-group',
            'total' => (int) ($counts[$g->id]->total ?? 0), 'up' => (int) ($counts[$g->id]->up ?? 0), 'down' => (int) ($counts[$g->id]->down ?? 0)])
            ->filter(fn ($g) => $g['total'] > 0)->sortByDesc('down')->values()->all();

        $this->extras($out, $user, $base, $siteId);

        return $out;
    }

    /** Ping cepat, dinding status, kejadian 1 jam, latensi tertinggi, uplink tersibuk, dan tingkat alarm. */
    private function extras(array &$out, User $user, $base, ?int $siteId): void
    {
        $ids = (clone $base)->pluck('devices.device_id');
        $pings = DB::table('ant_device_pings')->whereIn('device_id', $ids)->get()->keyBy('device_id');
        $fresh = fn ($p) => $p && $p->last_checked_at && strtotime($p->last_checked_at) >= now()->subMinutes(5)->getTimestamp();

        // Dinding status: semua perangkat, dikelompokkan per site
        $rows = (clone $base)->leftJoin('ant_device_details as dd', 'dd.device_id', '=', 'devices.device_id')
            ->leftJoin('ant_sites as s', 's.id', '=', 'dd.site_id')
            ->orderBy('s.code')->orderBy('devices.display')
            ->get(['devices.device_id', 'devices.display', 'devices.hostname', 'devices.status', 'devices.disabled', 'devices.ignore',
                's.code as site', 'dd.category']);
        $wall = [];
        $pingDown = 0;
        foreach ($rows as $r) {
            $p = $pings[$r->device_id] ?? null;
            $fp = $fresh($p) ? $p : null;
            $state = ($r->disabled || $r->ignore) ? 'off' : (! $r->status ? 'down' : ($fp && $fp->state === 'down' ? 'noping'
                : ($fp && ($fp->loss > 0 || ($fp->rtt_ms ?? 0) >= FastPing::HIGH_RTT_MS) ? 'slow' : 'ok')));
            $pingDown += $state === 'noping' ? 1 : 0;
            $wall[] = ['id' => $r->device_id, 'name' => $r->display ?: $r->hostname, 'ip' => $r->hostname, 'site' => $r->site ?: '—',
                'group' => Catalog::GROUP_NAMES[$r->category] ?? 'Lainnya', 'state' => $state,
                'rtt' => $fp?->rtt_ms, 'loss' => $fp?->loss];
        }
        $out['wall'] = $wall;
        $out['ping'] = ['down' => $pingDown, 'fresh' => $pings->contains(fn ($p) => $fresh($p))];

        // Perangkat yang belum dianggap down oleh polling tetapi sudah tidak menjawab ping cepat
        foreach ($wall as $w) {
            if ($w['state'] === 'noping') {
                $p = $pings[$w['id']];
                array_unshift($out['down_list'], ['id' => $w['id'], 'name' => $w['name'], 'ip' => $w['ip'], 'site' => $w['site'],
                    'group' => $w['group'], 'reason' => 'tidak menjawab ping ' . $p->fail_streak . ' menit', 'ping' => true]);
            }
        }

        // Kejadian 1 jam terakhir
        $names = collect($wall)->keyBy('id');
        $out['events'] = DB::table('ant_noc_events')->whereIn('device_id', $ids)->where('at', '>=', now()->subHour())
            ->orderByDesc('at')->orderByDesc('id')->limit(25)->get()
            ->map(fn ($e) => ['kind' => $e->kind, 'message' => $e->message, 'at' => Carbon::parse($e->at)->toIso8601String(),
                'device' => $names[$e->device_id]['name'] ?? null, 'site' => $names[$e->device_id]['site'] ?? null, 'id' => $e->device_id])->all();

        // Latensi & loss tertinggi + sparkline 60 menit
        $worst = collect($pings)->filter(fn ($p) => $fresh($p) && ($p->rtt_ms !== null || $p->loss > 0))
            ->sortByDesc(fn ($p) => [$p->loss, $p->rtt_ms ?? 0])->take(8);
        $spark = $worst->isEmpty() ? collect() : DB::table('ant_device_ping_samples')->whereIn('device_id', $worst->keys())
            ->where('at', '>=', now()->subHour())->orderBy('at')->get(['device_id', 'rtt_ms', 'loss'])->groupBy('device_id');
        $out['latency'] = $worst->map(fn ($p) => [
            'id' => $p->device_id, 'name' => $names[$p->device_id]['name'] ?? '#' . $p->device_id, 'site' => $names[$p->device_id]['site'] ?? null,
            'rtt' => $p->rtt_ms, 'loss' => $p->loss,
            'spark' => ($spark[$p->device_id] ?? collect())->map(fn ($x) => $x->loss >= 100 ? null : $x->rtt_ms)->values()->all(),
        ])->values()->all();

        // Uplink tersibuk: port up ≥ 10 Mbps di router/switch/radio, diurutkan pemakaian
        $infra = ['router', 'switch', 'core-switch', 'distribution-switch', 'access-switch', 'firewall', 'ptp', 'ptmp', 'wireless-controller'];
        $out['uplinks'] = DB::table('ports')->join('ant_device_details as dd', 'dd.device_id', '=', 'ports.device_id')
            ->whereIn('ports.device_id', $ids)->whereIn('dd.category', $infra)
            ->where('ports.ifOperStatus', 'up')->where('ports.ifSpeed', '>=', 10000000)->where('ports.deleted', 0)
            ->whereNotNull('ports.ifInOctets_rate')
            ->selectRaw('ports.port_id, ports.device_id, ports.ifName, ports.ifAlias, ports.ifSpeed,
                GREATEST(ports.ifInOctets_rate, ports.ifOutOctets_rate) * 8 / ports.ifSpeed * 100 AS util,
                ports.ifInOctets_rate * 8 AS in_bps, ports.ifOutOctets_rate * 8 AS out_bps')
            ->orderByDesc('util')->limit(6)->get()
            ->map(fn ($r) => ['device' => $names[$r->device_id]['name'] ?? '#' . $r->device_id, 'port' => $r->ifName,
                'alias' => $r->ifAlias && $r->ifAlias !== $r->ifName ? mb_substr($r->ifAlias, 0, 40) : null,
                'util' => round(min(100, (float) $r->util), 1), 'in' => (int) $r->in_bps, 'out' => (int) $r->out_bps, 'speed' => (int) $r->ifSpeed,
                'url' => url('device/' . $r->device_id . '/tab=port/port=' . $r->port_id)])->all();

        // Tingkat alarm: MERAH hanya untuk yang butuh tindakan sekarang
        $problems = collect($out['problems'] ?? []);
        $newHigh = $problems->filter(fn ($p) => ! $p['ack'] && $p['new'] && $p['severity']['rank'] >= 4)->count();
        $unacked = $problems->filter(fn ($p) => ! $p['ack'])->count();
        $down = $out['devices']['down'] + $pingDown;
        $busy = collect($out['uplinks'])->where('util', '>=', 90)->count();
        $slow = collect($wall)->where('state', 'slow')->count();
        if ($down > 0 || $newHigh > 0) {
            $level = 'crit';
            $text = trim(($down ? "{$down} perangkat down" : '') . ($down && $newHigh ? ' · ' : '') . ($newHigh ? "{$newHigh} problem baru" : ''));
        } elseif ($unacked > 0 || $busy > 0 || $slow > 0) {
            $level = 'warn';
            $text = collect([$unacked ? "{$unacked} problem belum ACK" : null, $slow ? "{$slow} perangkat lambat" : null, $busy ? "{$busy} uplink > 90%" : null])->filter()->implode(' · ');
        } else {
            $level = 'ok';
            $text = $problems->isNotEmpty() ? 'Normal · ' . $problems->count() . ' problem lama sudah di-ACK' : 'Semua sistem normal';
        }
        $out['alarm'] = ['level' => $level, 'text' => $text];
    }
}
