<?php

namespace Antareja\Console;

use Antareja\Services\FastPing;
use Illuminate\Console\Command;

class FastPingCommand extends Command
{
    protected $signature = 'antareja:fast-ping {--prune : hapus sampel > 24 jam & kejadian > 30 hari}';

    protected $description = 'Ping cepat semua perangkat (tiap menit) untuk Layar NOC: down cepat, latensi, linimasa kejadian';

    public function handle(FastPing $ping): int
    {
        if ($this->option('prune')) {
            $ping->prune();
            $this->info('Data lama dibersihkan.');

            return self::SUCCESS;
        }
        $r = $ping->run();
        $this->info("{$r['devices']} perangkat di-ping, {$r['down']} tidak menjawab, {$r['events']} kejadian baru.");

        return self::SUCCESS;
    }
}
