<?php

namespace Antareja\Console;

use Antareja\Models\QualityTarget;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SchedulerStatusCommand extends Command
{
    protected $signature = 'antareja:scheduler-status';

    protected $description = 'Cek apakah penjadwal tugas berjalan (probe kualitas, problem, notifikasi)';

    public function handle(): int
    {
        $ts = Cache::get('antareja:scheduler-tick') ?: Cache::get('scheduler_working');
        $last = QualityTarget::where('enabled', true)->max('last_checked_at');
        if ($ts) {
            $this->line('OK penjadwal aktif ' . (int) abs(now()->diffInSeconds(\Illuminate\Support\Carbon::createFromTimestamp($ts))) . ' detik lalu'
                . ($last ? '; probe kualitas terakhir ' . \Illuminate\Support\Carbon::parse($last)->diffForHumans() : ''));

            return self::SUCCESS;
        }
        $this->line('STALE penjadwal tidak berjalan > 5 menit' . ($last ? '; probe kualitas terakhir ' . \Illuminate\Support\Carbon::parse($last)->diffForHumans() : ''));

        return self::FAILURE;
    }
}
