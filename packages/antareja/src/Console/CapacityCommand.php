<?php

namespace Antareja\Console;

use Antareja\Services\CapacityService;
use Illuminate\Console\Command;

class CapacityCommand extends Command
{
    protected $signature = 'antareja:capacity {--backfill=90 : Hari histori RRD yang diambil untuk item baru}';

    protected $description = 'Perbarui item kapasitas, data harian dari histori RRD, dan prakiraan';

    public function handle(CapacityService $capacity): int
    {
        $new = $capacity->syncItems();
        $r = $capacity->computeAll((int) $this->option('backfill'));
        $this->info(sprintf('Kapasitas: %d item baru, %d item dihitung, %d baris harian diperbarui.', $new, $r['items'], $r['days']));

        return self::SUCCESS;
    }
}
