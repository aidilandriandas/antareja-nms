<?php

namespace Antareja\Services;

use Antareja\Models\Anomaly;
use Antareja\Models\CapacityItem;
use Antareja\Models\QualitySample;
use Antareja\Models\QualityTarget;
use Antareja\Support\RobustStats as RS;
use App\Models\Device;
use App\Models\Port;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Deteksi anomali statistik — berjalan offline, tanpa model AI.
 *
 * Nilai 30 menit terakhir dibandingkan dengan baseline jam yang sama (±1 jam) dari hari-hari sebelumnya
 * memakai median & MAD (robust z-score), sehingga pola harian shift tambang tidak dianggap anomali.
 *  - Quality Probe: latency median naik, packet loss naik (link putus total sudah ditangani status "down").
 *  - Uplink yang dipantau Capacity Planning: traffic turun drastis (mis. stream CCTV berhenti) atau melonjak.
 *  - Flapping: perangkat down ≥ 3 kali dalam 6 jam.
 */
class AnomalyDetector
{
    public const MIN_BASELINE = 24;

    public function __construct(private readonly RrdReader $rrd)
    {
    }

    /** @return array{open:int, new:int, closed:int} */
    public function run(): array
    {
        $found = array_merge($this->quality(), $this->traffic(), $this->flapping());
        $now = now();
        $stats = ['open' => count($found), 'new' => 0, 'closed' => 0];
        $seen = [];
        foreach ($found as $f) {
            $seen[$f['kind'] . '|' . $f['ref']] = true;
            $a = Anomaly::where('kind', $f['kind'])->where('ref', $f['ref'])->where('status', 'open')->first();
            if ($a) {
                $a->update(['value' => $f['value'], 'baseline' => $f['baseline'], 'score' => $f['score'], 'last_seen_at' => $now, 'label' => $f['label']]);
            } else {
                Anomaly::create($f + ['status' => 'open', 'detected_at' => $now, 'last_seen_at' => $now]);
                $stats['new']++;
            }
        }
        foreach (Anomaly::where('status', 'open')->get() as $a) {
            if (! isset($seen[$a->kind . '|' . $a->ref])) {
                $a->update(['status' => 'closed', 'closed_at' => $now]);
                $stats['closed']++;
            }
        }

        return $stats;
    }

    /** Jam lokal (0-23) yang dianggap "jam yang sama": jam ini ±1. */
    private function hourWindow(): array
    {
        $h = (int) CarbonImmutable::now(config('app.timezone'))->format('G');

        return [($h + 23) % 24, $h, ($h + 1) % 24];
    }

    private function quality(): array
    {
        $out = [];
        $tz = config('app.timezone');
        $hours = $this->hourWindow();
        foreach (QualityTarget::where('enabled', true)->get() as $t) {
            $recent = QualitySample::where('target_id', $t->id)->where('measured_at', '>=', now()->subMinutes(30))->get(['median_ms', 'loss_pct']);
            if ($recent->count() < 2) {
                continue;
            }
            $base = QualitySample::where('target_id', $t->id)
                ->whereBetween('measured_at', [now()->subDays(14), now()->subHours(2)])->get(['measured_at', 'median_ms', 'loss_pct'])
                ->filter(fn ($s) => in_array((int) $s->measured_at->copy()->setTimezone($tz)->format('G'), $hours, true));
            if ($base->count() < self::MIN_BASELINE) {
                continue;
            }

            // Latency
            $x = RS::median($recent->pluck('median_ms')->all());
            $bv = $base->pluck('median_ms')->filter(fn ($v) => $v !== null)->map(fn ($v) => (float) $v)->all();
            $bm = RS::median($bv);
            if ($x !== null && $bm !== null) {
                $z = RS::z($x, $bm, RS::mad($bv, $bm), max(0.1 * $bm, 1.0));
                if ($z >= 6 && $x - $bm >= 5) {
                    $out[] = $this->f('latency', "quality:{$t->id}", $t->device_id, "{$t->name}: latency {$this->n($x)} ms (normal ±{$this->n($bm)} ms)", $x, $bm, $z, 'ms');
                }
            }

            // Packet loss (di bawah 100% — 100% = link putus, sudah ditangani Quality Probe)
            $x = (float) $recent->avg('loss_pct');
            $lv = $base->pluck('loss_pct')->map(fn ($v) => (float) $v)->all();
            $lm = RS::median($lv);
            $z = RS::z($x, $lm, RS::mad($lv, $lm), 2.0);
            if ($x < 100 && $z >= 6 && $x - $lm >= 5) {
                $out[] = $this->f('loss', "quality:{$t->id}", $t->device_id, "{$t->name}: loss {$this->n($x)}% (normal ±{$this->n($lm)}%)", $x, $lm, $z, '%');
            }
        }

        return $out;
    }

