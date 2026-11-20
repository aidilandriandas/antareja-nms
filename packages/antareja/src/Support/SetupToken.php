<?php

namespace Antareja\Support;

use App\Models\User;

/**
 * Kode setup awal ANTAREJA.
 *
 * Selama belum ada satu pun user, halaman /setup terbuka untuk membuat admin pertama. Agar orang lain di jaringan
 * tidak bisa "mendahului" engineer yang memasang, halaman itu meminta kode setup yang hanya bisa dibaca dari
 * server (./antareja.sh setup-token). Kode dihapus begitu admin pertama dibuat.
 */
class SetupToken
{
    /** Huruf/angka yang tidak mudah tertukar saat dibaca dari layar (tanpa 0/O, 1/I/L). */
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public static function path(): string
    {
        return storage_path('app/antareja-setup.token');
    }

    /** Setup awal masih dibutuhkan = belum ada user sama sekali. */
    public static function needed(): bool
    {
        try {
            return ! User::query()->exists();
        } catch (\Throwable) {
            return false; // database belum siap: jangan alihkan ke mana-mana
        }
    }

    /** Kode yang berlaku saat ini (dibuat bila belum ada). */
    public static function current(): string
    {
        $path = self::path();
        $token = is_readable($path) ? trim((string) file_get_contents($path)) : '';
        if (preg_match('/^[A-Z0-9]{4}(-[A-Z0-9]{4}){2}$/', $token)) {
            return $token;
        }

        $groups = [];
        for ($g = 0; $g < 3; $g++) {
            $part = '';
            for ($i = 0; $i < 4; $i++) {
                $part .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
            $groups[] = $part;
        }
        $token = implode('-', $groups);

        @mkdir(dirname($path), 0750, true);
        file_put_contents($path, $token . "\n", LOCK_EX);
        @chmod($path, 0600);

        return $token;
    }

    public static function verify(string $input): bool
    {
        $path = self::path();
        if (! is_readable($path)) {
            return false;
        }
        $expected = trim((string) file_get_contents($path));
        $given = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $input) ?? '');

        return $expected !== '' && hash_equals(str_replace('-', '', $expected), $given);
    }

    public static function clear(): void
    {
        @unlink(self::path());
    }
}
