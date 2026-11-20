<?php

namespace Antareja\Console;

use Antareja\Services\AnomalyDetector;
use Illuminate\Console\Command;

class AnomaliesCommand extends Command
{
    protected $signature = 'antareja:anomalies';

    protected $description = 'Deteksi anomali statistik (latency, loss, traffic, flapping) terhadap baseline jam yang sama';

    public function handle(AnomalyDetector $detector): int
    {
        $r = $detector->run();
        $this->info(sprintf('Anomali: %d aktif, %d baru, %d selesai.', $r['open'], $r['new'], $r['closed']));

        return self::SUCCESS;
    }
}