    private function traffic(): array
    {
        $out = [];
        $tz = config('app.timezone');
        $hours = $this->hourWindow();
        foreach (CapacityItem::where('type', 'port')->where('enabled', true)->whereNotNull('capacity')->get() as $item) {
            $port = Port::with('device')->find($item->ref_id);
            if (! $port?->device || $port->device->disabled || ! $port->device->status) {
                continue; // perangkat down sudah jadi problem tersendiri
            }
            // 6 hari: RRA 5 menit LibreNMS menyimpan tepat 7 hari; meminta lebih membuat rrdtool memakai resolusi 30 menit
            $rows = $this->rrd->fetch($port->device->hostname, ['port-id' . $port->port_id], 'AVERAGE', 300, time() - 6 * 86400, time());
            $util = [];
            foreach ($rows as $ts => $r) {
                if (($r['INOCTETS'] ?? null) === null && ($r['OUTOCTETS'] ?? null) === null) {
                    continue;
                }
                $util[$ts] = max($r['INOCTETS'] ?? 0, $r['OUTOCTETS'] ?? 0) * 8 / $item->capacity * 100;
            }
            $cut = time() - 1800;
            $recent = array_filter($util, fn ($ts) => $ts > $cut, ARRAY_FILTER_USE_KEY);
            $base = array_filter($util, fn ($ts) => $ts < time() - 7200
                && in_array((int) CarbonImmutable::createFromTimestamp($ts, $tz)->format('G'), $hours, true), ARRAY_FILTER_USE_KEY);
            if (count($recent) < 3 || count($base) < self::MIN_BASELINE) {
                continue;
            }
            // Median 30 menit: tahan terhadap satu titik ekstrem, dan menandai penurunan yang sudah ≥ separuh jendela
            $x = RS::median(array_values($recent));
            $bm = RS::median(array_values($base));
            $z = RS::z($x, $bm, RS::mad(array_values($base), $bm), max(0.1 * $bm, 1.0));
            if ($bm >= 5 && $x < $bm * 0.3 && $z <= -4) {
                $out[] = $this->f('traffic_drop', "capacity:{$item->id}", $item->device_id, "{$item->label}: traffic {$this->n($x)}% (normal ±{$this->n($bm)}%)", $x, $bm, $z, '%');
            } elseif ($z >= 6 && $x - $bm >= 20) {
                $out[] = $this->f('traffic_spike', "capacity:{$item->id}", $item->device_id, "{$item->label}: traffic {$this->n($x)}% (normal ±{$this->n($bm)}%)", $x, $bm, $z, '%');
            }
        }

        return $out;
    }

    private function flapping(): array
    {
        $out = [];
        DB::table('device_outages')->where('going_down', '>=', time() - 6 * 3600)
            ->selectRaw('device_id, COUNT(*) AS n')->groupBy('device_id')->having('n', '>=', 3)->get()
            ->each(function ($r) use (&$out): void {
                $d = Device::find($r->device_id);
                if ($d && ! $d->disabled) {
                    $out[] = $this->f('flapping', "device:{$d->device_id}", $d->device_id, "{$d->displayName()}: {$r->n}× down dalam 6 jam", (float) $r->n, null, null, '×');
                }
            });

        return $out;
    }

    private function f(string $kind, string $ref, ?int $deviceId, string $label, ?float $value, ?float $baseline, ?float $score, string $unit): array
    {
        return ['kind' => $kind, 'ref' => $ref, 'device_id' => $deviceId, 'label' => mb_substr($label, 0, 255),
            'value' => $value === null ? null : round($value, 3), 'baseline' => $baseline === null ? null : round($baseline, 3),
            'score' => $score === null ? null : round($score, 2), 'unit' => $unit];
    }

    private function n(float $v): string
    {
        return number_format($v, $v >= 10 ? 0 : 1, ',', '.');
    }
}
