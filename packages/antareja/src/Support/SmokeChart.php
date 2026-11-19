<?php

namespace Antareja\Support;

use Carbon\CarbonInterface;

/**
 * Grafik "smoke" ala SmokePing, dirender sebagai SVG di server (tanpa library JavaScript, aman untuk jaringan offline).
 *
 *  - pita tipis  : p10–p90 sebaran RTT
 *  - pita tebal  : p25–p75 sebaran RTT
 *  - garis kecil : median, warnanya menunjukkan packet loss
 *  - kolom merah : sampel loss 100% (jalur putus)
 */
class SmokeChart
{
    public const LOSS_COLORS = [
        [0, '#16a34a', '0%'],
        [5, '#0ea5e9', '≤5%'],
        [10, '#2563eb', '≤10%'],
        [20, '#7c3aed', '≤20%'],
        [50, '#db2777', '≤50%'],
        [99.99, '#ea580c', '<100%'],
        [100, '#dc2626', '100%'],
    ];

    public static function lossColor(?float $loss): string
    {
        foreach (self::LOSS_COLORS as [$max, $color]) {
            if (($loss ?? 0) <= $max) {
                return $color;
            }
        }

        return '#dc2626';
    }

    /**
     * @param  array{from:CarbonInterface, to:CarbonInterface, bucket:int, points:array}  $series
     */
    public static function render(array $series, ?float $warnMs = null, int $width = 960, int $height = 240): string
    {
        [$ml, $mr, $mt, $mb] = [56, 12, 10, 26];
        $pw = $width - $ml - $mr;
        $ph = $height - $mt - $mb;
        $from = $series['from']->getTimestamp();
        $to = $series['to']->getTimestamp();
        $span = max(1, $to - $from);
        $points = $series['points'];

        $peak = 0.0;
        foreach ($points as $p) {
            $peak = max($peak, (float) ($p['p90'] ?? $p['median'] ?? 0));
        }
        $yMax = self::niceCeil(max(1.0, $peak * 1.15));
        $x = fn (int $t) => $ml + ($t - $from) / $span * $pw;
        $y = fn (float $v) => $mt + $ph - min($v, $yMax) / $yMax * $ph;
        $barW = max(1.0, $pw * $series['bucket'] / $span);

        $svg = [];
        $svg[] = sprintf('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" class="tw:w-full tw:h-auto" role="img" aria-label="Grafik latency dan packet loss">', $width, $height);

        // grid & label sumbu Y
        $ticks = 4;
        for ($i = 0; $i <= $ticks; $i++) {
            $v = $yMax * $i / $ticks;
            $yy = $y($v);
            $svg[] = sprintf('<line x1="%d" x2="%d" y1="%.1f" y2="%.1f" stroke="currentColor" stroke-opacity="0.12"/>', $ml, $ml + $pw, $yy, $yy);
            $svg[] = sprintf('<text x="%d" y="%.1f" font-size="10" text-anchor="end" fill="currentColor" fill-opacity="0.65">%s ms</text>', $ml - 6, $yy + 3, self::fmt($v));
        }

        // label sumbu X
        $step = self::timeStep($span);
        $format = $span > 2 * 86400 ? 'd M' : 'H:i';
        for ($t = (int) (ceil($from / $step) * $step); $t <= $to; $t += $step) {
            $xx = $x($t);
            $label = \Carbon\Carbon::createFromTimestamp($t, config('app.timezone'))->format($format);
            $svg[] = sprintf('<line x1="%.1f" x2="%.1f" y1="%d" y2="%d" stroke="currentColor" stroke-opacity="0.08"/>', $xx, $xx, $mt, $mt + $ph);
            $svg[] = sprintf('<text x="%.1f" y="%d" font-size="10" text-anchor="middle" fill="currentColor" fill-opacity="0.65">%s</text>', $xx, $height - 8, $label);
        }

        // data
        foreach ($points as $p) {
            $xx = $x((int) $p['t']);
            if ($xx < $ml || $xx > $ml + $pw) {
                continue;
            }
            $loss = (float) ($p['loss'] ?? 0);
            // Bucket (rentang panjang) yang berisi sampel putus: penanda merah di atas area grafik
            if (($p['down'] ?? 0) > 0 && $p['median'] !== null) {
                $svg[] = sprintf('<rect x="%.1f" y="%d" width="%.1f" height="4" fill="#dc2626"><title>%s: %d siklus putus (loss 100%%)</title></rect>',
                    $xx, $mt, max(1.5, $barW), self::time($p['t']), $p['down']);
            }
            if ($p['median'] === null) {
                if ($loss >= 100) {
                    $svg[] = sprintf('<rect x="%.1f" y="%d" width="%.1f" height="%d" fill="#dc2626" fill-opacity="0.22"><title>%s: loss 100%%</title></rect>',
                        $xx, $mt, $barW, $ph, self::time($p['t']));
                }
                continue;
            }
            if ($p['p10'] !== null && $p['p90'] !== null) {
                $svg[] = sprintf('<rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" fill="currentColor" fill-opacity="0.13"/>',
                    $xx, $y($p['p90']), $barW, max(0.5, $y($p['p10']) - $y($p['p90'])));
            }
            if ($p['p25'] !== null && $p['p75'] !== null) {
                $svg[] = sprintf('<rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" fill="currentColor" fill-opacity="0.28"/>',
                    $xx, $y($p['p75']), $barW, max(0.5, $y($p['p25']) - $y($p['p75'])));
            }
            $svg[] = sprintf('<rect x="%.1f" y="%.1f" width="%.1f" height="2.4" fill="%s"><title>%s — median %s ms, loss %s%%, jitter %s ms</title></rect>',
                $xx, $y($p['median']) - 1.2, max(1.5, $barW), self::lossColor($loss), self::time($p['t']),
                self::fmt($p['median']), self::fmt($loss), self::fmt($p['jitter'] ?? 0));
        }

        if ($warnMs !== null && $warnMs < $yMax) {
            $svg[] = sprintf('<line x1="%d" x2="%d" y1="%.1f" y2="%.1f" stroke="#d97706" stroke-width="1" stroke-dasharray="4 3"/>', $ml, $ml + $pw, $y($warnMs), $y($warnMs));
            $svg[] = sprintf('<text x="%d" y="%.1f" font-size="9" text-anchor="end" fill="#d97706">batas %s ms</text>', $ml + $pw, $y($warnMs) - 3, self::fmt($warnMs));
        }

        if ($points === []) {
            $svg[] = sprintf('<text x="%.1f" y="%.1f" font-size="13" text-anchor="middle" fill="currentColor" fill-opacity="0.6">Belum ada data pada rentang ini</text>', $ml + $pw / 2, $mt + $ph / 2);
        }

        $svg[] = sprintf('<rect x="%d" y="%d" width="%d" height="%d" fill="none" stroke="currentColor" stroke-opacity="0.25"/>', $ml, $mt, $pw, $ph);
        $svg[] = '</svg>';

        return implode('', $svg);
    }

