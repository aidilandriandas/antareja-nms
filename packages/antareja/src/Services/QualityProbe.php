<?php

namespace Antareja\Services;

use Antareja\Models\QualitySample;
use Antareja\Models\QualityTarget;
use App\Facades\LibrenmsConfig;
use App\Models\Eventlog;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LibreNMS\Enum\Severity;
use Symfony\Component\Process\Process;

/**
 * Quality Probe — pengukuran kualitas jaringan ala SmokePing, berjalan native di ANTAREJA.
 *
 * Setiap siklus (5 menit) mengirim N ping (default 20, jeda 1 detik) ke tiap target dengan fping -C,
 * lalu menyimpan sebaran RTT: min, p10, p25, median, rata-rata, p75, p90, max, loss, jitter, stddev.
 */
class QualityProbe
{
    public const RETENTION_DAYS = 30;

    public const INTERVAL_SECONDS = 300;

    public function fpingPath(): string
    {
        $path = (string) (LibrenmsConfig::get('fping') ?: '/usr/sbin/fping');

        return is_executable($path) ? $path : 'fping';
    }

    /**
     * Ukur semua target aktif. Target dikelompokkan per jumlah ping; tiap kelompok satu proses fping paralel.
     *
     * @return array{targets:int, samples:int, errors:int}
     */
    public function run(?Collection $targets = null): array
    {
        $targets ??= QualityTarget::where('enabled', true)->get();
        $result = ['targets' => $targets->count(), 'samples' => 0, 'errors' => 0];
        $now = now()->startOfMinute();

        foreach ($targets->groupBy('pings') as $pings => $group) {
            $hosts = $group->pluck('host')->unique()->values()->all();
            try {
                $rtts = $this->ping($hosts, (int) $pings);
            } catch (\Throwable $e) {
                Log::error('ANTAREJA Quality Probe: ' . $e->getMessage());
                $result['errors']++;
                continue;
            }

            foreach ($group as $target) {
                $samples = $rtts[$target->host] ?? array_fill(0, (int) $pings, null); // host tak dikenal = loss 100%
                $this->store($target, $samples, $now);
                $result['samples']++;
            }
        }

        return $result;
    }

    /**
     * @param  string[]  $hosts
     * @return array<string, array<int, float|null>>  host => daftar RTT ms (null = hilang)
     */
    public function ping(array $hosts, int $count): array
    {
        if ($hosts === []) {
            return [];
        }
        $count = max(3, min(60, $count));
        $process = new Process(array_merge(
            [$this->fpingPath(), '-C', (string) $count, '-q', '-B1', '-r0', '-p', '1000', '-t', '900', '-i', '10'],
            $hosts,
        ));
        $process->setTimeout($count * 2 + 60);
        $process->run(); // exit 1 = ada host yang loss (normal); 2 = sebagian nama tidak bisa di-resolve
        $parsed = $this->parse($process->getErrorOutput() . "\n" . $process->getOutput());

        // exit ≥ 3 (argumen salah / gagal membuka socket ICMP) atau tidak ada satu pun baris hasil: probe yang gagal,
        // bukan link putus — jangan dicatat sebagai loss 100% agar tidak memicu problem palsu.
        if ($process->getExitCode() >= 3 || ($parsed === [] && $process->getExitCode() !== 0)) {
            throw new \RuntimeException('fping gagal (exit ' . $process->getExitCode() . '): '
                . mb_substr(trim($process->getErrorOutput() ?: $process->getOutput()), 0, 300));
        }

        return $parsed;
    }

    /**
     * Parse output "fping -C": "host : 1.23 1.10 - 2.01".
     *
     * @return array<string, array<int, float|null>>
     */
    public function parse(string $output): array
    {
        $result = [];
        foreach (preg_split('/\R/', $output) as $line) {
            if (! preg_match('/^(\S+)\s+:\s+((?:[\d.]+|-)(?:\s+(?:[\d.]+|-))*)\s*$/', trim($line), $m)) {
                continue;
            }
            $result[$m[1]] = array_map(fn ($v) => $v === '-' ? null : (float) $v, preg_split('/\s+/', trim($m[2])));
        }

        return $result;
    }

