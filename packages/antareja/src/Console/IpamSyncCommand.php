<?php

namespace Antareja\Console;

use Antareja\Services\IpamService;
use Illuminate\Console\Command;

class IpamSyncCommand extends Command
{
    protected $signature = 'antareja:ipam-sync';

    protected $description = 'Sinkronkan IP terpakai ke IPAM dari interface perangkat, IP manajemen, dan tabel ARP; deteksi konflik';

    public function handle(IpamService $ipam): int
    {
        $r = $ipam->sync();
        $this->info(sprintf('IPAM: %d IP baru, %d diperbarui, %d konflik.', $r['created'], $r['updated'], $r['conflicts']));

        return self::SUCCESS;
    }
}
