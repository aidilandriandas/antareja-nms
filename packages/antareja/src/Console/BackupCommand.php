<?php

namespace Antareja\Console;

use Antareja\Models\Backup;
use Antareja\Models\BackupSetting;
use Antareja\Services\BackupService;
use Illuminate\Console\Command;

class BackupCommand extends Command
{
    protected $signature = 'antareja:backup {--record= : ID catatan backup yang sudah dibuat (dari web)} {--scheduled : dijalankan oleh jadwal}';

    protected $description = 'Backup NMS lengkap (database, data, RRD) ke volume data + salinan SFTP opsional';

    public function handle(BackupService $service): int
    {
        if ($this->option('scheduled') && ! BackupSetting::current()->schedule_enabled) {
            $this->line('Backup terjadwal nonaktif.');

            return 0;
        }
        $backup = $this->option('record') ? Backup::findOrFail((int) $this->option('record'))
            : $service->newRecord($this->option('scheduled') ? 'schedule' : 'manual', null);
        if ($backup->status !== 'running') {
            $this->error('Catatan backup ini sudah selesai diproses.');

            return 1;
        }
        $this->line("Membuat backup {$backup->filename} ...");
        $backup = $service->run($backup);
        if ($backup->status !== 'ok') {
            $this->error('Gagal: ' . $backup->message);

            return 1;
        }
        $this->info(sprintf('Selesai: %s (%s)%s', $backup->path(), BackupService::human($backup->size),
            $backup->offsite_status === 'ok' ? ' — salinan SFTP OK' : ($backup->offsite_status === 'failed' ? ' — salinan SFTP GAGAL' : '')));

        return 0;
    }
}
