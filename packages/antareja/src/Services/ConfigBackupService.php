<?php

namespace Antareja\Services;

use Antareja\Models\Change;
use Antareja\Models\ConfigTarget;
use Antareja\Models\ConfigVersion;
use Antareja\Models\MaintenanceWindow;
use Antareja\Support\ConfigProfiles;
use Antareja\Support\LineDiff;
use Illuminate\Support\Facades\Log;

/**
 * Backup konfigurasi perangkat lewat SSH, HANYA perintah baca dari profil vendor (lihat ConfigProfiles).
 *
 * - Host key SSH disimpan saat pengambilan pertama (trust on first use); bila berubah, pengambilan ditolak.
 * - Versi baru disimpan hanya bila isi berubah (hash). Isi dienkripsi dengan APP_KEY.
 * - Perubahan dianggap sah bila ada change request (disetujui / dilaksanakan / selesai) atau maintenance jenis "change"
 *   untuk perangkat itu yang jendela waktunya beririsan dengan rentang sejak pengambilan sebelumnya.
 *   Bila tidak ada → ditandai "tanpa change request" dan muncul sebagai problem sampai di-acknowledge.
 */
class ConfigBackupService
{
    /** Kelonggaran jendela change (perubahan sering dieksekusi sedikit lebih awal / dicek setelahnya). */
    private const WINDOW_SLACK_BEFORE = 1800;

    private const WINDOW_SLACK_AFTER = 6 * 3600;

    public function __construct(private readonly AuditLogger $audit)
    {
    }

    /**
     * @return array{status:string, message:string, version:?ConfigVersion}
     */
    public function collect(ConfigTarget $target): array
    {
        $target->loadMissing(['device', 'credential']);
        $now = now();
        $target->forceFill(['last_attempt_at' => $now]);

        try {
            $raw = $this->fetch($target);
        } catch (HostKeyMismatch $e) {
            $this->fail($target, 'hostkey', $e->getMessage());

            return ['status' => 'hostkey', 'message' => $e->getMessage(), 'version' => null];
        } catch (\Throwable $e) {
            $this->fail($target, 'error', $e->getMessage());

            return ['status' => 'error', 'message' => $e->getMessage(), 'version' => null];
        }

        $config = ConfigProfiles::normalize($target->profile, $raw);
        foreach (ConfigProfiles::ERROR_PATTERNS as $re) {
            if (preg_match($re, $config)) {
                $msg = 'Perangkat menolak perintah: ' . mb_substr(trim(strtok($config, "\n")), 0, 200) . ' — periksa profil vendor.';
                $this->fail($target, 'error', $msg);

                return ['status' => 'error', 'message' => $msg, 'version' => null];
            }
        }
        if (substr_count($config, "\n") < 3) {
            $this->fail($target, 'error', 'Keluaran terlalu pendek (' . strlen($config) . ' byte) — kemungkinan hak akses akun kurang.');

            return ['status' => 'error', 'message' => 'Keluaran terlalu pendek', 'version' => null];
        }

        $hash = hash('sha256', $config);
        $previous = ConfigVersion::where('device_id', $target->device_id)->orderByDesc('collected_at')->orderByDesc('id')->first();
        if ($previous && hash_equals($previous->hash, $hash)) {
            $target->forceFill(['last_status' => 'unchanged', 'last_error' => null, 'last_collected_at' => $now])->saveQuietly();

            return ['status' => 'unchanged', 'message' => 'Konfigurasi tidak berubah.', 'version' => $previous];
        }

        $stats = $previous ? LineDiff::stats(LineDiff::diff($previous->text(), $config)) : ['added' => substr_count($config, "\n"), 'removed' => 0];
        [$changeId, $authorized] = $previous ? $this->authorization($target->device_id, $previous->collected_at->getTimestamp(), $now->getTimestamp()) : [null, true];

        $version = ConfigVersion::create([
            'device_id' => $target->device_id, 'collected_at' => $now, 'hash' => $hash, 'size' => strlen($config),
            'line_count' => substr_count($config, "\n"), 'content' => ConfigVersion::pack($config),
            'lines_added' => $stats['added'], 'lines_removed' => $stats['removed'],
            'change_id' => $changeId, 'unauthorized' => ! $authorized,
        ]);
        $target->forceFill(['last_status' => 'ok', 'last_error' => null, 'last_collected_at' => $now, 'last_changed_at' => $now])->saveQuietly();

        $name = $target->device?->displayName() ?? "#{$target->device_id}";
        if ($previous) {
            \App\Models\Eventlog::log("ANTAREJA: konfigurasi {$name} berubah (+{$stats['added']} / -{$stats['removed']} baris)"
                . ($authorized ? '' : ' TANPA change request'), $target->device_id, 'config', $authorized ? \LibreNMS\Enum\Severity::Info : \LibreNMS\Enum\Severity::Warning);
        }

        return ['status' => 'ok', 'message' => $previous ? "Konfigurasi berubah: +{$stats['added']} / -{$stats['removed']} baris." : 'Versi pertama (baseline) disimpan.', 'version' => $version];
    }

