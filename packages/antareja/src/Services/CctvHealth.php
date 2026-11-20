<?php

namespace Antareja\Services;

use Antareja\Models\CctvStream;
use Illuminate\Support\Facades\Storage;

/**
 * Cek kesehatan gambar CCTV — kamera bisa "online" (ping OK) padahal gambarnya tidak berguna.
 *
 * Dari satu snapshot (lebar 320 px) dihitung, memakai GD di server (tanpa AI, tanpa internet):
 *  - kecerahan rata-rata & kontras (simpangan baku) → gelap / layar polos (tertutup, "No Video")
 *  - ketajaman (rata-rata |Laplacian|) dibanding gambar acuan → buram (debu, lumpur, embun)
 *  - selisih dengan snapshot sebelumnya → beku (2× berturut-turut hampir identik; jam OSD ikut berhenti)
 *  - selisih bentuk dengan gambar acuan (setelah kecerahan dinormalkan) → arah berubah / terhalang
 * Gambar acuan diambil engineer saat kondisi normal (sebaiknya siang hari).
 */
class CctvHealth
{
    public const THUMB_W = 64;

    public const THUMB_H = 36;

    public function __construct(private readonly VideoGateway $gateway)
    {
    }

    /**
     * Cek satu stream: ambil frame, hitung metrik, simpan snapshot terbaru, tentukan status.
     *
     * @return array{health: string, detail: ?string}
     */
    public function check(CctvStream $stream): array
    {
        $now = now();
        try {
            $jpeg = $this->gateway->frame($stream, 320);
        } catch (\Throwable $e) {
            return $this->save($stream, 'error', $e->getMessage(), null, $now);
        }

        $m = self::metrics($jpeg);
        if ($m === null) {
            return $this->save($stream, 'error', 'Gambar tidak bisa dibaca.', null, $now);
        }
        Storage::disk(AttachmentService::DISK)->put("cctv/{$stream->id}/latest.jpg", $jpeg);

        $prev = $stream->metrics;
        $ref = $stream->reference_metrics;
        [$health, $detail] = $this->classify($m, $prev, $ref, $stream);

        return $this->save($stream, $health, $detail, $m, $now);
    }

