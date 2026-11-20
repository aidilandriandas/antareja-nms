<?php

namespace Antareja\Http\Controllers;

use Antareja\Models\Backup;
use Antareja\Models\BackupSetting;
use Antareja\Services\AuditLogger;
use Antareja\Services\BackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Backup & pemulihan NMS. Hanya admin (hak "settings.update"): file backup berisi seluruh database termasuk
 * kredensial SNMP/SSH dan APP_KEY, jadi perlakukan seperti kunci brankas.
 */
class BackupController extends Controller
{
    public function index(BackupService $service): View
    {
        Gate::authorize('settings.update');
        $service->reconcile();
        $backups = Backup::orderByDesc('id')->limit(60)->get();

        return view('antareja::backup.index', [
            'settings' => BackupSetting::current(),
            'backups' => $backups,
            'running' => $backups->firstWhere('status', 'running'),
            'lastOk' => $backups->firstWhere('status', 'ok'),
            'devices' => DB::table('devices')->count(),
            'free' => @disk_free_space(BackupService::dir()) ?: @disk_free_space(dirname(BackupService::dir())),
            'dir' => BackupService::dir(),
        ]);
    }

    public function run(Request $request, BackupService $service): RedirectResponse
    {
        Gate::authorize('settings.update');
        @mkdir(BackupService::dir(), 0750, true);
        $backup = $service->startInBackground($request->user()->user_id);

        return redirect()->route('antareja.backup.index')
            ->with('antareja_success', "Backup {$backup->filename} sedang dibuat di latar belakang. Halaman ini diperbarui otomatis.");
    }

    public function status(): JsonResponse
    {
        Gate::authorize('settings.update');

        return response()->json(['running' => Backup::where('status', 'running')->where('started_at', '>', now()->subHours(3))->exists()]);
    }

    public function download(Backup $backup, AuditLogger $audit): BinaryFileResponse
    {
        Gate::authorize('settings.update');
        abort_unless($backup->status === 'ok' && $backup->exists(), 404, 'File backup tidak ditemukan.');
        $audit->log('downloaded', 'backup', "Backup {$backup->filename} diunduh");

        return response()->download($backup->path(), $backup->filename, ['Content-Type' => 'application/gzip']);
    }

    public function destroy(Backup $backup, BackupService $service, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('settings.update');
        abort_if($backup->status === 'running', 409, 'Backup sedang berjalan.');
        $service->delete($backup);
        $audit->log('deleted', 'backup', "Backup {$backup->filename} dihapus");

        return back()->with('antareja_success', "Backup {$backup->filename} dihapus.");
    }

    public function settings(Request $request): RedirectResponse
    {
        Gate::authorize('settings.update');
        $data = $request->validate([
            'schedule_time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'keep_count' => ['required', 'integer', 'between:1,365'],
            'sftp_host' => ['nullable', 'required_if:sftp_enabled,1', 'string', 'max:255', 'regex:/^[A-Za-z0-9.\-:\[\]]+$/'],
            'sftp_port' => ['nullable', 'integer', 'between:1,65535'],
            'sftp_username' => ['nullable', 'required_if:sftp_enabled,1', 'string', 'max:128'],
            'sftp_secret' => ['nullable', 'string', 'max:16000'],
            'sftp_path' => ['nullable', 'string', 'max:255'],
            'sftp_keep_count' => ['nullable', 'integer', 'between:1,3650'],
        ], [
            'schedule_time.regex' => 'Format jam HH:MM (24 jam).',
            'sftp_host.required_if' => 'Isi host server tujuan bila salinan SFTP diaktifkan.',
            'sftp_username.required_if' => 'Isi username SFTP bila salinan SFTP diaktifkan.',
        ]);
        $settings = BackupSetting::current();
        $hostChanged = ($data['sftp_host'] ?? null) !== $settings->sftp_host || (int) ($data['sftp_port'] ?? 22) !== (int) $settings->sftp_port;
        $settings->fill([
            'schedule_enabled' => $request->boolean('schedule_enabled'),
            'schedule_time' => $data['schedule_time'],
            'keep_count' => $data['keep_count'],
            'include_rrd' => $request->boolean('include_rrd'),
            'sftp_enabled' => $request->boolean('sftp_enabled'),
            'sftp_host' => $data['sftp_host'] ?? null,
            'sftp_port' => $data['sftp_port'] ?? 22,
            'sftp_username' => $data['sftp_username'] ?? null,
            'sftp_path' => $data['sftp_path'] ?? null,
            'sftp_keep_count' => $data['sftp_keep_count'] ?? 30,
        ]);
        if (! empty($data['sftp_secret'])) {
            $settings->sftp_secret = $data['sftp_secret'];
        }
        if ($hostChanged || $request->boolean('reset_host_key')) {
            $settings->sftp_host_key = null;
        }
        $settings->save();

        return back()->with('antareja_success', 'Pengaturan backup disimpan.' . ($settings->sftp_enabled ? ' Klik "Uji koneksi" untuk memastikan salinan SFTP berjalan.' : ''));
    }

    public function testSftp(BackupService $service): RedirectResponse
    {
        Gate::authorize('settings.update');
        $r = $service->testSftp(BackupSetting::current());

        return back()->with($r['ok'] ? 'antareja_success' : 'antareja_error', ($r['ok'] ? 'Uji SFTP berhasil: ' : 'Uji SFTP gagal: ') . $r['message']);
    }
}