    /** Jalankan semua target yang sudah waktunya. */
    public function collectDue(bool $force = false, array $deviceIds = []): array
    {
        $q = ConfigTarget::with(['device', 'credential'])->where('enabled', true);
        if ($deviceIds) {
            $q->whereIn('device_id', $deviceIds);
        }
        $out = ['ok' => 0, 'unchanged' => 0, 'error' => 0, 'hostkey' => 0];
        foreach ($q->get() as $t) {
            if (! $force && ! $t->isDue()) {
                continue;
            }
            $r = $this->collect($t);
            $out[$r['status']]++;
        }

        return $out;
    }

    /**
     * @return array{0:?int, 1:bool} [change_id, sah?]
     */
    public function authorization(int $deviceId, int $since, int $until): array
    {
        $changes = Change::whereIn('status', Change::AUTHORIZING)
            ->whereHas('devices', fn ($q) => $q->where('devices.device_id', $deviceId))
            ->whereNotNull('planned_start')->get();
        foreach ($changes as $c) {
            $start = $c->planned_start->getTimestamp() - self::WINDOW_SLACK_BEFORE;
            $end = max($c->planned_end?->getTimestamp() ?? 0, $c->implemented_at?->getTimestamp() ?? 0, $c->planned_start->getTimestamp()) + self::WINDOW_SLACK_AFTER;
            if ($start <= $until && $end >= $since) {
                return [$c->id, true];
            }
        }
        $maint = MaintenanceWindow::where('type', 'change')->where('status', '!=', 'cancelled')
            ->whereHas('devices', fn ($q) => $q->where('devices.device_id', $deviceId))
            ->where('starts_at', '<=', \Carbon\Carbon::createFromTimestamp($until + self::WINDOW_SLACK_BEFORE))
            ->where('ends_at', '>=', \Carbon\Carbon::createFromTimestamp($since - self::WINDOW_SLACK_AFTER))->exists();

        return [null, $maint];
    }

    private function fail(ConfigTarget $target, string $status, string $message): void
    {
        $target->forceFill(['last_status' => $status, 'last_error' => mb_substr($message, 0, 500)])->saveQuietly();
        Log::warning("ANTAREJA config backup device {$target->device_id}: {$message}");
    }

    /** Ambil keluaran mentah lewat SSH exec (tanpa shell interaktif, tanpa perintah tulis). */
    private function fetch(ConfigTarget $target): string
    {
        $cls = class_exists(\phpseclib4\Net\SSH2::class) ? \phpseclib4\Net\SSH2::class
            : (class_exists(\phpseclib3\Net\SSH2::class) ? \phpseclib3\Net\SSH2::class : null);
        if ($cls === null) {
            throw new \RuntimeException('Library SSH (phpseclib) tidak tersedia di image ini.');
        }
        $profile = ConfigProfiles::PROFILES[$target->profile] ?? throw new \RuntimeException("Profil {$target->profile} tidak dikenal.");
        $cred = $target->credential ?? throw new \RuntimeException('Kredensial tidak ditemukan.');
        $host = $target->host ?: ($target->device?->overwrite_ip ?: $target->device?->hostname);

        $ssh = new $cls($host, $cred->port ?: 22, 15);
        $ssh->setTimeout(60);

        $key = $ssh->getServerPublicHostKey();
        if (! $key) {
            throw new \RuntimeException("Tidak bisa terhubung ke {$host}:{$cred->port}.");
        }
        $fp = self::fingerprint($key);
        if ($target->host_key && ! hash_equals($target->host_key, $fp)) {
            throw new HostKeyMismatch("Host key {$host} berubah ({$fp}, tersimpan {$target->host_key}). Bila perangkat memang diganti/di-reset, hapus host key tersimpan di pengaturan backup.");
        }
        if (! $ssh->login($cred->username, $cred->secret)) {
            throw new \RuntimeException("Login SSH ke {$host} gagal untuk user {$cred->username}.");
        }
        if (! $target->host_key) {
            $target->forceFill(['host_key' => $fp]);
        }

        $out = '';
        foreach ($profile['commands'] as $cmd) {
            $out .= (string) $ssh->exec($cmd);
        }
        $ssh->disconnect();

        return $out;
    }

    /** Sidik jari SHA256 seperti OpenSSH, dari "algoritma base64-blob". */
    public static function fingerprint(string $publicKey): string
    {
        $parts = explode(' ', trim($publicKey));
        $blob = base64_decode($parts[1] ?? $parts[0], true) ?: $publicKey;

        return 'SHA256:' . rtrim(base64_encode(hash('sha256', $blob, true)), '=');
    }
}
