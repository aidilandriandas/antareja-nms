<?php

namespace Antareja\Services;

use Antareja\Support\Catalog;
use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Peta topologi: perangkat (node) dan sambungannya (edge).
 * Sumber sambungan: (1) Mapping Perangkat ANTAREJA yang diverifikasi engineer (paling dipercaya),
 * (2) saran mapping yang belum diverifikasi (garis putus-putus), (3) tetangga LLDP/CDP yang ditemukan LibreNMS.
 * Satu pasangan perangkat hanya digambar sekali (sumber paling dipercaya yang menang).
 * Warna garis = pemakaian bandwidth port saat ini (dari polling LibreNMS), merah bila port/perangkat down.
 */
class TopologyService
{
    /** Tingkat hierarki (atas → bawah) untuk tata letak otomatis. */
    private const LEVELS = [
        'firewall' => 0, 'router' => 0, 'core-switch' => 1, 'distribution-switch' => 2, 'switch' => 2, 'wireless-controller' => 2,
        'ptp' => 2, 'ptmp' => 2, 'server' => 3, 'nvr' => 3, 'access-switch' => 3, 'ups' => 3, 'wireless-ap' => 4, 'cctv-camera' => 4, 'other' => 4,
    ];

    /**
     * @param  array{site_id?: ?int, group_id?: ?int}  $filter
     * @return array{nodes: list<array>, edges: list<array>, stats: array}
     */
    public function graph(User $user, array $filter = []): array
    {
        $q = Device::hasAccess($user)
            ->leftJoin('ant_device_details as dd', 'dd.device_id', '=', 'devices.device_id')
            ->leftJoin('ant_sites as s', 's.id', '=', 'dd.site_id')
            ->leftJoin('ant_topology_positions as tp', 'tp.device_id', '=', 'devices.device_id')
            ->select(['devices.device_id', 'devices.hostname', 'devices.display', 'devices.sysName', 'devices.status', 'devices.disabled',
                'devices.ignore', 'devices.uptime', 'devices.os', 'devices.hardware', 'devices.last_polled', 'dd.category', 'dd.site_id',
                's.code as site_code', 's.name as site_name', 'tp.x', 'tp.y']);
        if (! empty($filter['site_id'])) {
            $q->where('dd.site_id', $filter['site_id']);
        }
        if (! empty($filter['group_id'])) {
            $q->whereIn('devices.device_id', DB::table('device_group_device')->where('device_group_id', $filter['group_id'])->select('device_id'));
        }
        $devices = $q->get()->keyBy('device_id');
        $ids = $devices->keys();

        $nodes = $devices->map(function ($d) {
            $state = ($d->disabled || $d->ignore) ? 'off' : ($d->status ? 'up' : 'down');
            $category = $d->category ?: 'other';

            return [
                'id' => (int) $d->device_id,
                'label' => $d->display ?: $d->sysName ?: $d->hostname,
                'ip' => $d->hostname,
                'state' => $state,
                'category' => $category,
                'group' => Catalog::GROUP_NAMES[$category] ?? 'Lainnya',
                'icon' => Catalog::CATEGORY_ICONS[$category] ?? 'microchip',
                'level' => self::LEVELS[$category] ?? 4,
                'site' => $d->site_code,
                'site_name' => $d->site_name,
                'hardware' => $d->hardware,
                'uptime' => $d->status && $d->uptime ? \LibreNMS\Util\Time::formatInterval((int) $d->uptime, true) : null,
                'x' => $d->x !== null ? (int) $d->x : null,
                'y' => $d->y !== null ? (int) $d->y : null,
                'url' => url('device/' . $d->device_id),
            ];
        })->values();

        // --- sambungan
        $pairs = [];
        $add = function (int $a, int $b, ?int $aPort, ?int $bPort, string $source, ?int $linkId = null, ?string $type = null) use (&$pairs, $ids) {
            if ($a === $b || ! $ids->contains($a) || ! $ids->contains($b)) {
                return;
            }
            $key = min($a, $b) . '-' . max($a, $b);
            $rank = ['verified' => 3, 'suggested' => 1, 'lldp' => 2][$source];
            if (! isset($pairs[$key]) || $pairs[$key]['rank'] < $rank) {
                $pairs[$key] = ['from' => $a, 'to' => $b, 'a_port' => $aPort, 'b_port' => $bPort, 'source' => $source, 'rank' => $rank, 'link_id' => $linkId, 'type' => $type];
            } elseif ($pairs[$key]['a_port'] === null && $aPort !== null) {
                $pairs[$key]['a_port'] = $aPort;
                $pairs[$key]['b_port'] ??= $bPort;
            }
        };

        DB::table('ant_device_links')->whereIn('status', ['verified', 'suggested'])
            ->whereIn('a_device_id', $ids)->whereIn('b_device_id', $ids)
            ->get(['id', 'a_device_id', 'b_device_id', 'a_port_id', 'b_port_id', 'status', 'link_type'])
            ->each(fn ($l) => $add((int) $l->a_device_id, (int) $l->b_device_id, $l->a_port_id, $l->b_port_id, $l->status, (int) $l->id, $l->link_type));

        DB::table('links')->where('active', 1)->where('remote_device_id', '>', 0)
            ->whereIn('local_device_id', $ids)->whereIn('remote_device_id', $ids)
            ->get(['local_device_id', 'remote_device_id', 'local_port_id', 'remote_port_id'])
            ->each(fn ($l) => $add((int) $l->local_device_id, (int) $l->remote_device_id, $l->local_port_id, $l->remote_port_id, 'lldp'));

        $portIds = collect($pairs)->flatMap(fn ($p) => [$p['a_port'], $p['b_port']])->filter()->unique()->values();
        $ports = $portIds->isEmpty() ? collect() : DB::table('ports')->whereIn('port_id', $portIds)
            ->get(['port_id', 'ifName', 'ifDescr', 'ifOperStatus', 'ifAdminStatus', 'ifSpeed', 'ifInOctets_rate', 'ifOutOctets_rate'])->keyBy('port_id');

        $edges = [];
        foreach ($pairs as $key => $p) {
            $pa = $p['a_port'] ? $ports->get($p['a_port']) : null;
            $pb = $p['b_port'] ? $ports->get($p['b_port']) : null;
            $port = $pa ?: $pb;
            $util = null;
            $bps = null;
            if ($port && $port->ifSpeed > 0 && $port->ifInOctets_rate !== null) {
                $bps = max((int) $port->ifInOctets_rate, (int) $port->ifOutOctets_rate) * 8;
                $util = round(min(100, $bps / $port->ifSpeed * 100), 1);
            }
            $da = $devices->get($p['from']);
            $db = $devices->get($p['to']);
            $down = ! $da->status || ! $db->status || ($port && $port->ifAdminStatus === 'up' && $port->ifOperStatus !== 'up');
            $state = $down ? 'down' : ($util === null ? 'unknown' : ($util >= 90 ? 'crit' : ($util >= 70 ? 'warn' : 'ok')));

            $edges[] = [
                'id' => $key,
                'from' => $p['from'],
                'to' => $p['to'],
                'source' => $p['source'],
                'link_id' => $p['link_id'] ?? null,
                'type' => isset($p['type']) ? Catalog::label(Catalog::LINK_TYPES, $p['type']) : null,
                'state' => $state,
                'util' => $util,
                'bps' => $bps,
                'speed' => $port?->ifSpeed ? (int) $port->ifSpeed : null,
                'a_port' => $pa ? ($pa->ifName ?: $pa->ifDescr) : null,
                'b_port' => $pb ? ($pb->ifName ?: $pb->ifDescr) : null,
            ];
        }

        $byState = $nodes->countBy('state');

        return [
            'nodes' => $nodes->all(),
            'edges' => $edges,
            'stats' => [
                'nodes' => $nodes->count(),
                'edges' => count($edges),
                'up' => $byState['up'] ?? 0,
                'down' => $byState['down'] ?? 0,
                'off' => $byState['off'] ?? 0,
                'unlinked' => $nodes->count() - collect($edges)->flatMap(fn ($e) => [$e['from'], $e['to']])->unique()->count(),
            ],
        ];
    }

    /** @param  array<int|string, array{x: numeric, y: numeric}>  $positions */
    public function savePositions(array $positions, int $userId): int
    {
        $rows = [];
        $now = now();
        $valid = DB::table('devices')->whereIn('device_id', array_keys($positions))->pluck('device_id')->flip();
        foreach ($positions as $id => $pos) {
            if (isset($valid[$id]) && is_numeric($pos['x'] ?? null) && is_numeric($pos['y'] ?? null)) {
                $rows[] = ['device_id' => (int) $id, 'x' => (int) round($pos['x']), 'y' => (int) round($pos['y']),
                    'updated_by' => $userId, 'created_at' => $now, 'updated_at' => $now];
            }
        }
        if ($rows) {
            DB::table('ant_topology_positions')->upsert($rows, ['device_id'], ['x', 'y', 'updated_by', 'updated_at']);
        }

        return count($rows);
    }

    public function resetPositions(): void
    {
        DB::table('ant_topology_positions')->delete();
    }
}
