<?php

namespace Antareja\Services;

use Antareja\Models\CctvStream;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Klien gateway video internal (go2rtc, container "go2rtc" di jaringan Docker internal).
 *
 * - Stream didaftarkan saat dibutuhkan (PUT /api/streams) dan hanya ditarik dari NVR/kamera ketika ada yang menonton
 *   atau saat cek kesehatan.
 * - API gateway TIDAK diekspos ke pengguna (GET /api/streams menampilkan URL berkredensial). Browser hanya mendapat
 *   WebSocket video lewat Nginx setelah lolos pemeriksaan hak akses ANTAREJA.
 */
class VideoGateway
{
    public function url(): string
    {
        return rtrim((string) config('antareja.cctv.gateway', 'http://go2rtc:1984'), '/');
    }

    /** Pastikan stream terdaftar di gateway; return nama stream. */
    public function ensure(CctvStream $stream, bool $main = false): string
    {
        $stream->loadMissing('credential');
        $name = $stream->gatewayName($main);
        // Gateway kehilangan daftar stream saat restart; cache singkat agar tidak PUT setiap request
        Cache::remember("antareja:gw:{$name}", 300, function () use ($stream, $name, $main) {
            $r = Http::timeout(5)->put($this->url() . '/api/streams?' . http_build_query(['name' => $name, 'src' => $stream->rtspUrl($main)]));
            if (! $r->successful()) {
                throw new \RuntimeException('Gateway video menolak pendaftaran stream (HTTP ' . $r->status() . ').');
            }

            return true;
        });

        return $name;
    }

    /**
     * Ambil satu frame JPEG. Gateway membalas 200 dengan isi kosong bila gagal (mis. password salah), jadi isi divalidasi.
     *
     * @throws \RuntimeException
     */
    public function frame(CctvStream $stream, int $width = 640): string
    {
        $name = $this->ensure($stream);
        try {
            $r = Http::timeout(25)->get($this->url() . '/api/frame.jpeg', ['src' => $name, 'width' => $width]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Gateway video tidak bisa dihubungi.');
        }
        $body = $r->body();
        if (! $r->successful() || strlen($body) < 100 || ! str_starts_with($body, "\xFF\xD8")) {
            Cache::forget('antareja:gw:' . $name); // daftar ulang pada percobaan berikutnya
            throw new \RuntimeException('Tidak ada gambar dari ' . $stream->host . ' channel ' . $stream->channel . ' — periksa user/password, nomor channel, port RTSP 554, dan apakah kamera merekam.');
        }

        return $body;
    }

    /** Tes koneksi gateway (untuk halaman pengaturan & health). */
    public function reachable(): bool
    {
        try {
            return Http::timeout(3)->get($this->url() . '/api')->successful();
        } catch (\Throwable) {
            return false;
        }
    }
}
