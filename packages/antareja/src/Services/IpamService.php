<?php

namespace Antareja\Services;

use Antareja\Models\IpAddress;
use Antareja\Models\IpHistory;
use Antareja\Models\Subnet;
use Antareja\Support\IpMath;
use App\Models\Device;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Logika IPAM: sinkron IP terpakai dari data poller, deteksi konflik, statistik utilisasi, IP tersedia.
 *
 * Sumber data (semuanya dikumpulkan poller, tidak ada pemindaian tambahan):
 *  - ipv4_addresses / ipv6_addresses : IP yang terpasang di interface perangkat
 *  - devices.ip                      : IP manajemen perangkat
 *  - ipv4_mac                        : tabel ARP router / L3 switch (hanya untuk IP di dalam subnet IPAM)
 */
class IpamService
{
    /** @var Collection<int, Subnet>|null */
    private ?Collection $subnets = null;

    public function __construct(private readonly AuditLogger $audit)
    {
    }

    // ------------------------------------------------------------------ subnet lookup

    /** @return Collection<int, Subnet> */
    public function subnets(): Collection
    {
        return $this->subnets ??= Subnet::orderByDesc('prefix')->get();
    }

    public function forgetSubnets(): void
    {
        $this->subnets = null;
    }

    /** Subnet paling spesifik yang memuat alamat ini. */
    public function subnetFor(string $bin): ?Subnet
    {
        return $this->subnets()->first(fn (Subnet $s) => $s->containsBinary($bin));
    }

    /** Pasang ulang subnet_id seluruh IP (dipakai setelah subnet ditambah / diubah / dihapus). */
    public function reassignSubnets(): void
    {
        $this->forgetSubnets();
        IpAddress::query()->chunkById(500, function ($ips): void {
            foreach ($ips as $ip) {
                $subnetId = $this->subnetFor($ip->address)?->id;
                if ($ip->subnet_id !== $subnetId) {
                    $ip->forceFill(['subnet_id' => $subnetId])->saveQuietly();
                }
            }
        });
    }

    // ------------------------------------------------------------------ statistik

    /**
     * @return array{usable:int|float, used:int, static:int, reserved:int, dhcp_records:int, dhcp_pool:int, allocated:int, available:int|float, utilization:float, conflicts:int}
     */
    public function stats(Subnet $subnet): array
    {
        $counts = IpAddress::where('subnet_id', $subnet->id)->selectRaw('status, COUNT(*) AS c')->groupBy('status')->pluck('c', 'status');
        $conflicts = IpAddress::where('subnet_id', $subnet->id)->where('conflict', true)->count();
        $usable = $subnet->usableCount();
        $records = (int) $counts->sum();

        // Pool DHCP dihitung terpakai; IP yang sudah tercatat di dalam pool tidak dihitung dua kali
        $pool = $subnet->dhcpPoolSize();
        $inPool = 0;
        if ($pool > 0) {
            $inPool = IpAddress::where('subnet_id', $subnet->id)
                ->whereBetween('address', [$subnet->dhcp_start, $subnet->dhcp_end])
                ->whereRaw('LENGTH(address) = 4')->count();
        }
        $allocated = $records + max(0, $pool - $inPool);
        $available = max(0, $usable - $allocated);

        return [
            'usable' => $usable,
            'used' => (int) ($counts['used'] ?? 0),
            'static' => (int) ($counts['static'] ?? 0),
            'reserved' => (int) ($counts['reserved'] ?? 0),
            'dhcp_records' => (int) ($counts['dhcp'] ?? 0),
            'dhcp_pool' => $pool,
            'allocated' => $allocated,
            'available' => $available,
            'utilization' => $usable > 0 ? round(100 * $allocated / $usable, 1) : 0.0,
            'conflicts' => $conflicts,
        ];
    }

