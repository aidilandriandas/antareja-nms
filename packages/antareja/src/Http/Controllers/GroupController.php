<?php

namespace Antareja\Http\Controllers;

use Antareja\Services\AuditLogger;
use Antareja\Services\DeviceGroupSync;
use Antareja\Support\Catalog;
use App\Models\Device;
use App\Models\DeviceGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Grup perangkat: otomatis per kategori (CCTV, Access Point, …) + grup buatan sendiri (Device Group LibreNMS). */
class GroupController extends Controller
{
    public function index(Request $request, DeviceGroupSync $sync): View
    {
        Gate::authorize('viewAny', Device::class);
        $auto = $sync->groups();
        $autoIds = collect($auto)->map->id->flip();
        $stats = $this->stats($request);

        $groups = DeviceGroup::hasAccess($request->user())->orderBy('name')->get()->map(function (DeviceGroup $g) use ($auto, $autoIds, $stats) {
            $category = $autoIds->has($g->id) ? array_search($g->id, collect($auto)->map->id->all(), true) : null;
            $s = $stats[$g->id] ?? (object) ['total' => 0, 'up' => 0, 'down' => 0];

            return (object) [
                'group' => $g,
                'auto' => $category !== null,
                'icon' => $category ? (Catalog::CATEGORY_ICONS[$category] ?? 'layer-group') : 'layer-group',
                'total' => (int) $s->total, 'up' => (int) $s->up, 'down' => (int) $s->down,
                'off' => max(0, (int) $s->total - (int) $s->up - (int) $s->down),
            ];
        });

        $uncategorized = DB::table('devices')->whereIn('devices.device_id', Device::hasAccess($request->user())->select('devices.device_id'))
            ->leftJoin('ant_device_details', 'ant_device_details.device_id', '=', 'devices.device_id')
            ->whereNull('ant_device_details.category')->count();

        return view('antareja::groups.index', [
            'autoGroups' => $groups->where('auto', true)->sortBy(fn ($g) => array_search(substr($g->group->desc, strlen(DeviceGroupSync::MARK)), array_keys(Catalog::GROUP_NAMES)))->values(),
            'customGroups' => $groups->where('auto', false)->values(),
            'uncategorized' => $uncategorized,
            'needsSync' => $auto === [],
        ]);
    }

    public function show(Request $request, DeviceGroup $group): View
    {
        Gate::authorize('viewAny', Device::class);
        abort_unless(DeviceGroup::hasAccess($request->user())->whereKey($group->id)->exists(), 403);

        $devices = $group->devices()->whereIn('devices.device_id', Device::hasAccess($request->user())->select('devices.device_id'))
            ->with(['location'])->orderByRaw('status = 1')->orderBy('display')->orderBy('hostname')->get();
        $details = DB::table('ant_device_details')->leftJoin('ant_sites', 'ant_sites.id', '=', 'ant_device_details.site_id')
            ->whereIn('device_id', $devices->pluck('device_id'))->get(['device_id', 'ant_sites.code as site_code', 'category'])->keyBy('device_id');

        return view('antareja::groups.show', [
            'group' => $group,
            'auto' => str_starts_with((string) $group->desc, DeviceGroupSync::MARK),
            'devices' => $devices,
            'details' => $details,
        ]);
    }

    public function sync(Request $request, DeviceGroupSync $sync, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('ant-inventory.update');
        $r = $sync->syncAll();
        $audit->log('synced', 'device_group', "Grup otomatis disinkronkan: {$r['groups']} grup, {$r['members']} anggota");

        return back()->with('antareja_success', "Grup otomatis diperbarui: {$r['members']} perangkat dalam {$r['groups']} grup.");
    }

    /** @return array<int, object{total:int, up:int, down:int}> */
    private function stats(Request $request): array
    {
        return DB::table('device_group_device')
            ->join('devices', 'devices.device_id', '=', 'device_group_device.device_id')
            ->whereIn('devices.device_id', Device::hasAccess($request->user())->select('devices.device_id'))
            ->groupBy('device_group_device.device_group_id')
            ->selectRaw('device_group_device.device_group_id AS gid, COUNT(*) AS total,
                SUM(devices.status = 1 AND devices.disabled = 0 AND devices.`ignore` = 0) AS up,
                SUM(devices.status = 0 AND devices.disabled = 0 AND devices.`ignore` = 0) AS down')
            ->get()->keyBy('gid')->all();
    }
}
