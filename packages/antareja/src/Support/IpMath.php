<?php

namespace Antareja\Support;

use InvalidArgumentException;

/**
 * Utilitas alamat IP untuk IPAM. Penyimpanan memakai biner (inet_pton, 4 atau 16 byte) agar siap IPv6;
 * perhitungan rentang & daftar IP kosong saat ini untuk IPv4.
 */
class IpMath
{
    /**
     * @return array{family:int, network:string, prefix:int, cidr:string}
     */
    public static function parseCidr(string $cidr): array
    {
        $cidr = trim($cidr);
        if (! str_contains($cidr, '/')) {
            throw new InvalidArgumentException('Format CIDR harus alamat/prefix, mis. 10.10.20.0/24');
        }
        [$ip, $prefix] = explode('/', $cidr, 2);
        $bin = @inet_pton($ip);
        if ($bin === false || ! ctype_digit($prefix)) {
            throw new InvalidArgumentException("CIDR tidak valid: {$cidr}");
        }
        $family = strlen($bin) === 4 ? 4 : 6;
        $prefix = (int) $prefix;
        $max = $family === 4 ? 32 : 128;
        if ($prefix < ($family === 4 ? 8 : 16) || $prefix > $max) {
            throw new InvalidArgumentException("Prefix /{$prefix} di luar batas yang didukung");
        }
        $network = self::applyMask($bin, $prefix);

        return ['family' => $family, 'network' => $network, 'prefix' => $prefix, 'cidr' => inet_ntop($network) . '/' . $prefix];
    }

    public static function applyMask(string $bin, int $prefix): string
    {
        $out = '';
        foreach (str_split($bin) as $i => $byte) {
            $bits = max(0, min(8, $prefix - $i * 8));
            $mask = $bits === 0 ? 0 : (0xFF << (8 - $bits)) & 0xFF;
            $out .= chr(ord($byte) & $mask);
        }

        return $out;
    }

    public static function toBinary(string $ip): ?string
    {
        $bin = @inet_pton(trim($ip));

        return $bin === false ? null : $bin;
    }

    public static function toText(?string $bin): ?string
    {
        if ($bin === null || $bin === '') {
            return null;
        }
        $text = @inet_ntop($bin);

        return $text === false ? null : $text;
    }

    public static function family(string $bin): int
    {
        return strlen($bin) === 4 ? 4 : 6;
    }

    public static function contains(string $networkBin, int $prefix, string $ipBin): bool
    {
        return strlen($networkBin) === strlen($ipBin) && self::applyMask($ipBin, $prefix) === $networkBin;
    }

    /** Jumlah alamat yang bisa dipakai host (IPv4: tanpa network & broadcast kecuali /31 dan /32). */
    public static function usableCount(int $family, int $prefix): int|float
    {
        if ($family === 6) {
            return 2 ** min(63, 128 - $prefix); // dibatasi agar tetap angka
        }
        $total = 2 ** (32 - $prefix);

        return $prefix >= 31 ? $total : $total - 2;
    }

    /**
     * Rentang host IPv4 (long) yang bisa dipakai.
     *
     * @return array{0:int,1:int}
     */
    public static function hostRange(string $networkBin, int $prefix): array
    {
        $start = self::v4ToLong($networkBin);
        $size = 2 ** (32 - $prefix);
        if ($prefix >= 31) {
            return [$start, $start + $size - 1];
        }

        return [$start + 1, $start + $size - 2];
    }

    public static function v4ToLong(string $bin): int
    {
        return unpack('N', $bin)[1];
    }

    public static function longToV4(int $long): string
    {
        return pack('N', $long);
    }

    public static function broadcast(string $networkBin, int $prefix): ?string
    {
        if (self::family($networkBin) !== 4 || $prefix >= 31) {
            return null;
        }

        return self::longToV4(self::v4ToLong($networkBin) + 2 ** (32 - $prefix) - 1);
    }

    /** Alamat yang tidak masuk IPAM (loopback, link-local, multicast, tanpa alamat). */
    public static function isIgnored(string $bin): bool
    {
        if (strlen($bin) === 4) {
            $first = ord($bin[0]);

            return $first === 127 || $first === 0 || $first >= 224 || (ord($bin[0]) === 169 && ord($bin[1]) === 254);
        }

        $text = inet_ntop($bin);

        return $text === '::1' || $text === '::' || str_starts_with(strtolower($text), 'fe80:') || str_starts_with(strtolower($text), 'ff');
    }

    public static function normalizeMac(?string $mac): ?string
    {
        $hex = strtolower(preg_replace('/[^0-9a-fA-F]/', '', (string) $mac));

        return strlen($hex) === 12 && $hex !== '000000000000' ? implode(':', str_split($hex, 2)) : null;
    }
}
