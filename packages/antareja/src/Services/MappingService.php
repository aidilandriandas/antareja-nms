<?php

namespace Antareja\Services;

use Antareja\Models\DeviceDetail;
use Antareja\Models\DeviceLink;
use App\Models\Device;
use App\Models\Link;
use App\Models\Port;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Device mapping berbasis bukti. Saran dibuat dari data yang benar-benar dikumpulkan LibreNMS:
 *  - LLDP / CDP neighbour (tabel links)
 *  - Tabel MAC switch (ports_fdb) + ARP (ipv4_mac) untuk perangkat ujung seperti CCTV & AP
 * Tidak ada topologi yang ditebak. Semua saran wajib diverifikasi engineer.
 */
class MappingService
{
    /** Kategori yang dianggap perangkat "infrastruktur" (kemungkinan besar sisi upstream). */
    private const UPSTREAM_CATEGORIES = ['router', 'core-switch', 'distribution-switch', 'switch', 'access-switch', 'firewall', 'wireless-controller'];

    /** Port dengan MAC lebih dari ini dianggap trunk/uplink, bukan port perangkat ujung. */
    private const MAX_MACS_ON_EDGE_PORT = 4;

    public function __construct(private readonly AuditLogger $audit)
    {
    }

    /**
     * @return array{lldp:int, fdb:int}
     */
    public function generateSuggestions(): array
    {
        $result = ['lldp' => $this->suggestFromNeighbours(), 'fdb' => $this->suggestFromFdb()];
        $this->audit->log('sync', 'device_link', sprintf('Saran mapping dibuat: %d dari LLDP/CDP, %d dari FDB/ARP', $result['lldp'], $result['fdb']));

        return $result;
    }

    private function suggestFromNeighbours(): int
    {
        $categories = DeviceDetail::pluck('category', 'device_id');
        $count = 0;

        Link::query()->where('active', 1)->where('remote_device_id', '>', 0)
            ->whereColumn('remote_device_id', '!=', 'local_device_id')
            ->orderBy('id')
            ->each(function (Link $link) use ($categories, &$count): void {
                $local = [$link->local_device_id, $link->local_port_id];
                $remote = [$link->remote_device_id, $link->remote_port_id];

                // Tentukan sisi upstream (A) dari kategori; default: perangkat yang melaporkan neighbour.
                [$a, $b] = $this->orient($local, $remote, $categories);

                $pair = [$a[0] . ':' . ($a[1] ?? 0), $b[0] . ':' . ($b[1] ?? 0)];
                sort($pair);
                $ref = implode('-', $pair);
                $source = in_array(strtolower((string) $link->protocol), ['cdp'], true) ? 'cdp' : 'lldp';

                if ($this->alreadyKnown($a[0], $b[0], $source, $ref)) {
                    return;
                }

                $localPort = $link->local_port_id ? Port::find($link->local_port_id) : null;
                $this->createSuggestion($a, $b, $this->guessType($categories[$b[0]] ?? null, $categories[$a[0]] ?? null), $source, $ref,
                    sprintf('%s: %s %s ↔ %s %s', strtoupper($source),
                        Device::find($link->local_device_id)?->displayName(), $localPort?->getShortLabel() ?? '?',
                        $link->remote_hostname, $link->remote_port));
                $count++;
            });

        return $count;
    }

