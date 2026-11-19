<?php

namespace Antareja\Console;

use Antareja\Services\QualityProbe;
use Illuminate\Console\Command;

class QualityMaintenanceCommand extends Command
{
    protected $signature = 'antareja:quality-maintenance {--days=2 : Jumlah hari terakhir yang di-rollup}';

    protected $description = 'Rollup harian data Network Quality dan hapus sampel lebih tua dari 30 hari';

    public function handle(QualityProbe $probe): int
    {
        for ($i = (int) $this->option('days') - 1; $i >= 0; $i--) {
            $day = now()->subDays($i);
            $this->line(sprintf('Rollup %s: %d target', $day->toDateString(), $probe->rollup($day)));
        }
        $this->info(sprintf('%d sampel lama (> %d hari) dihapus.', $probe->prune(), QualityProbe::RETENTION_DAYS));

        return self::SUCCESS;
    }
}