    /**
     * IP IPv4 kosong (tidak tercatat, bukan gateway, di luar pool DHCP).
     *
     * @return string[]
     */
    public function freeAddresses(Subnet $subnet, int $limit = 256): array
    {
        if ($subnet->family !== 4) {
            return [];
        }
        [$start, $end] = IpMath::hostRange($subnet->network, $subnet->prefix);
        $taken = IpAddress::where('subnet_id', $subnet->id)->pluck('address')
            ->map(fn ($b) => IpMath::v4ToLong($b))->flip();
        if ($subnet->gateway) {
            $taken[IpMath::v4ToLong($subnet->gateway)] = true;
        }
        [$poolStart, $poolEnd] = $subnet->dhcpPoolSize() > 0
            ? [IpMath::v4ToLong($subnet->dhcp_start), IpMath::v4ToLong($subnet->dhcp_end)] : [null, null];

        $free = [];
        for ($ip = $start; $ip <= $end && count($free) < $limit; $ip++) {
            if (isset($taken[$ip]) || ($poolStart !== null && $ip >= $poolStart && $ip <= $poolEnd)) {
                continue;
            }
            $free[] = long2ip($ip);
        }

        return $free;
    }

    /**
     * Peta status setiap alamat untuk subnet IPv4 kecil (≤ /22), dipakai sebagai grid visual.
     *
     * @return array<int, array{ip:string, state:string, record:?IpAddress}>
     */
    public function addressMap(Subnet $subnet): array
    {
        if ($subnet->family !== 4 || $subnet->prefix < 22) {
            return [];
        }
        $records = IpAddress::where('subnet_id', $subnet->id)->get()->keyBy(fn ($r) => IpMath::v4ToLong($r->address));
        $network = IpMath::v4ToLong($subnet->network);
        $size = 2 ** (32 - $subnet->prefix);
        $gateway = $subnet->gateway ? IpMath::v4ToLong($subnet->gateway) : null;
        [$poolStart, $poolEnd] = $subnet->dhcpPoolSize() > 0
            ? [IpMath::v4ToLong($subnet->dhcp_start), IpMath::v4ToLong($subnet->dhcp_end)] : [null, null];

        $map = [];
        for ($i = 0; $i < $size; $i++) {
            $long = $network + $i;
            $record = $records[$long] ?? null;
            $state = match (true) {
                $subnet->prefix < 31 && ($i === 0 || $i === $size - 1) => 'network',
                $record?->conflict === true => 'conflict',
                $record !== null => $record->status,
                $long === $gateway => 'gateway',
                $poolStart !== null && $long >= $poolStart && $long <= $poolEnd => 'dhcp-pool',
                default => 'free',
            };
            $map[] = ['ip' => long2ip($long), 'state' => $state, 'record' => $record];
        }

        return $map;
    }

    // ------------------------------------------------------------------ alokasi manual

    public function allocate(string $address, array $data): IpAddress
    {
        $bin = IpMath::toBinary($address);
        $text = IpMath::toText($bin);
        $ip = IpAddress::firstWhere('address_text', $text);
        $old = $ip?->only(['status', 'hostname', 'description', 'mac_address', 'device_id']);

        $ip ??= new IpAddress([
            'family' => IpMath::family($bin), 'address' => $bin, 'address_text' => $text,
            'source' => 'manual', 'subnet_id' => $this->subnetFor($bin)?->id,
        ]);
        $ip->fill(array_intersect_key($data, array_flip(['status', 'hostname', 'description', 'mac_address', 'device_id', 'port_id'])));
        $ip->assigned_by = Auth::id();
        $ip->save();

        $this->history($ip, $old === null ? 'assigned' : 'updated',
            $old === null ? "Dialokasikan sebagai {$ip->status}" : 'Data IP diubah', $old, $ip->only(['status', 'hostname', 'description', 'mac_address', 'device_id']));
        $this->audit->log($old === null ? 'created' : 'updated', 'ip_address', "IP {$text} " . ($old === null ? "dialokasikan ({$ip->status})" : 'diubah'), $old, $ip->only(['status', 'hostname', 'device_id']));

        return $ip;
    }

    public function release(IpAddress $ip): void
    {
        $old = $ip->only(['status', 'hostname', 'description', 'mac_address', 'device_id']);
        $this->history($ip, 'released', 'IP dilepas dari IPAM', $old, null);
        $this->audit->log('deleted', 'ip_address', "IP {$ip->address_text} dilepas", $old);
        $ip->delete();
    }