    private function suggestFromFdb(): int
    {
        $count = 0;
        $details = DeviceDetail::with('device')->whereNotIn('verification_status', [DeviceDetail::REJECTED, DeviceDetail::RETIRED])->get();
        $categories = $details->pluck('category', 'device_id');

        foreach ($details as $detail) {
            $device = $detail->device;
            if (! $device || in_array($detail->category ?? $detail->suggested_category, self::UPSTREAM_CATEGORIES, true)) {
                continue; // FDB dipakai untuk perangkat ujung saja
            }

            foreach ($this->macsOf($device, $detail) as $mac) {
                $edge = $this->edgePortForMac($mac, $device->device_id);
                if (! $edge) {
                    continue;
                }

                $ref = "{$mac}:{$edge->port_id}";
                if ($this->alreadyKnown($edge->device_id, $device->device_id, 'fdb', $ref)) {
                    continue;
                }

                $this->createSuggestion([$edge->device_id, $edge->port_id], [$device->device_id, null],
                    $this->guessType($detail->category ?? $detail->suggested_category, $categories[$edge->device_id] ?? null), 'fdb', $ref,
                    sprintf('FDB: MAC %s terlihat di %s port %s (%d MAC di port tsb)', $this->formatMac($mac),
                        $edge->device?->displayName(), $edge->getShortLabel(), $edge->mac_count));
                $count++;
                break;
            }
        }

        return $count;
    }

    /** MAC perangkat: dari field inventaris, interface fisik device, atau ARP berdasarkan IP manajemen. */
    private function macsOf(Device $device, DeviceDetail $detail): array
    {
        $macs = [];
        if ($detail->mac_address) {
            $macs[] = $this->normalizeMac($detail->mac_address);
        }
        $ip = $device->overwrite_ip ?: $device->ip;
        if ($ip) {
            $macs = array_merge($macs, DB::table('ipv4_mac')->where('ipv4_address', $ip)->pluck('mac_address')->map($this->normalizeMac(...))->all());
        }
        $macs = array_merge($macs, Port::where('device_id', $device->device_id)->whereNotNull('ifPhysAddress')
            ->where('ifPhysAddress', '!=', '')->pluck('ifPhysAddress')->map($this->normalizeMac(...))->all());

        return array_values(array_unique(array_filter($macs, fn ($m) => strlen($m) === 12 && $m !== '000000000000')));
    }

    private function edgePortForMac(string $mac, int $selfDeviceId): ?Port
    {
        $candidates = DB::table('ports_fdb')->where('mac_address', $mac)->where('device_id', '!=', $selfDeviceId)->pluck('port_id');
        if ($candidates->isEmpty()) {
            return null;
        }

        $best = DB::table('ports_fdb')->select('port_id', DB::raw('COUNT(DISTINCT mac_address) AS mac_count'))
            ->whereIn('port_id', $candidates)->groupBy('port_id')->orderBy('mac_count')->first();

        if (! $best || $best->mac_count > self::MAX_MACS_ON_EDGE_PORT) {
            return null;
        }

        $port = Port::with('device')->find($best->port_id);
        if ($port) {
            $port->mac_count = (int) $best->mac_count;
        }

        return $port;
    }

    /**
     * @param  array{0:int,1:?int}  $x
     * @param  array{0:int,1:?int}  $y
     * @return array{0:array, 1:array}
     */
    private function orient(array $x, array $y, $categories): array
    {
        $xUp = in_array($categories[$x[0]] ?? null, self::UPSTREAM_CATEGORIES, true);
        $yUp = in_array($categories[$y[0]] ?? null, self::UPSTREAM_CATEGORIES, true);

        return (! $xUp && $yUp) ? [$y, $x] : [$x, $y];
    }

    private function guessType(?string $childCategory, ?string $parentCategory): string
    {
        return match (true) {
            $childCategory === 'wireless-ap' => 'ap-port',
            $childCategory === 'cctv-camera' && $parentCategory === 'nvr' => 'nvr-camera',
            $childCategory === 'cctv-camera' => 'cctv-port',
            $childCategory === 'ptp' && $parentCategory === 'ptp' => 'ptp-pair',
            $childCategory === 'ptmp' => 'ptmp-client',
            default => 'uplink',
        };
    }

