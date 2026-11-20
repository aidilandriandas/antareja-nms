<?php

namespace Antareja\Services;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\PermissionDeniedException;
use Anthropic\Core\Exceptions\RateLimitException;

/**
 * Asisten AI opsional (Claude API) untuk menulis DRAF teks: ringkasan incident, dugaan penyebab, laporan serah terima shift.
 *
 * Batasan tegas:
 *  - Hanya menghasilkan teks untuk dibaca engineer. Tidak ada tool, tidak ada akses ke perangkat, tidak pernah
 *    mengeksekusi perubahan konfigurasi; keluaran tidak pernah mengisi root cause / status secara otomatis.
 *  - Kunci API hanya di server (env ANTAREJA_AI_KEY) — tidak pernah dikirim ke browser.
 *  - Data yang dikirim disusun ANTAREJA (lihat AiContextBuilder): tanpa konfigurasi perangkat, kredensial, atau email;
 *    alamat IP & MAC disamarkan secara bawaan. Konteks yang dikirim disimpan agar bisa diperiksa.
 */
class AiAssistant
{
    public const FALLBACK_BETA = 'server-side-fallback-2026-07-01';

    public function enabled(): bool
    {
        return filled(config('antareja.ai.key')) && class_exists(Client::class);
    }

    public function model(): string
    {
        return (string) config('antareja.ai.model', 'claude-opus-5-5');
    }

    public function unavailableReason(): ?string
    {
        if (! class_exists(Client::class)) {
            return 'Library Claude API (anthropic-ai/sdk) belum terpasang di image ini — jalankan ./antareja.sh update.';
        }
        if (blank(config('antareja.ai.key'))) {
            return 'Asisten AI belum diaktifkan. Isi ANTAREJA_AI_KEY di docker/.env lalu jalankan ./antareja.sh restart (butuh internet ke api.anthropic.com).';
        }

        return null;
    }

    /**
     * @return array{text: string, model: string, fallback: bool, input_tokens: ?int, output_tokens: ?int}
     *
     * @throws AiUnavailable
     */
    public function write(string $system, string $context, string $task, int $maxTokens = 8000): array
    {
        if ($reason = $this->unavailableReason()) {
            throw new AiUnavailable($reason);
        }

        $client = new Client(apiKey: (string) config('antareja.ai.key'), baseUrl: config('antareja.ai.base_url') ?: null);

        try {
            // Server-side fallback: bila model utama menolak (refusal), API menjalankan ulang di model cadangan.
            $message = $client->beta->messages->create(
                maxTokens: $maxTokens,
                messages: [['role' => 'user', 'content' => "<data>\n{$context}\n</data>\n\n{$task}"]],
                model: $this->model(),
                system: $system,
                outputConfig: ['effort' => 'medium'],
                fallbacks: 'default',
                betas: [self::FALLBACK_BETA],
                requestOptions: ['timeout' => 120.0, 'maxRetries' => 2],
            );
        } catch (AuthenticationException|PermissionDeniedException) {
            throw new AiUnavailable('Kunci API ditolak. Periksa ANTAREJA_AI_KEY.');
        } catch (RateLimitException) {
            throw new AiUnavailable('Batas pemakaian API tercapai. Coba lagi beberapa menit lagi.');
        } catch (APIConnectionException) {
            throw new AiUnavailable('Tidak bisa terhubung ke api.anthropic.com. Periksa koneksi internet / proxy server ANTAREJA.');
        } catch (APIStatusException $e) {
            throw new AiUnavailable('Layanan AI mengembalikan galat (' . ($e->type?->value ?? 'HTTP') . '). Coba lagi nanti.');
        }

        if ($message->stopReason === 'refusal') {
            throw new AiUnavailable('Model menolak menulis draf untuk data ini.');
        }

        $text = '';
        $fallback = false;
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            } elseif ($block->type === 'fallback') {
                $fallback = true;
            }
        }
        if (trim($text) === '') {
            throw new AiUnavailable('Model tidak mengembalikan teks.' . ($message->stopReason === 'max_tokens' ? ' Keluaran terpotong.' : ''));
        }

        return [
            'text' => trim($text),
            'model' => (string) ($message->model ?? $this->model()),
            'fallback' => $fallback,
            'input_tokens' => $message->usage->inputTokens ?? null,
            'output_tokens' => $message->usage->outputTokens ?? null,
        ];
    }

    /** Samarkan IPv4, IPv6, dan MAC bila diaktifkan (bawaan aktif). */
    public static function redact(string $text): string
    {
        if (! config('antareja.ai.redact', true)) {
            return $text;
        }
        $map = [];
        $sub = function (string $prefix) use (&$map) {
            return function ($m) use ($prefix, &$map) {
                $key = $prefix . $m[0];
                $map[$key] ??= $prefix . '-' . (count(array_filter(array_keys($map), fn ($k) => str_starts_with($k, $prefix))) + 1);

                return '[' . $map[$key] . ']';
            };
        };
        $text = preg_replace_callback('/\b(?:[0-9a-f]{2}[:-]){5}[0-9a-f]{2}\b/i', $sub('MAC'), $text);
        $text = preg_replace_callback('/\b(?:(?:25[0-5]|2[0-4]\d|1?\d?\d)\.){3}(?:25[0-5]|2[0-4]\d|1?\d?\d)(?:\/\d{1,2})?\b/', $sub('IP'), $text);
        $text = preg_replace_callback('/\b(?:[0-9a-f]{1,4}:){2,7}[0-9a-f]{1,4}\b/i', $sub('IP6'), $text);

        return $text;
    }
}
