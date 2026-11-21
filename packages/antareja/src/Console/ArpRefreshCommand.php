<?php

namespace Antareja\Console;

use Antareja\Services\IpamService;
use App\Models\Device;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Segarkan tabel ARP dari router/L3 lebih sering daripada discovery penuh LibreNMS (bawaan tiap 6 jam), lalu cek ulang
 * konflik IP. Hanya perangkat yang memang punya tabel ARP (pernah melaporkan entri ARP) yang dibaca — SNMP read-only.
 */
class ArpRefreshCommand extends Command
{
    protected $signature = 'antareja:arp-refresh {--max=30 : maksimal perangkat per putaran}';

    protected $description = 'Baca ulang tabel ARP router/L3 (SNMP) lalu cek ulang konflik IP di IPAM';

    public function handle(IpamService $ipam): int
    {
        $ids = DB::table('ipv4_mac')->leftJoin('ports', 'ports.port_id', '=', 'ipv4_mac.port_id')
            ->selectRaw('DISTINCT COALESCE(ipv4_mac.device_id, ports.device_id) AS id')->pluck('id')->filter()->all();
        $devices = Device::whereIn('device_id', $ids)->where('status', 1)->where('disabled', 0)->where('snmp_disable', 0)
            ->orderBy('device_id')->limit((int) $this->option('max'))->get(['device_id', 'hostname']);

        Cache::put('antareja:arp-refresh:running', now()->toIso8601String(), 900);
        $ok = 0;
        foreach ($devices as $d) {
            try {
                Artisan::call('device:discover', ['device spec' => (string) $d->device_id, '--modules' => ['arp-table']]);
                $ok++;
            } catch (\Throwable $e) {
                $this->warn("{$d->hostname}: " . $e->getMessage());
            }
        }
        $r = $ipam->sync();
        Cache::forget('antareja:arp-refresh:running');
        Cache::put('antareja:arp-refresh:last', ['at' => now()->toIso8601String(), 'devices' => $ok, 'conflicts' => $r['conflicts']], 86400 * 7);
        $this->info("ARP dibaca ulang dari {$ok} perangkat; konflik IP aktif: {$r['conflicts']}.");

        return self::SUCCESS;
    }
}