    // ------------------------------------------------------------------ impor subnet

    /**
     * Network yang terlihat di interface perangkat tetapi belum ada di IPAM.
     *
     * @return Collection<int, array{cidr:string, family:int, addresses:int, devices:string}>
     */
    public function importCandidates(): Collection
    {
        $existing = Subnet::pluck('cidr')->flip();
        $rows = collect();

        $v4 = DB::table('ipv4_addresses')
            ->join('ports', 'ports.port_id', '=', 'ipv4_addresses.port_id')
            ->join('devices', 'devices.device_id', '=', 'ports.device_id')
            ->select('ipv4_addresses.ipv4_address as ip', 'ipv4_addresses.ipv4_prefixlen as prefix', 'devices.hostname')->get();
        foreach ($v4 as $r) {
            $rows->push(['ip' => $r->ip, 'prefix' => (int) $r->prefix, 'host' => $r->hostname]);
        }
        $v6 = DB::table('ipv6_addresses')
            ->join('ports', 'ports.port_id', '=', 'ipv6_addresses.port_id')
            ->join('devices', 'devices.device_id', '=', 'ports.device_id')
            ->select('ipv6_addresses.ipv6_address as ip', 'ipv6_addresses.ipv6_prefixlen as prefix', 'devices.hostname')->get();
        foreach ($v6 as $r) {
            $rows->push(['ip' => $r->ip, 'prefix' => (int) $r->prefix, 'host' => $r->hostname]);
        }

        return $rows->map(function ($r) {
            $bin = IpMath::toBinary($r['ip']);
            if (! $bin || IpMath::isIgnored($bin) || $r['prefix'] >= (strlen($bin) === 4 ? 31 : 127) || $r['prefix'] < (strlen($bin) === 4 ? 8 : 16)) {
                return null;
            }
            $net = IpMath::applyMask($bin, $r['prefix']);

            return ['cidr' => inet_ntop($net) . '/' . $r['prefix'], 'family' => IpMath::family($bin), 'host' => $r['host']];
        })->filter()->reject(fn ($r) => isset($existing[$r['cidr']]))
            ->groupBy('cidr')
            ->map(fn ($g, $cidr) => ['cidr' => $cidr, 'family' => $g[0]['family'], 'addresses' => $g->count(), 'devices' => $g->pluck('host')->unique()->take(5)->implode(', ')])
            ->sortKeys()->values();
    }

    public function createSubnet(array $data): Subnet
    {
        $parsed = IpMath::parseCidr($data['cidr']);
        $subnet = Subnet::create(array_merge($data, [
            'family' => $parsed['family'], 'network' => $parsed['network'], 'prefix' => $parsed['prefix'], 'cidr' => $parsed['cidr'],
        ]));
        $this->reassignSubnets();

        return $subnet;
    }

    // ------------------------------------------------------------------ sinkron dari poller

