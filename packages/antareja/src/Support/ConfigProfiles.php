<?php

namespace Antareja\Support;

/**
 * Profil pengambilan konfigurasi per vendor. HANYA perintah baca (show / display / export) —
 * daftar tertutup, tidak bisa diisi bebas dari UI, agar ANTAREJA tidak pernah mengubah perangkat.
 *
 * ignore: baris yang selalu berubah (timestamp, uptime) dan tidak dianggap perubahan konfigurasi.
 */
class ConfigProfiles
{
    public const PROFILES = [
        'cisco_ios' => [
            'label' => 'Cisco IOS / IOS-XE',
            'commands' => ['show running-config'],
            'ignore' => ['/^! (Last configuration change|NVRAM config last updated)/', '/^ntp clock-period/', '/^Building configuration/', '/^Current configuration\s*:/'],
        ],
        'cisco_nxos' => [
            'label' => 'Cisco NX-OS',
            'commands' => ['show running-config'],
            'ignore' => ['/^!Time:/', '/^!Running configuration last done at/'],
        ],
        'ruijie' => [
            'label' => 'Ruijie RGOS',
            'commands' => ['show running-config'],
            'ignore' => ['/^Building configuration/', '/^Current configuration\s*:/'],
        ],
        'aruba_os' => [
            'label' => 'HPE Aruba / ProCurve',
            'commands' => ['show running-config'],
            'ignore' => ['/^; .*Configuration Editor; Created on release/', '/^Running configuration:/'],
        ],
        'huawei_vrp' => [
            'label' => 'Huawei VRP',
            'commands' => ['display current-configuration'],
            'ignore' => ['/^!Software Version/', '/^!Last configuration was/'],
        ],
        'h3c_comware' => [
            'label' => 'H3C / HPE Comware',
            'commands' => ['display current-configuration'],
            'ignore' => [],
        ],
        'mikrotik' => [
            'label' => 'MikroTik RouterOS',
            'commands' => ['/export'],
            'ignore' => ['/^# \S+ \S+ by RouterOS/', '/^# software id/', '/^# \d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2} by RouterOS/'],
        ],
        'juniper_junos' => [
            'label' => 'Juniper Junos',
            'commands' => ['show configuration | display set | no-more'],
            'ignore' => ['/^## Last commit:/'],
        ],
        'fortios' => [
            'label' => 'Fortinet FortiOS',
            'commands' => ['show'],
            'ignore' => ['/^#conf_file_ver=/'],
        ],
        'edgeos' => [
            'label' => 'Ubiquiti EdgeOS / VyOS',
            'commands' => ['cat /config/config.boot'],
            'ignore' => ['/^\/\* Release version:/'],
        ],
    ];

    /** Tanda keluaran gagal (perintah ditolak perangkat). */
    public const ERROR_PATTERNS = ['/^% ?Invalid input/m', '/^% ?Unrecognized command/m', '/^Error: Unrecognized command/m', '/syntax error, expecting/', '/^bad command name/m', '/^Unknown command/m'];

    public static function options(): array
    {
        return array_map(fn ($p) => $p['label'], self::PROFILES);
    }

    /** Normalisasi keluaran: CRLF, spasi di akhir baris, baris yang selalu berubah. */
    public static function normalize(string $profile, string $output): string
    {
        $ignore = self::PROFILES[$profile]['ignore'] ?? [];
        $lines = preg_split('/\r?\n|\r/', str_replace("\x00", '', $output));
        $out = [];
        foreach ($lines as $line) {
            $line = rtrim($line);
            foreach ($ignore as $re) {
                if (preg_match($re, $line)) {
                    continue 2;
                }
            }
            $out[] = $line;
        }
        // buang baris kosong di awal/akhir
        while ($out !== [] && trim($out[0]) === '') {
            array_shift($out);
        }
        while ($out !== [] && trim(end($out)) === '') {
            array_pop($out);
        }

        return implode("\n", $out) . "\n";
    }

    /** Samarkan nilai rahasia (password, community, key) untuk tampilan. */
    public static function mask(string $text): string
    {
        return preg_replace(
            '/\b(password|passwd|secret|community|pre-shared-key|psk|key-string|passphrase|auth(?:entication)?-(?:key|password)|priv(?:acy)?-(?:key|password)|wpa2?-pre-shared-key|cipher)(\s+(?:\d{1,2}\s+)?|\s*=\s*"?)([^\s"]+)/i',
            '$1$2******',
            $text,
        ) ?? $text;
    }
}
