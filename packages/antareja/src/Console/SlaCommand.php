<?php

namespace Antareja\Console;

use Antareja\Services\SlaCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class SlaCommand extends Command
{
    protected $signature = 'antareja:sla {--month= : Bulan YYYY-MM (bawaan: bulan lalu)} {--refresh : Hitung ulang walau sudah dibekukan}';

    protected $description = 'Hitung & bekukan SLA bulanan semua layanan untuk bulan yang sudah selesai';

    public function handle(): int
    {
        $month = $this->option('month')
            ? CarbonImmutable::createFromFormat('Y-m-d', $this->option('month') . '-01')->startOfMonth()
            : CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth();

        $stats = SlaCalculator::monthlyForServices($month, (bool) $this->option('refresh'));
        $met = collect($stats)->where('met', true)->count();
        $this->info(sprintf('SLA %s: %d layanan, %d memenuhi target%s.', $month->format('Y-m'), count($stats), $met,
            $month->addMonth()->isPast() ? '' : ' (bulan berjalan, tidak dibekukan)'));

        return self::SUCCESS;
    }
}