    /**
     * @param  array<int, float|null>  $rtts
     * @return array<string, float|int|null>
     */
    public function statistics(array $rtts): array
    {
        $sent = count($rtts);
        $ok = array_values(array_filter($rtts, fn ($v) => $v !== null));
        $received = count($ok);
        $stats = [
            'sent' => $sent, 'received' => $received,
            'loss_pct' => $sent > 0 ? round(100 * ($sent - $received) / $sent, 2) : 100.0,
            'min_ms' => null, 'p10_ms' => null, 'p25_ms' => null, 'median_ms' => null, 'avg_ms' => null,
            'p75_ms' => null, 'p90_ms' => null, 'max_ms' => null, 'jitter_ms' => null, 'stddev_ms' => null,
        ];
        if ($received === 0) {
            return $stats;
        }

        // Jitter: rata-rata |selisih| RTT berurutan (urutan pengiriman, paket hilang dilewati)
        $diffs = [];
        for ($i = 1; $i < $received; $i++) {
            $diffs[] = abs($ok[$i] - $ok[$i - 1]);
        }
        $sorted = $ok;
        sort($sorted);
        $avg = array_sum($ok) / $received;

        return array_merge($stats, [
            'min_ms' => $sorted[0],
            'p10_ms' => $this->percentile($sorted, 10),
            'p25_ms' => $this->percentile($sorted, 25),
            'median_ms' => $this->percentile($sorted, 50),
            'avg_ms' => round($avg, 3),
            'p75_ms' => $this->percentile($sorted, 75),
            'p90_ms' => $this->percentile($sorted, 90),
            'max_ms' => $sorted[$received - 1],
            'jitter_ms' => $diffs ? round(array_sum($diffs) / count($diffs), 3) : 0.0,
            'stddev_ms' => round(sqrt(array_sum(array_map(fn ($v) => ($v - $avg) ** 2, $ok)) / $received), 3),
        ]);
    }

    private function percentile(array $sorted, float $p): float
    {
        $n = count($sorted);
        if ($n === 1) {
            return $sorted[0];
        }
        $rank = ($p / 100) * ($n - 1);
        $lo = (int) floor($rank);
        $hi = (int) ceil($rank);

        return round($sorted[$lo] + ($sorted[$hi] - $sorted[$lo]) * ($rank - $lo), 3);
    }

    private function store(QualityTarget $target, array $rtts, CarbonInterface $at): void
    {
        $sample = QualitySample::create(array_merge($this->statistics($rtts), ['target_id' => $target->id, 'measured_at' => $at]));
        $status = $target->evaluate($sample);
        $previous = $target->last_status;
        $target->forceFill(['last_status' => $status, 'last_checked_at' => $at])->saveQuietly();

        if ($previous !== null && $previous !== $status && $target->device_id) {
            $severity = match ($status) {
                'ok' => Severity::Ok,
                'warning' => Severity::Warning,
                default => Severity::Error,
            };
            $detail = $sample->received === 0 ? 'loss 100%' : sprintf('median %.1f ms, loss %.0f%%, jitter %.1f ms', $sample->median_ms, $sample->loss_pct, $sample->jitter_ms);
            Eventlog::log("ANTAREJA Quality: {$target->name} {$previous} → {$status} ({$detail})", $target->device_id, 'antareja', $severity);
        }
    }

    // ------------------------------------------------------------------ rollup & retensi

    public function rollup(CarbonInterface $day): int
    {
        $start = $day->copy()->startOfDay();
        $end = $day->copy()->endOfDay();
        $count = 0;

        foreach (QualityTarget::pluck('id') as $targetId) {
            $rows = QualitySample::where('target_id', $targetId)->whereBetween('measured_at', [$start, $end])->get();
            if ($rows->isEmpty()) {
                continue;
            }
            $medians = $rows->pluck('median_ms')->filter(fn ($v) => $v !== null)->sort()->values()->all();
            DB::table('ant_quality_daily')->updateOrInsert(
                ['target_id' => $targetId, 'day' => $start->toDateString()],
                [
                    'samples' => $rows->count(),
                    'avg_loss_pct' => round($rows->avg('loss_pct'), 2),
                    'max_loss_pct' => $rows->max('loss_pct'),
                    'samples_with_loss' => $rows->where('loss_pct', '>', 0)->count(),
                    'avg_median_ms' => $medians ? round(array_sum($medians) / count($medians), 3) : null,
                    'p95_median_ms' => $medians ? $this->percentile($medians, 95) : null,
                    'max_ms' => $rows->max('max_ms'),
                    'avg_jitter_ms' => $rows->whereNotNull('jitter_ms')->avg('jitter_ms'),
                    'samples_down' => $rows->where('received', 0)->count(),
                ],
            );
            $count++;
        }

        return $count;
    }

