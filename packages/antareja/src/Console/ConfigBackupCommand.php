<?php

namespace Antareja\Console;

use Antareja\Services\ConfigBackupService;
use Illuminate\Console\Command;

class ConfigBackupCommand extends Command
{
    protected $signature = 'antareja:config-backup {--device=* : Hanya perangkat tertentu (device_id)} {--force : Abaikan interval}';

    protected $description = 'Ambil backup konfigurasi perangkat (SSH, perintah baca saja) yang sudah waktunya';

    public function handle(ConfigBackupService $service): int
    {
        $r = $service->collectDue((bool) $this->option('force'), array_map('intval', (array) $this->option('device')));
        $this->info(sprintf('Backup konfigurasi: %d berubah, %d tetap, %d gagal, %d host key berubah.', $r['ok'], $r['unchanged'], $r['error'], $r['hostkey']));

        return self::SUCCESS;
    }
}