    /**
     * @return array{created:int, updated:int, conflicts:int}
     */
    public function sync(): array
    {
        $this->forgetSubnets();
        $observations = $this->collectObservations();
        $existing = IpAddress::whereIn('address_text', array_keys($observations))->get()->keyBy('address_text');
        $stats = ['created' => 0, 'updated' => 0, 'conflicts' => 0];
        $now = now();

        foreach ($observations as $text => $obs) {
            $bin = $obs['bin'];
            $subnet = $this->subnetFor($bin);
            $deviceIds = array_keys($obs['devices']);
            $macs = array_keys($obs['macs']);
            // MAC yang dipakai untuk catatan IP: satu-satunya MAC, atau yang dilaporkan paling banyak perangkat
            // (seri → MAC terkecil, agar tidak "berganti" hanya karena urutan baris berubah)
            $preferredMac = null;
            if ($obs['mac_votes']) {
                $top = array_keys($obs['mac_votes'], max($obs['mac_votes']), true);
                sort($top);
                $preferredMac = $top[0];
            }

            // IP dari ARP di luar subnet IPAM diabaikan agar perangkat user di LAN kantor tidak membanjiri IPAM
            if (! $subnet && ! isset($obs['sources']['interface']) && ! isset($obs['sources']['device'])) {
                continue;
            }

            $ip = $existing[$text] ?? null;
            $source = isset($obs['sources']['device']) ? 'device' : (isset($obs['sources']['interface']) ? 'interface' : 'arp');
            $mac = count($macs) === 1 ? $macs[0] : ($preferredMac ?: null);
            $deviceId = count($deviceIds) === 1 ? $deviceIds[0] : null;

            if (! $ip) {
                $ip = IpAddress::create([
                    'subnet_id' => $subnet?->id, 'family' => IpMath::family($bin), 'address' => $bin, 'address_text' => $text,
                    'status' => 'used', 'mac_address' => $mac, 'device_id' => $deviceId, 'port_id' => $obs['port_id'],
                    'source' => $source, 'first_seen_at' => $now, 'last_seen_at' => $now,
                ]);
                $this->history($ip, 'discovered', 'Pertama terlihat dari ' . IpAddress::SOURCES[$source], null,
                    ['mac_address' => $mac, 'device_id' => $deviceId], userId: null);
                $stats['created']++;
            } else {
                $changes = ['last_seen_at' => $now, 'subnet_id' => $subnet?->id];
                if ($mac && $ip->mac_address !== $mac) {
                    $this->history($ip, 'mac_changed', "MAC berubah {$ip->mac_address} → {$mac}", ['mac_address' => $ip->mac_address], ['mac_address' => $mac], userId: null);
                    $changes['mac_address'] = $mac;
                }
                // Device hanya diisi otomatis bila belum ditentukan engineer
                if ($deviceId && ! $ip->device_id) {
                    $changes['device_id'] = $deviceId;
                    $changes['port_id'] = $obs['port_id'];
                } elseif ($deviceId && $ip->device_id !== $deviceId && ! in_array($ip->status, IpAddress::MANUAL_STATUSES, true)) {
                    $this->history($ip, 'device_changed', 'Perangkat pemilik IP berubah', ['device_id' => $ip->device_id], ['device_id' => $deviceId], userId: null);
                    $changes['device_id'] = $deviceId;
                    $changes['port_id'] = $obs['port_id'];
                }
                if ($ip->source === 'arp' && $source !== 'arp') {
                    $changes['source'] = $source;
                }
                $ip->forceFill($changes)->saveQuietly();
                $stats['updated']++;
            }

            $stats['conflicts'] += $this->evaluateConflict($ip, $macs, $deviceIds, $obs['arp']) ? 1 : 0;
        }

        // IP berstatus konflik yang tidak lagi teramati: konflik dianggap selesai
        IpAddress::where('conflict', true)->whereNotIn('address_text', array_keys($observations))->get()
            ->each(fn (IpAddress $ip) => $this->evaluateConflict($ip, [], [], []));

        return $stats;
    }