    public function prune(): int
    {
        return QualitySample::where('measured_at', '<', now()->subDays(self::RETENTION_DAYS))->delete();
    }

    // ------------------------------------------------------------------ data grafik

    /**
     * Data grafik smoke. Untuk rentang panjang sampel digabung per bucket (rata-rata persentil, loss rata-rata).
     *
     * @return array{from:CarbonInterface, to:CarbonInterface, bucket:int, points:array<int, array<string, float|int|null>>}
     */
    public function series(QualityTarget $target, string $range): array
    {
        [$seconds, $bucket] = match ($range) {
            '3h' => [3 * 3600, self::INTERVAL_SECONDS],
            '10d' => [10 * 86400, 3600],
            '30d' => [30 * 86400, 3 * 3600],
            default => [30 * 3600, self::INTERVAL_SECONDS], // 30h
        };
        $to = now();
        $from = $to->copy()->subSeconds($seconds);

        $query = QualitySample::where('target_id', $target->id)->whereBetween('measured_at', [$from, $to]);
        if ($bucket === self::INTERVAL_SECONDS) {
            $points = $query->orderBy('measured_at')->get()->map(fn (QualitySample $s) => [
                't' => $s->measured_at->getTimestamp(), 'loss' => $s->loss_pct,
                'p10' => $s->p10_ms, 'p25' => $s->p25_ms, 'median' => $s->median_ms, 'p75' => $s->p75_ms, 'p90' => $s->p90_ms,
                'min' => $s->min_ms, 'max' => $s->max_ms, 'jitter' => $s->jitter_ms, 'down' => $s->received === 0 ? 1 : 0,
            ])->all();
        } else {
            $points = $query->selectRaw('FLOOR(UNIX_TIMESTAMP(measured_at) / ?) * ? AS t, AVG(loss_pct) AS loss, AVG(p10_ms) AS p10, AVG(p25_ms) AS p25,
                    AVG(median_ms) AS median, AVG(p75_ms) AS p75, AVG(p90_ms) AS p90, MIN(min_ms) AS min_v, MAX(max_ms) AS max_v, AVG(jitter_ms) AS jitter, SUM(received = 0) AS down', [$bucket, $bucket])
                ->groupBy('t')->orderBy('t')->get()
                ->map(fn ($r) => array_map(fn ($v) => $v === null ? null : (float) $v, (array) $r->getAttributes()))
                ->map(fn ($r) => array_merge($r, ['t' => (int) $r['t'], 'min' => $r['min_v'], 'max' => $r['max_v'], 'down' => (int) $r['down']]))->all();
        }

        return ['from' => $from, 'to' => $to, 'bucket' => $bucket, 'points' => $points];
    }

    /**
     * Ringkasan periode (untuk tabel & laporan).
     *
     * @return array{samples:int, avg_loss:?float, avg_median:?float, p95_median:?float, avg_jitter:?float, availability:?float}
     */
    public function summary(QualityTarget $target, CarbonInterface $from): array
    {
        $rows = QualitySample::where('target_id', $target->id)->where('measured_at', '>=', $from)
            ->get(['loss_pct', 'median_ms', 'jitter_ms', 'received']);
        if ($rows->isEmpty()) {
            return ['samples' => 0, 'avg_loss' => null, 'avg_median' => null, 'p95_median' => null, 'avg_jitter' => null, 'availability' => null];
        }
        $medians = $rows->pluck('median_ms')->filter(fn ($v) => $v !== null)->sort()->values()->all();

        return [
            'samples' => $rows->count(),
            'avg_loss' => round($rows->avg('loss_pct'), 2),
            'avg_median' => $medians ? round(array_sum($medians) / count($medians), 2) : null,
            'p95_median' => $medians ? round($this->percentile($medians, 95), 2) : null,
            'avg_jitter' => $rows->whereNotNull('jitter_ms')->isNotEmpty() ? round($rows->whereNotNull('jitter_ms')->avg('jitter_ms'), 2) : null,
            // Ketersediaan jalur = persentase sampel yang tidak loss 100%
            'availability' => round(100 * $rows->where('received', '>', 0)->count() / $rows->count(), 2),
        ];
    }
}
