<?php

namespace Antareja\Services;

use App\Facades\LibrenmsConfig;
use App\Models\Device;
use Illuminate\Support\Arr;
use LibreNMS\Data\Source\Icmp\Fping;

/**
 * Uji koneksi dari server ANTAREJA ke perangkat: resolusi nama, ping (fping — sama dengan penentu status up/down),
 * dan SNMP GET read-only (sysName, sysDescr). Hasil disertai diagnosis berbahasa Indonesia.
 * Community / password tidak pernah dikembalikan ke browser.
 */
class ConnectionTester
{
    public function __construct(private readonly Fping $fping)
    {
    }

    /**
     * @param  array{hostname: string, mode: string, community?: ?string, v3_user?: ?string, v3_auth_pass?: ?string,
     *               v3_auth_algo?: ?string, v3_priv_pass?: ?string, v3_priv_algo?: ?string, port?: ?int}  $p
     */
    public function test(array $p): array
    {
        $host = trim($p['hostname']);
        $out = ['host' => $host, 'ip' => null, 'resolve' => null, 'ping' => null, 'snmp' => null, 'hints' => [], 'ok' => false];

        // 1) Resolusi nama
        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : (($r = gethostbyname($host)) !== $host ? $r : null);
        $out['ip'] = $ip;
        $out['resolve'] = ['ok' => $ip !== null, 'text' => $ip ? ($ip === $host ? 'Alamat IP' : "$host → $ip") : "Nama $host tidak bisa di-resolve (DNS)."];
        if (! $ip) {
            $out['hints'][] = 'Pakai alamat IP langsung, atau daftarkan nama perangkat di DNS yang dipakai server ANTAREJA.';

            return $out;
        }

        // 2) Ping
        try {
            $r = $this->fping->ping($ip);
            $alive = $r->isAlive() || $r->received > 0;
            $out['ping'] = ['ok' => $alive, 'loss' => $r->loss, 'avg' => $alive ? round($r->avg_latency, 1) : null,
                'text' => $alive ? sprintf('Membalas — rata-rata %.1f ms, loss %d%%', $r->avg_latency, $r->loss) : 'Tidak membalas ping (100% loss).'];
        } catch (\Throwable $e) {
            $out['ping'] = ['ok' => false, 'loss' => 100, 'avg' => null, 'text' => 'Ping gagal dijalankan: ' . $e->getMessage()];
        }

        // 3) SNMP
        if ($p['mode'] !== 'ping') {
            $out['snmp'] = $this->snmp($ip, $p);
        }

        $out['ok'] = ($out['ping']['ok'] ?? false) && ($out['snmp'] === null || $out['snmp']['ok']);
        $out['hints'] = $this->hints($out, $p);

        return $out;
    }