    /**
     * Sparkline median latency (dengan titik merah untuk sampel ber-loss).
     *
     * @param  array<int, array{t:int, median:?float, loss:?float}>  $points
     */
    public static function sparkline(array $points, int $width = 140, int $height = 28): string
    {
        $vals = array_filter(array_column($points, 'median'), fn ($v) => $v !== null);
        if (count($points) < 2) {
            return '<span class="tw:text-xs tw:text-gray-400">—</span>';
        }
        $max = max(1.0, $vals ? max($vals) * 1.1 : 1.0);
        $n = count($points);
        $coords = [];
        $dots = [];
        foreach (array_values($points) as $i => $p) {
            $xx = $i / ($n - 1) * ($width - 2) + 1;
            if ($p['median'] === null) {
                $dots[] = sprintf('<rect x="%.1f" y="0" width="1.5" height="%d" fill="#dc2626" fill-opacity="0.6"/>', $xx, $height);
                continue;
            }
            $yy = $height - 2 - $p['median'] / $max * ($height - 4);
            $coords[] = sprintf('%.1f,%.1f', $xx, $yy);
            if (($p['loss'] ?? 0) > 0) {
                $dots[] = sprintf('<circle cx="%.1f" cy="%.1f" r="1.6" fill="%s"/>', $xx, $yy, self::lossColor($p['loss']));
            }
        }

        return sprintf('<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d"><polyline points="%s" fill="none" stroke="currentColor" stroke-opacity="0.7" stroke-width="1.2"/>%s</svg>',
            $width, $height, $width, $height, implode(' ', $coords), implode('', $dots));
    }

    private static function niceCeil(float $v): float
    {
        $exp = 10 ** floor(log10($v));
        foreach ([1, 2, 2.5, 5, 10] as $m) {
            if ($v <= $m * $exp) {
                return $m * $exp;
            }
        }

        return 10 * $exp;
    }

    private static function timeStep(int $span): int
    {
        return match (true) {
            $span <= 4 * 3600 => 1800,
            $span <= 36 * 3600 => 3 * 3600,
            $span <= 12 * 86400 => 86400,
            default => 3 * 86400,
        };
    }

    private static function fmt(float|int|null $v): string
    {
        if ($v === null) {
            return '—';
        }
        if ((float) $v === 0.0) {
            return '0';
        }

        return $v >= 100 ? number_format($v, 0) : rtrim(rtrim(number_format($v, $v >= 10 ? 1 : 2, '.', ''), '0'), '.');
    }

    private static function time(int|float $t): string
    {
        return \Carbon\Carbon::createFromTimestamp((int) $t, config('app.timezone'))->format('d M H:i');
    }
}
