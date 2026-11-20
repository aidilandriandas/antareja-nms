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
            $out['problems'] = (clone $open)->with(['device:device_id,display,hostname', 'site:id,code'])
                ->orderByRaw("FIELD(severity, 'disaster', 'high', 'average', 'warning', 'information')")->orderByDesc('started_at')->limit(14)->get()
                ->map(fn (Problem $p) => [
                    'id' => $p->id, 'title' => $p->title, 'severity' => $p->severityMeta(), 'device' => $p->device?->displayName(),
                    'site' => $p->site?->code, 'since' => $p->started_at->toIso8601String(), 'ack' => $p->acknowledged_at !== null,
                    'url' => route('antareja.problems.show', $p),
                ])->all();
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

        return $out;
    }
}
