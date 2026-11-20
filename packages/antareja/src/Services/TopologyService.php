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
            ->select(['devices.device_id', 'devices.ip', 'devices.hostname', 'devices.display', 'devices.sysName', 'devices.status', 'devices.disabled',
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

        // Sambungan tanpa port (mis. dibuat manual): cari port A yang menghadap B dari tabel ARP (IP B terlihat di port itu)
        $noPort = array_filter($pairs, fn ($p) => ! $p['a_port'] && ! $p['b_port']);
        if ($noPort) {
            $ipOf = fn ($d) => $d->ip ?: (filter_var($d->hostname, FILTER_VALIDATE_IP) ? $d->hostname : null);
            $arp = DB::table('ipv4_mac')->whereIn('device_id', collect($noPort)->flatMap(fn ($p) => [$p['from'], $p['to']])->unique())
                ->whereIn('ipv4_address', collect($noPort)->flatMap(fn ($p) => [$ipOf($devices[$p['from']]), $ipOf($devices[$p['to']])])->filter()->unique())
                ->whereNotNull('port_id')->get(['device_id', 'port_id', 'ipv4_address']);
            foreach ($noPort as $key => $p) {
                $hitA = $arp->first(fn ($r) => $r->device_id == $p['from'] && $r->ipv4_address === $ipOf($devices[$p['to']]));
                $hitB = $arp->first(fn ($r) => $r->device_id == $p['to'] && $r->ipv4_address === $ipOf($devices[$p['from']]));
                $pairs[$key]['a_port'] = $hitA?->port_id;
                $pairs[$key]['b_port'] = $hitB?->port_id;
                $pairs[$key]['port_guess'] = (bool) ($hitA || $hitB);
            }
        }

        $portIds = collect($pairs)->flatMap(fn ($p) => [$p['a_port'], $p['b_port']])->filter()->unique()->values();
        $ports = $portIds->isEmpty() ? collect() : DB::table('ports')->whereIn('port_id', $portIds)
            ->get(['port_id', 'device_id', 'ifIndex', 'ifName', 'ifDescr', 'ifOperStatus', 'ifAdminStatus', 'ifSpeed', 'ifInOctets_rate', 'ifOutOctets_rate'])->keyBy('port_id');

        $edges = [];
        foreach ($pairs as $key => $p) {
            $pa = $p['a_port'] ? $ports->get($p['a_port']) : null;
            $pb = $p['b_port'] ? $ports->get($p['b_port']) : null;
            $port = $pa ?: $pb;
            $side = $pa ? 'a' : ($pb ? 'b' : null);
            $util = null;
            $bps = null;
            $downBps = null; // arah A → B (ke perangkat bawah)
            $upBps = null;   // arah B → A (ke perangkat atas)
            if ($port && $port->ifInOctets_rate !== null) {
                $in = (int) $port->ifInOctets_rate * 8;
                $out = (int) $port->ifOutOctets_rate * 8;
                // Port di sisi A: keluar = ke bawah. Port di sisi B: masuk = ke bawah.
                [$downBps, $upBps] = $side === 'a' ? [$out, $in] : [$in, $out];
                $bps = max($in, $out);
                $util = $port->ifSpeed > 0 ? round(min(100, $bps / $port->ifSpeed * 100), 1) : null;
            }
            $da = $devices->get($p['from']);
            $db = $devices->get($p['to']);
            $down = ! $da->status || ! $db->status || ($port && $port->ifAdminStatus === 'up' && $port->ifOperStatus !== 'up');
            $state = $down ? 'down' : ($util === null ? ($bps !== null ? 'ok' : 'unknown') : ($util >= 90 ? 'crit' : ($util >= 70 ? 'warn' : 'ok')));

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
                'down_bps' => $downBps,
                'up_bps' => $upBps,
                'port_id' => $port?->port_id,
                'port_side' => $side,
                'port_guess' => $p['port_guess'] ?? false,
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

    /**
     * Baca counter oktet port langsung dari perangkat (SNMP GET, read-only) untuk mode trafik live.
     * Browser menghitung bps dari selisih dua pembacaan. Dibatasi jumlah port agar tidak membebani perangkat.
     *
     * @param  list<int>  $portIds
     * @return array<int, array{in: ?string, out: ?string, t: float}>
     */
    public function liveCounters(User $user, array $portIds, int $max = 40): array
    {
        $ports = DB::table('ports')->whereIn('port_id', array_slice(array_unique(array_map('intval', $portIds)), 0, $max))
            ->whereIn('device_id', Device::hasAccess($user)->select('devices.device_id'))
            ->where('deleted', 0)->get(['port_id', 'device_id', 'ifIndex']);
        $out = [];
        foreach ($ports->groupBy('device_id') as $deviceId => $list) {
            $device = Device::find($deviceId);
            if (! $device || $device->snmp_disable || ! $device->status) {
                continue;
            }
            $oids = [];
            foreach ($list as $p) {
                $oids[] = '.1.3.6.1.2.1.31.1.1.1.6.' . $p->ifIndex;   // ifHCInOctets
                $oids[] = '.1.3.6.1.2.1.31.1.1.1.10.' . $p->ifIndex;  // ifHCOutOctets
            }
            try {
                $vals = $this->normalize(\SnmpQuery::device($device)->numeric()->get($oids)->values());
                $missing = $list->filter(fn ($p) => ! is_numeric($vals['1.3.6.1.2.1.31.1.1.1.6.' . $p->ifIndex] ?? null));
                if ($missing->isNotEmpty()) { // perangkat lama tanpa counter 64-bit
                    $old = [];
                    foreach ($missing as $p) {
                        $old[] = '.1.3.6.1.2.1.2.2.1.10.' . $p->ifIndex;
                        $old[] = '.1.3.6.1.2.1.2.2.1.16.' . $p->ifIndex;
                    }
                    $v32 = $this->normalize(\SnmpQuery::device($device)->numeric()->get($old)->values());
                    foreach ($missing as $p) {
                        $vals['1.3.6.1.2.1.31.1.1.1.6.' . $p->ifIndex] = $v32['1.3.6.1.2.1.2.2.1.10.' . $p->ifIndex] ?? null;
                        $vals['1.3.6.1.2.1.31.1.1.1.10.' . $p->ifIndex] = $v32['1.3.6.1.2.1.2.2.1.16.' . $p->ifIndex] ?? null;
                    }
                }
            } catch (\Throwable) {
                continue;
            }
            $t = microtime(true);
            foreach ($list as $p) {
                $in = $vals['1.3.6.1.2.1.31.1.1.1.6.' . $p->ifIndex] ?? null;
                $o = $vals['1.3.6.1.2.1.31.1.1.1.10.' . $p->ifIndex] ?? null;
                if (is_numeric($in) && is_numeric($o)) {
                    // string agar counter 64-bit tidak kehilangan presisi di JSON
                    $out[(int) $p->port_id] = ['in' => (string) $in, 'out' => (string) $o, 't' => $t];
                }
            }
        }

        return $out;
    }

    private function normalize(array $values): array
    {
        $n = [];
        foreach ($values as $k => $v) {
            $n[ltrim((string) $k, '.')] = is_string($v) ? trim($v) : $v;
        }

        return $n;
    }
}