    private function alreadyKnown(int $deviceA, int $deviceB, string $source, string $ref): bool
    {
        if (DeviceLink::where('source', $source)->where('source_ref', $ref)->exists()) {
            return true; // sudah pernah disarankan (termasuk yang ditolak → tidak disarankan ulang)
        }

        return DeviceLink::where('status', '!=', DeviceLink::REJECTED)
            ->where(fn ($q) => $q->where(['a_device_id' => $deviceA, 'b_device_id' => $deviceB])
                ->orWhere(fn ($q) => $q->where(['a_device_id' => $deviceB, 'b_device_id' => $deviceA])))
            ->exists();
    }

    private function createSuggestion(array $a, array $b, string $type, string $source, string $ref, string $evidence): DeviceLink
    {
        $link = new DeviceLink([
            'a_device_id' => $a[0], 'a_port_id' => $a[1] ?: null,
            'b_device_id' => $b[0], 'b_port_id' => $b[1] ?: null,
            'link_type' => $type,
        ]);
        $link->forceFill([
            'status' => DeviceLink::SUGGESTED,
            'source' => $source,
            'source_ref' => mb_substr($ref, 0, 64),
            'evidence_text' => mb_substr($evidence, 0, 255),
        ])->saveQuietly();

        return $link;
    }

    public function verify(DeviceLink $link): void
    {
        $old = $link->status;
        $link->forceFill(['status' => DeviceLink::VERIFIED, 'verified_by' => Auth::id(), 'verified_at' => now()])->saveQuietly();
        $this->syncDependency($link->a_device_id, $link->b_device_id);
        $this->audit->log('verified', $link, "Mapping {$link->auditName()} diverifikasi", ['status' => $old], ['status' => DeviceLink::VERIFIED]);
    }

    public function reject(DeviceLink $link): void
    {
        $old = $link->status;
        $link->forceFill(['status' => DeviceLink::REJECTED, 'verified_by' => Auth::id(), 'verified_at' => now()])->saveQuietly();
        if ($old === DeviceLink::VERIFIED) {
            $this->syncDependency($link->a_device_id, $link->b_device_id, allowRemove: true);
        }
        $this->audit->log('rejected', $link, "Mapping {$link->auditName()} ditolak", ['status' => $old], ['status' => DeviceLink::REJECTED]);
    }

    public function delete(DeviceLink $link): void
    {
        [$a, $b, $wasVerified] = [$link->a_device_id, $link->b_device_id, $link->status === DeviceLink::VERIFIED];
        $link->delete();
        if ($wasVerified) {
            $this->syncDependency($a, $b, allowRemove: true);
        }
    }

    /**
     * Sinkronkan dependensi parent/child LibreNMS (device_relationships) dari mapping terverifikasi,
     * sehingga alert perangkat anak ditekan saat parent-nya down.
     * Relasi hanya dilepas ($allowRemove) ketika mapping terverifikasi ANTAREJA untuk pasangan ini dibatalkan,
     * sehingga dependensi yang dibuat manual di LibreNMS tidak ikut terhapus saat verifikasi biasa.
     */
    public function syncDependency(int $parentId, int $childId, bool $allowRemove = false): void
    {
        $shouldExist = DeviceLink::where('status', DeviceLink::VERIFIED)
            ->where('a_device_id', $parentId)->where('b_device_id', $childId)
            ->whereIn('link_type', \Antareja\Support\Catalog::PARENT_CHILD_LINK_TYPES)
            ->exists();

        $exists = DB::table('device_relationships')->where('parent_device_id', $parentId)->where('child_device_id', $childId)->exists();

        if ($shouldExist && ! $exists) {
            DB::table('device_relationships')->insert(['parent_device_id' => $parentId, 'child_device_id' => $childId]);
        } elseif ($allowRemove && ! $shouldExist && $exists) {
            DB::table('device_relationships')->where('parent_device_id', $parentId)->where('child_device_id', $childId)->delete();
        }
    }

    private function normalizeMac(?string $mac): string
    {
        return strtolower(preg_replace('/[^0-9a-fA-F]/', '', (string) $mac));
    }

    private function formatMac(string $mac): string
    {
        return implode(':', str_split($mac, 2));
    }
}
