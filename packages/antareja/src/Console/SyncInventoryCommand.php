<?php

namespace Antareja\Console;

use Antareja\Services\InventorySync;
use Illuminate\Console\Command;

class SyncInventoryCommand extends Command
{
    protected $signature = 'antareja:sync-inventory';

    protected $description = 'Buat detail inventaris ANTAREJA untuk device LibreNMS yang belum terdaftar (status: Discovered)';

    public function handle(InventorySync $sync): int
    {
        $result = $sync->syncAll();
        $this->info(sprintf('%d device baru masuk antrean verifikasi (total device: %d).', $result['created'], $result['total']));

        return self::SUCCESS;
    }
}
