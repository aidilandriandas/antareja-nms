<?php

namespace Antareja\Services;

use Antareja\AntarejaServiceProvider;
use Antareja\Models\Backup;
use Antareja\Models\BackupSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Backup NMS lengkap dari web: database (semua perangkat, kredensial SNMP, alert, inventaris, user, dll.),
 * folder data (.env berisi APP_KEY, evidence, konfigurasi) dan — opsional — grafik historis RRD.
 *
 * Format file sama dengan "./antareja.sh backup" sehingga bisa dipulihkan dengan "./antareja.sh restore":
 *   antareja-YYYYmmdd-HHMMSS.tar.gz
 *     └ antareja-YYYYmmdd-HHMMSS/ database.sql.gz, data.tar.gz, manifest.json
 * File disimpan di volume data (/data/antareja/backups) dan bisa disalin otomatis ke server lain lewat SFTP,
 * supaya tetap aman bila VM ANTAREJA rusak.
 */
class BackupService
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public static function dir(): string
    {
        return AttachmentService::root() . '/backups';
    }

    /**
     * Samakan catatan dengan isi folder backup. Perlu setelah restore (snapshot database diambil saat backup itu sendiri
     * masih "berjalan") dan bila file disalin manual ke folder backup.
     */
    public function reconcile(): void
    {
        $dir = self::dir();
        foreach (Backup::where('status', 'running')->get() as $b) {
            $file = $dir . '/' . $b->filename;
            $working = is_dir($dir . '/.work-' . substr($b->filename, 0, -7));
            if (is_file($file) && ! $working) {
                $b->forceFill(['status' => 'ok', 'size' => filesize($file), 'finished_at' => $b->finished_at ?? $b->started_at,
                    'message' => $b->message])->save();
            } elseif (! $working && $b->started_at?->lt(now()->subMinutes(10)) || $b->started_at?->lt(now()->subHours(3))) {
                $b->forceFill(['status' => 'failed', 'message' => 'Proses backup terhenti (VM/container di-restart?).', 'finished_at' => now()])->save();
            }
        }
        $known = Backup::pluck('filename')->flip();
        foreach (glob($dir . '/antareja-*.tar.gz') ?: [] as $file) {
            $name = basename($file);
            if (! isset($known[$name]) && preg_match('/^antareja-(\d{8}-\d{6})\.tar\.gz$/', $name, $m)) {
                $at = \Illuminate\Support\Carbon::createFromFormat('Ymd-His', $m[1]);
                Backup::create(['filename' => $name, 'status' => 'ok', 'trigger' => 'manual', 'size' => filesize($file),
                    'message' => 'Ditemukan di folder backup', 'started_at' => $at, 'finished_at' => $at]);
            }
        }
    }

    /** Mulai backup di latar belakang (dipanggil dari web agar request tidak menunggu). */
    public function startInBackground(int $userId): Backup
    {
        $this->reconcile();
        if ($running = Backup::where('status', 'running')->where('started_at', '>', now()->subHours(3))->first()) {
            return $running;
        }
        $backup = $this->newRecord('manual', $userId);
        $cmd = sprintf('nohup %s %s antareja:backup --record=%d > /dev/null 2>&1 &',
            escapeshellarg(PHP_BINARY && ! str_contains(PHP_BINARY, 'fpm') ? PHP_BINARY : 'php'), escapeshellarg(base_path('artisan')), $backup->id);
        exec($cmd);

        return $backup;
    }

    public function newRecord(string $trigger, ?int $userId): Backup
    {
        return Backup::create([
            'filename' => 'antareja-' . now()->format('Ymd-His') . '.tar.gz',
            'status' => 'running',
            'trigger' => $trigger,
            'include_rrd' => BackupSetting::current()->include_rrd,
            'created_by' => $userId,
            'started_at' => now(),
        ]);
    }

    public function run(Backup $backup): Backup
    {
        $settings = BackupSetting::current();
        $dir = self::dir();
        $name = substr($backup->filename, 0, -strlen('.tar.gz'));
        $work = $dir . '/.work-' . $name;
        $inner = $work . '/' . $name;
        @set_time_limit(0);

        try {
            if (! is_dir($inner) && ! mkdir($inner, 0750, true)) {
                throw new \RuntimeException("Tidak bisa membuat folder kerja $inner");
            }
            $this->checkDiskSpace($dir);

            // 1) Database
            $db = config('database.connections.' . config('database.default'));
            $this->sh(sprintf('mariadb-dump --single-transaction --quick --routines --triggers --no-tablespaces -h %s -P %d -u %s %s | gzip -6 > %s',
                escapeshellarg($db['host']), (int) ($db['port'] ?: 3306), escapeshellarg($db['username']), escapeshellarg($db['database']),
                escapeshellarg("$inner/database.sql.gz")), ['MYSQL_PWD' => (string) $db['password']], 3600);

            // 2) Folder data (tanpa log & tanpa backup lain; RRD opsional)
            $dataRoot = is_dir('/data') ? '/data' : storage_path('app');
            $excludes = ['./logs', './antareja/backups'];
            if (! $backup->include_rrd) {
                $excludes[] = './rrd';
            }
            $this->sh(sprintf('tar czf %s %s -C %s .', escapeshellarg("$inner/data.tar.gz"),
                implode(' ', array_map(fn ($e) => '--exclude=' . escapeshellarg($e), $excludes)), escapeshellarg($dataRoot)), [], 7200, [0, 1]);

            // 3) Manifest — ringkasan isi agar mudah dicek sebelum restore
            $summary = [
                'antareja_version' => AntarejaServiceProvider::VERSION,
                'created_at' => now()->toIso8601String(),
                'devices' => DB::table('devices')->count(),
                'users' => DB::table('users')->count(),
                'sites' => DB::table('ant_sites')->count(),
                'include_rrd' => (bool) $backup->include_rrd,
                'database_bytes' => filesize("$inner/database.sql.gz"),
                'data_bytes' => filesize("$inner/data.tar.gz"),
            ];
            file_put_contents("$inner/manifest.json", json_encode($summary, JSON_PRETTY_PRINT));

            $this->sh(sprintf('tar czf %s -C %s %s', escapeshellarg("$dir/{$backup->filename}"), escapeshellarg($work), escapeshellarg($name)), [], 3600);
            @chmod("$dir/{$backup->filename}", 0600);
            $this->cleanup($work);

            $backup->forceFill([
                'status' => 'ok', 'size' => filesize("$dir/{$backup->filename}"), 'sha256' => hash_file('sha256', "$dir/{$backup->filename}"),
                'summary' => $summary, 'finished_at' => now(), 'message' => null,
            ])->save();
            $this->audit->log('created', 'backup', "Backup NMS {$backup->filename} selesai (" . self::human($backup->size) . ", {$summary['devices']} perangkat)");
        } catch (\Throwable $e) {
            $this->cleanup($work);
            @unlink("$dir/{$backup->filename}");
            $backup->forceFill(['status' => 'failed', 'message' => mb_substr($e->getMessage(), 0, 500), 'finished_at' => now()])->save();
            Log::error('ANTAREJA backup gagal: ' . $e->getMessage());
            $this->audit->log('failed', 'backup', 'Backup NMS gagal: ' . mb_substr($e->getMessage(), 0, 200));

            return $backup;
        }

        if ($settings->sftp_enabled) {
            try {
                $this->upload($backup, $settings);
                $backup->forceFill(['offsite_status' => 'ok'])->save();
            } catch (\Throwable $e) {
                $backup->forceFill(['offsite_status' => 'failed', 'message' => 'Salinan SFTP gagal: ' . mb_substr($e->getMessage(), 0, 450)])->save();
                Log::warning('ANTAREJA backup SFTP gagal: ' . $e->getMessage());
            }
        } else {
            $backup->forceFill(['offsite_status' => 'skipped'])->save();
        }

        $this->rotate($settings->keep_count);

        return $backup;
    }

    /** Hapus backup lokal lama, sisakan $keep terbaru yang berhasil. */
    public function rotate(int $keep): int
    {
        $old = Backup::where('status', 'ok')->orderByDesc('id')->skip(max(1, $keep))->take(1000)->get();
        foreach ($old as $b) {
            @unlink($b->path());
            $b->delete();
        }
        Backup::where('status', 'failed')->where('created_at', '<', now()->subDays(30))->delete();

        return $old->count();
    }

    public function delete(Backup $backup): void
    {
        @unlink($backup->path());
        $backup->delete();
    }

    /** @return array{ok: bool, message: string} */
    public function testSftp(BackupSetting $settings): array
    {
        try {
            $sftp = $this->sftp($settings);
            $path = $settings->sftp_path ?: '.';
            if (! $sftp->is_dir($path)) {
                $sftp->mkdir($path, -1, true);
            }
            $probe = rtrim($path, '/') . '/.antareja-test';
            $sftp->put($probe, 'ok ' . now()->toIso8601String());
            $sftp->delete($probe);
            $sftp->disconnect();

            return ['ok' => true, 'message' => "Terhubung ke {$settings->sftp_host} dan bisa menulis ke {$path}. Host key: {$settings->sftp_host_key}"];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    private function upload(Backup $backup, BackupSetting $settings): void
    {
        $sftp = $this->sftp($settings);
        $path = rtrim($settings->sftp_path ?: '.', '/');
        if (! $sftp->is_dir($path)) {
            $sftp->mkdir($path, -1, true);
        }
        $cls = get_class($sftp);
        $sftp->put("$path/{$backup->filename}.part", $backup->path(), $cls::SOURCE_LOCAL_FILE);
        $sftp->rename("$path/{$backup->filename}.part", "$path/{$backup->filename}");
        if ((int) $sftp->filesize("$path/{$backup->filename}") !== (int) $backup->size) {
            throw new \RuntimeException('Ukuran file di server tujuan tidak sama dengan aslinya.');
        }
        // Rotasi di server tujuan: hanya file antareja-*.tar.gz milik ANTAREJA
        $files = collect($sftp->nlist($path) ?: [])->filter(fn ($f) => preg_match('/^antareja-\d{8}-\d{6}\.tar\.gz$/', (string) $f))->sort()->values();
        foreach ($files->slice(0, max(0, $files->count() - max(1, $settings->sftp_keep_count))) as $f) {
            $sftp->delete("$path/$f", false);
        }
        $sftp->disconnect();
    }

    private function sftp(BackupSetting $settings): object
    {
        $cls = class_exists(\phpseclib4\Net\SFTP::class) ? \phpseclib4\Net\SFTP::class
            : (class_exists(\phpseclib3\Net\SFTP::class) ? \phpseclib3\Net\SFTP::class : throw new \RuntimeException('Library SFTP (phpseclib) tidak tersedia.'));
        if (! $settings->sftp_host || ! $settings->sftp_username) {
            throw new \RuntimeException('Host dan username SFTP belum diisi.');
        }
        $sftp = new $cls($settings->sftp_host, $settings->sftp_port ?: 22, 20);
        $key = $sftp->getServerPublicHostKey();
        if (! $key) {
            throw new \RuntimeException("Tidak bisa terhubung ke {$settings->sftp_host}:{$settings->sftp_port}.");
        }
        $fp = ConfigBackupService::fingerprint($key);
        if ($settings->sftp_host_key && ! hash_equals($settings->sftp_host_key, $fp)) {
            throw new \RuntimeException("Host key server tujuan berubah ({$fp}, tersimpan {$settings->sftp_host_key}). Bila server memang diganti, kosongkan host key di pengaturan.");
        }
        $secret = (string) $settings->sftp_secret;
        $cred = $secret;
        if (str_contains($secret, 'PRIVATE KEY')) {
            $loader = class_exists(\phpseclib4\Crypt\PublicKeyLoader::class) ? \phpseclib4\Crypt\PublicKeyLoader::class : \phpseclib3\Crypt\PublicKeyLoader::class;
            $cred = $loader::load($secret);
        }
        if (! $sftp->login($settings->sftp_username, $cred)) {
            throw new \RuntimeException("Login SFTP ke {$settings->sftp_host} gagal untuk user {$settings->sftp_username}.");
        }
        if (! $settings->sftp_host_key) {
            $settings->forceFill(['sftp_host_key' => $fp])->saveQuietly();
        }

        return $sftp;
    }

    private function sh(string $cmd, array $env = [], int $timeout = 3600, array $okCodes = [0]): void
    {
        $p = Process::fromShellCommandline('set -o pipefail; ' . $cmd, null, $env + ['PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin'], null, $timeout);
        $p->run();
        // tar keluar dengan 1 bila ada file berubah saat dibaca (mis. RRD sedang ditulis) — isi tetap valid
        if (! in_array($p->getExitCode(), $okCodes, true)) {
            throw new \RuntimeException(trim(mb_substr($p->getErrorOutput() ?: $p->getOutput(), 0, 400)) ?: "Perintah gagal (kode {$p->getExitCode()})");
        }
    }

    private function checkDiskSpace(string $dir): void
    {
        $free = @disk_free_space($dir);
        if ($free !== false && $free < 512 * 1024 * 1024) {
            throw new \RuntimeException('Ruang disk tersisa kurang dari 512 MB — hapus backup lama atau perbesar disk VM.');
        }
    }

    private function cleanup(string $work): void
    {
        if (is_dir($work) && str_contains($work, '/.work-antareja-')) {
            (new Process(['rm', '-rf', '--', $work]))->run();
        }
    }

    public static function human(?int $bytes): string
    {
        if (! $bytes) {
            return '0 B';
        }
        $u = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes, 1024));

        return round($bytes / 1024 ** $i, 1) . ' ' . $u[$i];
    }
}
