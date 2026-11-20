<?php

namespace Antareja\Support;

/** Statistik tahan-outlier untuk deteksi anomali (median & MAD). */
class RobustStats
{
    public static function median(array $v): ?float
    {
        $v = array_values(array_filter($v, fn ($x) => $x !== null));
        $n = count($v);
        if ($n === 0) {
            return null;
        }
        sort($v);
        $m = intdiv($n, 2);

        return $n % 2 ? (float) $v[$m] : ($v[$m - 1] + $v[$m]) / 2;
    }

    /** Median absolute deviation. */
    public static function mad(array $v, ?float $median = null): ?float
    {
        $median ??= self::median($v);

        return $median === null ? null : self::median(array_map(fn ($x) => abs($x - $median), array_filter($v, fn ($x) => $x !== null)));
    }

    /** Robust z-score dengan skala minimum agar baseline yang sangat stabil tidak memicu alarm untuk perubahan kecil. */
    public static function z(float $x, float $median, float $mad, float $minScale): float
    {
        return ($x - $median) / max(1.4826 * $mad, $minScale);
    }
}