    /** Jadikan snapshot saat ini sebagai acuan. */
    public function setReference(CctvStream $stream): void
    {
        $jpeg = $this->gateway->frame($stream, 320);
        $m = self::metrics($jpeg) ?? throw new \RuntimeException('Gambar tidak bisa dibaca.');
        Storage::disk(AttachmentService::DISK)->put("cctv/{$stream->id}/reference.jpg", $jpeg);
        $stream->forceFill(['reference_metrics' => $m, 'reference_at' => now()])->saveQuietly();
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    public function classify(array $m, ?array $prev, ?array $ref, CctvStream $stream): array
    {
        if ($m['stddev'] < 4) {
            return ['blank', sprintf('Gambar polos tanpa detail (kontras %.1f) — lensa tertutup, kabel video, atau "No Video".', $m['stddev'])];
        }
        if ($m['mean'] < 20 && $m['stddev'] < 12) {
            return ['dark', sprintf('Gambar sangat gelap (kecerahan %.0f/255) — periksa IR / lampu area.', $m['mean'])];
        }

        // Beku: hampir identik dengan snapshot sebelumnya 2× berturut-turut (jam OSD kamera ikut berhenti)
        $frozen = $prev && isset($prev['thumb']) && self::diff($m['thumb'], $prev['thumb'], false) < 0.25;
        $stream->frozen_count = $frozen ? $stream->frozen_count + 1 : 0;
        if ($stream->frozen_count >= 2) {
            return ['frozen', 'Gambar tidak berubah sejak ' . ($stream->last_checked_at?->copy()->subMinutes(15)->format('H:i') ?? 'cek sebelumnya') . ' — stream macet atau kamera hang.'];
        }

        if ($ref && isset($ref['thumb']) && abs($m['mean'] - $ref['mean']) < 45) {
            if ($ref['sharpness'] > 0 && $m['sharpness'] < 0.35 * $ref['sharpness']) {
                return ['blur', sprintf('Ketajaman %.0f%% dari gambar acuan — lensa kotor, embun, atau fokus bergeser.', $m['sharpness'] / $ref['sharpness'] * 100)];
            }
            $d = self::diff($m['thumb'], $ref['thumb'], true);
            if ($d > 28) {
                return ['moved', sprintf('Pemandangan berbeda jauh dari gambar acuan (selisih %.0f) — kamera bergeser atau terhalang.', $d)];
            }
        }

        return ['ok', null];
    }

    private function save(CctvStream $stream, string $health, ?string $detail, ?array $m, $now): array
    {
        $changed = $stream->health !== $health;
        $stream->forceFill([
            'health' => $health,
            'health_detail' => $detail ? mb_substr($detail, 0, 255) : null,
            'metrics' => $m ?? $stream->metrics,
            'last_checked_at' => $now,
            'health_changed_at' => $changed ? $now : $stream->health_changed_at,
        ])->saveQuietly();

        return ['health' => $health, 'detail' => $detail];
    }

    /**
     * Metrik dari JPEG. thumb = 64×36 piksel abu-abu (base64) untuk perbandingan.
     *
     * @return array{mean: float, stddev: float, sharpness: float, thumb: string, w: int, h: int}|null
     */
    public static function metrics(string $jpeg): ?array
    {
        $img = @imagecreatefromstring($jpeg);
        if (! $img) {
            return null;
        }
        $w = imagesx($img);
        $h = imagesy($img);

        $gray = function ($src, int $tw, int $th): array {
            $t = imagecreatetruecolor($tw, $th);
            imagecopyresampled($t, $src, 0, 0, 0, 0, $tw, $th, imagesx($src), imagesy($src));
            $px = [];
            for ($y = 0; $y < $th; $y++) {
                for ($x = 0; $x < $tw; $x++) {
                    $c = imagecolorat($t, $x, $y);
                    $px[] = (int) round(0.299 * (($c >> 16) & 0xFF) + 0.587 * (($c >> 8) & 0xFF) + 0.114 * ($c & 0xFF));
                }
            }
            imagedestroy($t);

            return $px;
        };

        $thumb = $gray($img, self::THUMB_W, self::THUMB_H);
        $n = count($thumb);
        $mean = array_sum($thumb) / $n;
        $var = 0.0;
        foreach ($thumb as $v) {
            $var += ($v - $mean) ** 2;
        }

        // Ketajaman: rata-rata |Laplacian| pada 160×90
        $sw = 160;
        $sh = 90;
        $s = $gray($img, $sw, $sh);
        $lap = 0.0;
        $cnt = 0;
        for ($y = 1; $y < $sh - 1; $y++) {
            for ($x = 1; $x < $sw - 1; $x++) {
                $i = $y * $sw + $x;
                $lap += abs(4 * $s[$i] - $s[$i - 1] - $s[$i + 1] - $s[$i - $sw] - $s[$i + $sw]);
                $cnt++;
            }
        }
        imagedestroy($img);

        return [
            'mean' => round($mean, 2),
            'stddev' => round(sqrt($var / $n), 2),
            'sharpness' => round($lap / max(1, $cnt), 3),
            'thumb' => base64_encode(pack('C*', ...$thumb)),
            'w' => $w,
            'h' => $h,
        ];
    }

    /** Rata-rata selisih absolut dua thumbnail; $normalize = kurangi rata-rata masing-masing (abaikan perubahan terang/gelap). */
    public static function diff(string $a, string $b, bool $normalize): float
    {
        $x = array_values(unpack('C*', base64_decode($a)));
        $y = array_values(unpack('C*', base64_decode($b)));
        $n = min(count($x), count($y));
        if ($n === 0) {
            return 255.0;
        }
        $mx = $normalize ? array_sum($x) / count($x) : 0;
        $my = $normalize ? array_sum($y) / count($y) : 0;
        $d = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $d += abs(($x[$i] - $mx) - ($y[$i] - $my));
        }

        return $d / $n;
    }
}
