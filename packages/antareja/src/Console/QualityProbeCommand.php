<?php

namespace Antareja\Console;

use Antareja\Models\QualityTarget;
use Antareja\Services\QualityProbe;
use Illuminate\Console\Command;

class QualityProbeCommand extends Command
{
    protected $signature = 'antareja:quality-probe {--target=* : Hanya ukur target id tertentu}';

    protected $description = 'Quality Probe: ukur latency, packet loss, dan jitter semua target (ala SmokePing)';

    public function handle(QualityProbe $probe): int
    {
        $targets = QualityTarget::where('enabled', true)
            ->when($this->option('target'), fn ($q, $ids) => $q->whereIn('id', $ids))->get();
        $result = $probe->run($targets);
        $this->info(sprintf('%d target diukur, %d sampel disimpan, %d error.', $result['targets'], $result['samples'], $result['errors']));

        return $result['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
