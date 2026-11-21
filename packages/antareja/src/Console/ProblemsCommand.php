<?php

namespace Antareja\Console;

use Antareja\Services\EscalationService;
use Antareja\Services\MaintenanceService;
use Antareja\Services\ProblemEngine;
use Antareja\Services\TriggerEngine;
use Illuminate\Console\Command;

class ProblemsCommand extends Command
{
    protected $signature = 'antareja:problems {--no-escalation : Hanya sinkron problem, tanpa incident otomatis & notifikasi}';

    protected $description = 'Sinkron problem dari semua sumber, korelasi, incident otomatis, dan eskalasi notifikasi';

    public function handle(ProblemEngine $engine, MaintenanceService $maintenance, EscalationService $escalation, TriggerEngine $triggers): int
    {
        // Detak penjadwal (tugas ini dijadwalkan tiap menit) — dipakai peringatan "penjadwal berhenti"
        \Illuminate\Support\Facades\Cache::put('antareja:scheduler-tick', now()->timestamp, 300);
        $started = $maintenance->tick();
        $t = $triggers->evaluate();
        $p = $engine->sync();
        $this->info(sprintf('Trigger: %d dievaluasi, %d problem, %d tanpa data.', $t['evaluated'], $t['problem'], $t['unknown']));
        $this->info(sprintf('Problem: %d observasi, %d baru, %d selesai. Maintenance dimulai: %d.', $p['observations'], $p['opened'], $p['resolved'], $started));

        if (! $this->option('no-escalation')) {
            $e = $escalation->run();
            $this->info(sprintf('Eskalasi: %d incident otomatis, %d notifikasi terkirim.', $e['auto_incidents'], $e['notifications']));
        }

        return self::SUCCESS;
    }
}