    /**
     * @return array<string, array{bin:string, sources:array, macs:array, mac_votes:array, arp:array, devices:array, port_id:?int}>
     */
    private function collectObservations(): array
    {
        $obs = [];
        $add = function (?string $ipText, string $source, ?string $mac, ?int $deviceId, ?int $portId, ?string $reporter = null) use (&$obs): void {
            $bin = $ipText !== null ? IpMath::toBinary($ipText) : null;
            if (! $bin || IpMath::isIgnored($bin)) {
                return;
            }
            $text = inet_ntop($bin);
            $obs[$text] ??= ['bin' => $bin, 'sources' => [], 'macs' => [], 'mac_votes' => [], 'arp' => [], 'devices' => [], 'port_id' => null];
            $obs[$text]['sources'][$source] = true;
            if ($mac = IpMath::normalizeMac($mac)) {
                $obs[$text]['macs'][$mac] = true;
                $obs[$text]['mac_votes'][$mac] = ($obs[$text]['mac_votes'][$mac] ?? 0) + 1;
                // ARP per perangkat pelapor (+ VRF/context): konflik nyata = satu perangkat melihat >1 MAC untuk IP yang sama
                if ($reporter !== null) {
                    $obs[$text]['arp'][$reporter][$mac] = true;
                }
            }
            if ($deviceId) {
                $obs[$text]['devices'][$deviceId] = true;
                $obs[$text]['port_id'] ??= $portId;
            }
        };

        DB::table('ipv4_addresses')->join('ports', 'ports.port_id', '=', 'ipv4_addresses.port_id')
            ->select('ipv4_address', 'ports.device_id', 'ports.port_id', 'ports.ifPhysAddress')->orderBy('ipv4_address_id')
            ->each(fn ($r) => $add($r->ipv4_address, 'interface', $r->ifPhysAddress, (int) $r->device_id, (int) $r->port_id));

        DB::table('ipv6_addresses')->join('ports', 'ports.port_id', '=', 'ipv6_addresses.port_id')
            ->select('ipv6_address', 'ports.device_id', 'ports.port_id', 'ports.ifPhysAddress')->orderBy('ipv6_address_id')
            ->each(fn ($r) => $add($r->ipv6_address, 'interface', $r->ifPhysAddress, (int) $r->device_id, (int) $r->port_id));

        foreach (Device::whereNotNull('ip')->get(['device_id', 'ip', 'overwrite_ip']) as $device) {
            $add($device->overwrite_ip ?: $device->ip, 'device', null, $device->device_id, null);
        }

        DB::table('ipv4_mac')->leftJoin('ports', 'ports.port_id', '=', 'ipv4_mac.port_id')
            ->selectRaw('ipv4_mac.ipv4_address, ipv4_mac.mac_address, ipv4_mac.port_id, COALESCE(ipv4_mac.device_id, ports.device_id) AS reporter, ipv4_mac.context_name')
            ->orderBy('ipv4_mac.id')
            // kunci = router + VRF + interface: MAC berbeda di interface berbeda = subnet tumpang tindih, bukan host bentrok
            ->each(fn ($r) => $add($r->ipv4_address, 'arp', $r->mac_address, null, null,
                $r->reporter ? $r->reporter . '|' . ($r->context_name ?? '') . '|' . (int) $r->port_id : null));

        return $obs;
    }

    /**
     * Aturan konflik:
     *  - satu router/L3 melihat IP yang sama dengan >1 MAC di interface yang sama (per VRF);
     *  - MAC untuk IP ini berganti-ganti antara dua MAC berulang kali (≥ 3 kali dalam 6 jam) = dua host berebut IP;
     *  - IP yang sama terpasang di interface beberapa perangkat;
     *  - IP reserved aktif, atau IP static dipakai MAC lain.
     * MAC berbeda di tabel ARP perangkat yang *berbeda* BUKAN konflik: biasanya cache ARP lama (IP DHCP berpindah
     * ke klien lain) atau MAC interface VLAN/bridge yang berbeda dari port fisik.
     *
     * @param  string[]  $macs
     * @param  int[]  $deviceIds
     * @param  array<string, array<string, bool>>  $arpByReporter
     */
    private function evaluateConflict(IpAddress $ip, array $macs, array $deviceIds, array $arpByReporter = []): bool
    {
        $reasons = [];
        foreach ($arpByReporter as $reporter => $seen) {
            if (count($seen) > 1) {
                $name = Device::find((int) $reporter)?->displayName() ?? 'perangkat #' . (int) $reporter;
                $reasons[] = "terlihat dengan " . count($seen) . " MAC berbeda di tabel ARP {$name} (" . implode(', ', array_slice(array_keys($seen), 0, 3)) . ')';
                break;
            }
        }
        if ($ip->exists) {
            $changes = IpHistory::where('ip_address_id', $ip->id)->where('event', 'mac_changed')
                ->where('created_at', '>=', now()->subHours(6))->get(['old_values', 'new_values']);
            if ($changes->count() >= 3) {
                $involved = $changes->flatMap(fn ($h) => [$h->old_values['mac_address'] ?? null, $h->new_values['mac_address'] ?? null])->filter()->unique();
                if ($involved->count() === 2) {
                    $reasons[] = 'MAC berganti-ganti antara ' . $involved->implode(' dan ') . " ({$changes->count()}× dalam 6 jam) — dua perangkat memakai IP yang sama";
                }
            }
        }
        if (count($deviceIds) > 1) {
            $names = Device::whereIn('device_id', $deviceIds)->get()->map->displayName()->implode(', ');
            $reasons[] = "terpasang di beberapa perangkat ({$names})";
        }
        if ($ip->status === 'reserved' && ($macs || $deviceIds)) {
            $reasons[] = 'berstatus reserved tetapi aktif di jaringan';
        }
        if ($ip->status === 'static' && $ip->mac_address && $macs && ! in_array($ip->mac_address, $macs, true)) {
            $reasons[] = "MAC teramati berbeda dari alokasi static ({$ip->mac_address})";
        }

        $conflict = $reasons !== [];
        $detail = $conflict ? mb_substr('IP ' . implode('; ', $reasons), 0, 255) : null;

        if ($conflict !== $ip->conflict || $detail !== $ip->conflict_detail) {
            if ($conflict && ! $ip->conflict) {
                $this->history($ip, 'conflict', $detail, null, null, userId: null);
            } elseif (! $conflict && $ip->conflict) {
                $this->history($ip, 'resolved', 'Konflik tidak lagi terdeteksi', null, null, userId: null);
            }
            $ip->forceFill(['conflict' => $conflict, 'conflict_detail' => $detail])->saveQuietly();
        }

        return $conflict;
    }

