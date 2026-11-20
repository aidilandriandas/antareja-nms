<?php

namespace Antareja\Console;

use Antareja\Models\CctvStream;
use Antareja\Services\CctvHealth;
use Illuminate\Console\Command;

class CctvHealthCommand extends Command
{
    protected $signature = 'antareja:cctv-health {--id=* : Hanya stream tertentu}';

    protected $description = 'Cek kesehatan gambar CCTV (gelap, polos, beku, buram, bergeser) dari snapshot';

    public function handle(CctvHealth $health): int
    {
        $q = CctvStream::with(['device', 'credential'])->where('enabled', true)->where('health_check', true);
        if ($this->option('id')) {
            $q->whereIn('id', $this->option('id'));
        }
        $counts = [];
        foreach ($q->get() as $s) {
            // Kamera yang down sudah menjadi problem tersendiri — tidak perlu menarik video
            if ($s->device && ! $s->device->status && ! $s->device->disabled) {
                $counts['kamera down'] = ($counts['kamera down'] ?? 0) + 1;
                continue;
            }
            $r = $health->check($s);
            $counts[$r['health']] = ($counts[$r['health']] ?? 0) + 1;
        }
        $this->info('Kesehatan gambar CCTV: ' . ($counts ? collect($counts)->map(fn ($n, $k) => "{$k} {$n}")->implode(', ') : 'tidak ada stream'));

        return self::SUCCESS;
    }
}
