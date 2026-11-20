<?php

namespace Antareja\Support;

use App\Models;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Navigasi samping ANTAREJA: semua fitur (modul ANTAREJA + halaman LibreNMS) dikelompokkan per pekerjaan engineer,
 * bukan per jenis data seperti menu LibreNMS. Hak akses mengikuti permission modul & policy LibreNMS yang sama
 * dengan menu lama, jadi user tidak pernah melihat tautan yang akan ditolak (403).
 */
class Navigation
{
    public function __construct(private readonly ModuleRegistry $modules)
    {
    }

    /**
     * @return list<array{title: ?string, key: string, items: list<array{label: string, icon: string, url: string, active: bool}>}>
     */
    public function for(?Authenticatable $user, Request $request): array
    {
        if ($user === null) {
            return [];
        }
        $gate = Gate::forUser($user);
        $mod = $this->modules->menuFor($user)->keyBy('key');
        $m = fn (string $key, string $label, string $icon, array $also = []) => $mod->has($key)
            ? ['label' => $label, 'icon' => $icon, 'url' => $mod[$key]['url'], 'path' => $mod[$key]['path'], 'also' => $also] : null;
        $core = fn (bool $allowed, string $label, string $icon, string $path) => $allowed
            ? ['label' => $label, 'icon' => $icon, 'url' => url($path), 'path' => $path] : null;
        $can = fn (string $ability, $model = null) => $model ? $gate->allows($ability, $model) : $gate->allows($ability);

        $groups = [
            ['title' => null, 'key' => 'main', 'items' => [
                ['label' => 'Pusat Operasi', 'icon' => 'gauge-high', 'url' => route('antareja.home'), 'path' => 'antareja', 'exact' => true],
                $core($can('viewAny', Models\Device::class), 'Layar NOC', 'display', 'antareja/noc'),
                $core($can('viewAny', Models\Device::class), 'Peta Topologi', 'diagram-project', 'antareja/topology'),
                $m('management', 'Dashboard Manajemen', 'chart-column'),
                $m('dashboard', 'Dashboard Widget', 'table-cells-large'),
            ]],
            ['title' => 'Monitoring', 'key' => 'monitoring', 'items' => [
                $m('network-monitoring', 'Perangkat', 'server', ['device']),
                $m('interface-monitoring', 'Interface & Port', 'ethernet', ['port']),
                $m('wireless-monitoring', 'Wireless', 'wifi'),
                $m('cctv', 'CCTV', 'video'),
                $m('network-quality', 'Kualitas Jaringan', 'signal'),
                $core($can('viewAny', Models\Sensor::class), 'Kesehatan Hardware', 'heart-pulse', 'health'),
                $core($can('viewAny', Models\Service::class), 'Layanan', 'list-check', 'services'),
                $core($can('viewAny', Models\Device::class), 'Peta Ketersediaan', 'map', 'availability-map'),
                $core($can('viewAny', Models\Eventlog::class), 'Log Kejadian', 'scroll', 'eventlog'),
            ]],
            ['title' => 'Alert & Insiden', 'key' => 'alerts', 'items' => [
                $m('alert-center', 'Problem & Alert', 'bell'),
                $m('incident', 'Insiden', 'fire-extinguisher'),
                $m('maintenance', 'Maintenance', 'screwdriver-wrench'),
                $m('escalation', 'Eskalasi & Notifikasi', 'paper-plane'),
                $m('triggers', 'Trigger', 'crosshairs'),
                $core($can('viewAny', Models\AlertRule::class), 'Aturan Alert', 'sliders', 'alert-rules'),
            ]],
            ['title' => 'Inventaris & Aset', 'key' => 'assets', 'items' => [
                $core($can('device.create'), 'Tambah Perangkat', 'circle-plus', 'antareja/devices/add'),
                $core($can('viewAny', Models\Device::class), 'Grup Perangkat', 'layer-group', 'antareja/groups'),
                $m('sites', 'Site & Area', 'map-location-dot'),
                $m('device-inventory', 'Inventaris Perangkat', 'boxes-stacked'),
                $m('device-discovery', 'Discovery', 'magnifying-glass-plus'),
                $m('device-mapping', 'Mapping Perangkat', 'diagram-project'),
                $m('ipam', 'IP Address (IPAM)', 'network-wired'),
                $m('fiber', 'Fiber Optic', 'grip-lines'),
            ]],
            ['title' => 'Operasional', 'key' => 'ops', 'items' => [
                $m('workorders', 'Work Order', 'clipboard-list'),
                $m('changes', 'Change Request', 'right-left'),
                $m('config', 'Backup Konfigurasi', 'file-code'),
                $m('capacity', 'Kapasitas', 'chart-line'),
                $m('assistant', 'Asisten & Anomali', 'wand-magic-sparkles'),
            ]],
            ['title' => 'Laporan', 'key' => 'reports', 'items' => [
                $m('sla', 'SLA', 'percent'),
                $m('reports', 'Laporan Bulanan', 'file-lines'),
            ]],
            ['title' => 'Fitur Lainnya', 'key' => 'more', 'collapsed' => true, 'items' => [
                $core($can('device.create'), 'Tambah (form LibreNMS)', 'plus', 'addhost'),
                $core($can('viewAny', Models\DeviceGroup::class), 'Aturan Device Group', 'layer-group', 'device-groups'),
                $core($can('viewAny', Models\Location::class), 'Lokasi Geografis', 'location-dot', 'locations'),
                $core($can('viewAny', Models\Device::class), 'Peta Jaringan', 'sitemap', 'map'),
                $core($can('viewAny', Models\Vlan::class), 'VLAN', 'tags', 'vlans'),
                $core($can('viewAny', Models\Route::class), 'Routing', 'route', 'routing'),
                $core($can('viewAny', Models\Device::class), 'Aplikasi', 'cubes', 'apps'),
                $core($can('viewAny', Models\Syslog::class), 'Syslog', 'clone', 'syslog'),
                $core($can('viewAny', Models\AlertLog::class), 'Riwayat Alert', 'clock-rotate-left', 'alert-log'),
                $core($can('viewAny', Models\AlertTransport::class), 'Transport Alert', 'bus', 'alert-transports'),
                $core($can('viewAny', Models\AlertTemplate::class), 'Template Alert', 'file', 'templates'),
                $core($can('viewAny', Models\AlertSchedule::class), 'Jadwal Alert', 'calendar', 'alert-schedule'),
            ]],
            ['title' => 'Administrasi', 'key' => 'admin', 'items' => [
                $m('users', 'User & Role', 'users'),
                $m('audit', 'Audit Log', 'clock-rotate-left'),
                $core($can('settings.update'), 'Backup & Pemulihan', 'box-archive', 'antareja/backup'),
                $m('system', 'Pengaturan Sistem', 'gears'),
                $core($can('viewAny', Models\PollerCluster::class) || $can('viewAny', Models\PollerGroup::class), 'Poller', 'microchip', 'poller'),
                $core($can('settings.view'), 'Validasi Sistem', 'circle-check', 'validate'),
                ['label' => 'Tentang & Lisensi', 'icon' => 'circle-info', 'url' => route('antareja.about'), 'path' => 'antareja/about'],
            ]],
        ];

        $current = trim($request->path(), '/');
        $best = null;
        foreach ($groups as $g => &$group) {
            $group['items'] = array_values(array_filter($group['items']));
            foreach ($group['items'] as $i => &$item) {
                $item['active'] = false;
                $path = trim($item['path'], '/');
                $hit = ($item['exact'] ?? false)
                    ? ($current === $path || ($path === 'antareja' && $current === ''))
                    : ($current === $path || str_starts_with($current, $path . '/') || str_starts_with($current, $path . '?'));
                foreach ($item['also'] ?? [] as $alias) {
                    $hit = $hit || $current === $alias || str_starts_with($current, $alias . '/');
                }
                // tautan dengan path terpanjang yang cocok yang dianggap aktif
                if ($hit && ($best === null || strlen($path) > $best[2])) {
                    $best = [$g, $i, strlen($path)];
                }
            }
            unset($item);
        }
        unset($group);
        if ($best !== null) {
            $groups[$best[0]]['items'][$best[1]]['active'] = true;
            $groups[$best[0]]['collapsed'] = false;
        }

        return array_values(array_filter($groups, fn ($g) => $g['items'] !== []));
    }
}