    /**
     * Subnet yang terlihat di tabel ARP lebih dari satu interface pada router yang sama (mis. 10.17.18.x di VL-SECURITY
     * dan VL-TRAINING). Biasanya salah konfigurasi: alamat dari subnet yang sama terpasang di dua interface/VLAN.
     *
     * @return list<array{device: string, device_id: int, subnet: string, guessed: bool, interfaces: list<array{name: string, entries: int}>}>
     */
    public function interfaceOverlaps(): array
    {
        $this->forgetSubnets();
        $buckets = [];
        DB::table('ipv4_mac')->join('ports', 'ports.port_id', '=', 'ipv4_mac.port_id')
            ->selectRaw('ipv4_mac.ipv4_address, ipv4_mac.port_id, ports.ifName, ports.ifDescr, COALESCE(ipv4_mac.device_id, ports.device_id) AS reporter')
            ->whereNotNull('ipv4_mac.mac_address')->where('ipv4_mac.mac_address', '!=', '')
            ->orderBy('ipv4_mac.id')
            ->each(function ($r) use (&$buckets) {
                $bin = IpMath::toBinary($r->ipv4_address);
                if (! $bin || IpMath::isIgnored($bin)) {
                    return;
                }
                $subnet = $this->subnetFor($bin);
                $key = $subnet?->cidr ?? (preg_replace('/\.\d+$/', '.0/24', $r->ipv4_address) . '*');
                $buckets[$r->reporter][$key][$r->ifName ?: $r->ifDescr ?: ('port ' . $r->port_id)] =
                    ($buckets[$r->reporter][$key][$r->ifName ?: $r->ifDescr ?: ('port ' . $r->port_id)] ?? 0) + 1;
            });

        $out = [];
        foreach ($buckets as $deviceId => $subnets) {
            foreach ($subnets as $cidr => $ifaces) {
                if (count($ifaces) < 2) {
                    continue;
                }
                arsort($ifaces);
                $out[] = [
                    'device_id' => (int) $deviceId,
                    'device' => Device::find((int) $deviceId)?->displayName() ?? 'perangkat #' . (int) $deviceId,
                    'subnet' => rtrim($cidr, '*'),
                    'guessed' => str_ends_with($cidr, '*'),
                    'interfaces' => collect($ifaces)->map(fn ($n, $name) => ['name' => $name, 'entries' => $n])->values()->all(),
                ];
            }
        }

        return $out;
    }

    private function history(IpAddress $ip, string $event, ?string $description, ?array $old, ?array $new, int|false|null $userId = false): void
    {
        IpHistory::create([
            'ip_address_id' => $ip->id,
            'address_text' => $ip->address_text,
            'event' => $event,
            'description' => $description ? mb_substr($description, 0, 255) : null,
            'old_values' => $old,
            'new_values' => $new,
            'user_id' => $userId === false ? Auth::id() : $userId,
        ]);
    }
}