    private function snmp(string $ip, array $p): array
    {
        $base = ['hostname' => $ip, 'port' => (int) ($p['port'] ?? 161) ?: 161, 'transport' => 'udp', 'timeout' => 2, 'retries' => 1];
        if ($p['mode'] === 'v3') {
            $auth = (string) ($p['v3_auth_pass'] ?? '');
            $priv = (string) ($p['v3_priv_pass'] ?? '');
            $attempts = [['label' => 'SNMP v3 user ' . ($p['v3_user'] ?? ''), 'attrs' => [
                'snmpver' => 'v3', 'authname' => $p['v3_user'] ?? '', 'authpass' => $auth, 'authalgo' => $p['v3_auth_algo'] ?? 'SHA',
                'cryptopass' => $priv, 'cryptoalgo' => $p['v3_priv_algo'] ?? 'AES',
                'authlevel' => ($auth !== '' ? 'auth' : 'noAuth') . (($auth !== '' && $priv !== '') ? 'Priv' : 'NoPriv'),
            ]]];
        } else {
            $communities = ! empty($p['community']) ? [$p['community']]
                : Arr::where(Arr::wrap(LibrenmsConfig::get('snmp.community')), fn ($c) => $c && is_string($c));
            $attempts = [];
            foreach (array_slice(array_values($communities), 0, 5) as $i => $c) {
                $attempts[] = ['label' => ! empty($p['community']) ? ($p['community_source'] ?? 'community yang diisi') : 'community #' . ($i + 1) . ' dari Pengaturan Sistem',
                    'attrs' => ['snmpver' => 'v2c', 'community' => $c]];
            }
            if (! $attempts) {
                return ['ok' => false, 'text' => 'Community belum diisi dan daftar community di Pengaturan Sistem kosong.', 'tried' => []];
            }
        }

        $tried = [];
        foreach ($attempts as $a) {
            $device = (new Device)->forceFill($base + $a['attrs']);
            $t = microtime(true);
            try {
                $res = \SnmpQuery::device($device)->get(['SNMPv2-MIB::sysName.0', 'SNMPv2-MIB::sysDescr.0', 'SNMPv2-MIB::sysObjectID.0']);
                $vals = $res->values();
                $ok = $res->isValid() && $vals !== [];
                $err = $ok ? null : trim($res->getErrorMessage() ?: 'Tidak ada balasan (timeout)');
            } catch (\Throwable $e) {
                $ok = false;
                $vals = [];
                $err = $e->getMessage();
            }
            $tried[] = ['label' => $a['label'], 'ok' => $ok, 'ms' => (int) round((microtime(true) - $t) * 1000), 'error' => $err];
            if ($ok) {
                $get = fn (string $k) => collect($vals)->first(fn ($v, $key) => str_contains((string) $key, $k));

                return ['ok' => true, 'text' => 'Terbaca lewat ' . $a['label'] . '.', 'tried' => $tried,
                    'sysName' => $get('sysName'), 'sysDescr' => mb_substr((string) $get('sysDescr'), 0, 200), 'used' => $a['attrs']];
            }
        }

        return ['ok' => false, 'text' => 'SNMP tidak menjawab.', 'tried' => $tried];
    }

    private function hints(array $o, array $p): array
    {
        $h = [];
        $pingOk = $o['ping']['ok'] ?? false;
        $snmp = $o['snmp'];
        if (! $pingOk) {
            $h[] = 'Perangkat tidak membalas ping dari server ANTAREJA. Cek: IP benar & perangkat menyala; rute dari VM ANTAREJA ke subnet perangkat; firewall di jalur (FortiGate dll.) mengizinkan ICMP dari IP server ANTAREJA.';
            $h[] = 'MikroTik: pastikan tidak ada rule drop ICMP di /ip firewall filter chain=input. Kamera/NVR Hikvision: cek IP filter / opsi tolak ping di menu Network.';
        }
        if ($snmp && ! $snmp['ok']) {
            if ($pingOk) {
                $h[] = 'Perangkat hidup tapi SNMP tidak menjawab. Penyebab tersering: community salah (huruf besar/kecil berpengaruh), atau IP server ANTAREJA belum diizinkan di daftar alamat community.';
                $h[] = 'MikroTik: /snmp set enabled=yes lalu /snmp community add name=<community> addresses=<IP server ANTAREJA>/32 read-access=yes write-access=no. Pastikan juga UDP 161 tidak diblok firewall.';
                $h[] = 'Ubiquiti airOS: Services → SNMP Agent → Enable, isi community. Cambium/lainnya: aktifkan SNMP v2c/v3 dan izinkan IP server ANTAREJA.';
                if ($p['mode'] === 'v3') {
                    $h[] = 'SNMP v3: username, algoritma (SHA/MD5, AES/DES), dan kedua password harus sama persis dengan di perangkat; password minimal 8 karakter.';
                }
            } else {
                $h[] = 'SNMP juga tidak menjawab — selesaikan dulu masalah ping/rute di atas.';
            }
        }
        if ($pingOk && $snmp === null) {
            $h[] = 'Mode ping saja: status up/down & latensi tersedia. Untuk data port, trafik, dan sensor, aktifkan SNMP di perangkat lalu uji dengan mode SNMP.';
        }

        return $h;
    }
}
