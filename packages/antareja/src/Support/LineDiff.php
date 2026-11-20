<?php

namespace Antareja\Support;

/**
 * Diff baris sederhana (LCS) dengan pemangkasan awalan/akhiran yang sama — cukup untuk konfigurasi perangkat
 * (ribuan baris, perubahan biasanya sedikit).
 */
class LineDiff
{
    /**
     * @return array<int, array{0:string, 1:string, 2:?int, 3:?int}> [op (' ', '+', '-'), baris, no lama, no baru]
     */
    public static function diff(string $old, string $new): array
    {
        $a = $old === '' ? [] : explode("\n", rtrim($old, "\n"));
        $b = $new === '' ? [] : explode("\n", rtrim($new, "\n"));
        $n = count($a);
        $m = count($b);

        $pre = 0;
        while ($pre < $n && $pre < $m && $a[$pre] === $b[$pre]) {
            $pre++;
        }
        $suf = 0;
        while ($suf < $n - $pre && $suf < $m - $pre && $a[$n - 1 - $suf] === $b[$m - 1 - $suf]) {
            $suf++;
        }

        $ops = [];
        for ($i = 0; $i < $pre; $i++) {
            $ops[] = [' ', $a[$i], $i + 1, $i + 1];
        }

        $ma = array_slice($a, $pre, $n - $pre - $suf);
        $mb = array_slice($b, $pre, $m - $pre - $suf);
        $x = count($ma);
        $y = count($mb);

        if ($x * $y > 1_000_000) {
            // Terlalu besar untuk LCS: tampilkan sebagai hapus-semua lalu tambah-semua
            foreach ($ma as $k => $l) {
                $ops[] = ['-', $l, $pre + $k + 1, null];
            }
            foreach ($mb as $k => $l) {
                $ops[] = ['+', $l, null, $pre + $k + 1];
            }
        } else {
            // Tabel LCS (dari belakang), baris per baris agar hemat memori
            $L = array_fill(0, $x + 1, array_fill(0, $y + 1, 0));
            for ($i = $x - 1; $i >= 0; $i--) {
                for ($j = $y - 1; $j >= 0; $j--) {
                    $L[$i][$j] = $ma[$i] === $mb[$j] ? $L[$i + 1][$j + 1] + 1 : max($L[$i + 1][$j], $L[$i][$j + 1]);
                }
            }
            $i = $j = 0;
            while ($i < $x || $j < $y) {
                if ($i < $x && $j < $y && $ma[$i] === $mb[$j]) {
                    $ops[] = [' ', $ma[$i], $pre + $i + 1, $pre + $j + 1];
                    $i++;
                    $j++;
                } elseif ($j < $y && ($i >= $x || $L[$i][$j + 1] >= $L[$i + 1][$j])) {
                    $ops[] = ['+', $mb[$j], null, $pre + $j + 1];
                    $j++;
                } else {
                    $ops[] = ['-', $ma[$i], $pre + $i + 1, null];
                    $i++;
                }
            }
        }

        for ($k = $suf; $k > 0; $k--) {
            $ops[] = [' ', $a[$n - $k], $n - $k + 1, $m - $k + 1];
        }

        return $ops;
    }

    /** @return array{added:int, removed:int} */
    public static function stats(array $ops): array
    {
        $added = $removed = 0;
        foreach ($ops as $o) {
            $added += $o[0] === '+' ? 1 : 0;
            $removed += $o[0] === '-' ? 1 : 0;
        }

        return ['added' => $added, 'removed' => $removed];
    }

    /**
     * Kelompokkan menjadi hunk dengan $context baris konteks.
     *
     * @return array<int, array<int, array>>
     */
    public static function hunks(array $ops, int $context = 3): array
    {
        $keep = [];
        foreach ($ops as $k => $o) {
            if ($o[0] !== ' ') {
                for ($c = max(0, $k - $context); $c <= min(count($ops) - 1, $k + $context); $c++) {
                    $keep[$c] = true;
                }
            }
        }
        $hunks = [];
        $cur = [];
        $last = null;
        foreach (array_keys($keep) as $k) {
            if ($last !== null && $k !== $last + 1) {
                $hunks[] = $cur;
                $cur = [];
            }
            $cur[] = $ops[$k];
            $last = $k;
        }
        if ($cur) {
            $hunks[] = $cur;
        }

        return $hunks;
    }
}
