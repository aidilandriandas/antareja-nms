<?php

namespace Antareja\Support;

/**
 * Operasi himpunan interval waktu [mulai, selesai) dalam detik Unix. Dipakai perhitungan SLA.
 */
class Intervals
{
    /**
     * Urutkan & gabungkan interval yang bertumpuk/bersambung.
     *
     * @param  array<int, array{0:int,1:int}>  $list
     * @return array<int, array{0:int,1:int}>
     */
    public static function merge(array $list): array
    {
        $list = array_values(array_filter($list, fn ($i) => $i[1] > $i[0]));
        usort($list, fn ($a, $b) => $a[0] <=> $b[0]);
        $out = [];
        foreach ($list as [$s, $e]) {
            $last = count($out) - 1;
            if ($last >= 0 && $s <= $out[$last][1]) {
                $out[$last][1] = max($out[$last][1], $e);
            } else {
                $out[] = [$s, $e];
            }
        }

        return $out;
    }

    public static function clip(array $list, int $from, int $to): array
    {
        $out = [];
        foreach ($list as [$s, $e]) {
            $s = max($s, $from);
            $e = min($e, $to);
            if ($e > $s) {
                $out[] = [$s, $e];
            }
        }

        return self::merge($out);
    }

    /** $a dikurangi $b. */
    public static function subtract(array $a, array $b): array
    {
        $a = self::merge($a);
        $b = self::merge($b);
        $out = [];
        foreach ($a as [$s, $e]) {
            $cur = $s;
            foreach ($b as [$bs, $be]) {
                if ($be <= $cur || $bs >= $e) {
                    continue;
                }
                if ($bs > $cur) {
                    $out[] = [$cur, $bs];
                }
                $cur = max($cur, $be);
                if ($cur >= $e) {
                    break;
                }
            }
            if ($cur < $e) {
                $out[] = [$cur, $e];
            }
        }

        return $out;
    }

    public static function total(array $list): int
    {
        return array_sum(array_map(fn ($i) => $i[1] - $i[0], self::merge($list)));
    }

    /**
     * Gabungkan interval down beberapa anggota: hasil "down" ketika jumlah anggota yang down ≥ $required.
     * required = 1 → union (seri), required = n → irisan (redundan).
     *
     * @param  array<int, array<int, array{0:int,1:int}>>  $members  interval down per anggota
     */
    public static function combine(array $members, int $required, int $from, int $to): array
    {
        if ($members === []) {
            return [];
        }
        if ($required <= 0) {
            return $to > $from ? [[$from, $to]] : [];
        }
        if ($required > count($members)) {
            return [];
        }

        $events = [];
        foreach ($members as $list) {
            foreach (self::merge($list) as [$s, $e]) {
                $events[] = [$s, 1];
                $events[] = [$e, -1];
            }
        }
        // Pada waktu yang sama proses "selesai" lebih dulu agar interval bersambung tidak salah hitung
        usort($events, fn ($a, $b) => $a[0] <=> $b[0] ?: $a[1] <=> $b[1]);

        $out = [];
        $count = 0;
        $start = null;
        foreach ($events as [$t, $d]) {
            $count += $d;
            if ($start === null && $count >= $required) {
                $start = $t;
            } elseif ($start !== null && $count < $required) {
                $out[] = [$start, $t];
                $start = null;
            }
        }

        return self::merge($out);
    }
}
