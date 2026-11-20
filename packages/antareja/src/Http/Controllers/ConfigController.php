<?php

namespace Antareja\Http\Controllers;

use Antareja\Models\ConfigCredential;
use Antareja\Models\ConfigTarget;
use Antareja\Models\ConfigVersion;
use Antareja\Services\AuditLogger;
use Antareja\Services\ConfigBackupService;
use Antareja\Support\ConfigProfiles;
use Antareja\Support\LineDiff;
use App\Models\Device;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ConfigController extends Controller
{
    public function index(Request $request): View
    {
        $targets = ConfigTarget::with(['device', 'credential'])->get()->sortBy(fn ($t) => $t->device?->displayName());
        $unauth = ConfigVersion::where('unauthorized', true)->whereNull('acknowledged_at')
            ->selectRaw('device_id, COUNT(*) AS n')->groupBy('device_id')->pluck('n', 'device_id');

        return view('antareja::config.index', [
            'targets' => $targets,
            'unauth' => $unauth,
            'versions' => ConfigVersion::selectRaw('device_id, COUNT(*) AS n')->groupBy('device_id')->pluck('n', 'device_id'),
            'credentials' => ConfigCredential::withCount('targets')->orderBy('name')->get(),
            'devices' => Device::whereNotIn('device_id', $targets->pluck('device_id'))->where('disabled', 0)->orderBy('hostname')->get(),
            'recent' => ConfigVersion::with(['device', 'change'])->orderByDesc('collected_at')->limit(15)->get(),
        ]);
    }

    public function device(Request $request, Device $device): View
    {
        $target = ConfigTarget::with('credential')->find($device->device_id);
        $versions = ConfigVersion::with(['change', 'acknowledger'])->where('device_id', $device->device_id)
            ->orderByDesc('collected_at')->orderByDesc('id')->get();
        $reveal = $request->boolean('reveal') && $request->user()->can('ant-config.update');
        if ($reveal) {
            app(AuditLogger::class)->log('viewed', 'config', "Konfigurasi {$device->displayName()} dibuka tanpa penyamaran rahasia");
        }

        // Diff: ?b= versi baru (bawaan terbaru), ?a= versi lama (bawaan sebelum b)
        $b = $request->filled('b') ? $versions->firstWhere('id', $request->integer('b')) : $versions->first();
        $a = $request->filled('a') ? $versions->firstWhere('id', $request->integer('a'))
            : ($b ? $versions->first(fn ($v) => $v->collected_at->lt($b->collected_at) || ($v->collected_at->eq($b->collected_at) && $v->id < $b->id)) : null);
        $hunks = null;
        $text = null;
        if ($b) {
            $newText = $b->text();
            if ($a) {
                $ops = LineDiff::diff($a->text(), $newText);
                if (! $reveal) {
                    $ops = array_map(fn ($o) => [$o[0], ConfigProfiles::mask($o[1]), $o[2], $o[3]], $ops);
                }
                $hunks = LineDiff::hunks($ops, 3);
            }
            $text = $reveal ? $newText : ConfigProfiles::mask($newText);
        }

        return view('antareja::config.device', [
            'device' => $device,
            'target' => $target,
            'versions' => $versions,
            'a' => $a,
            'b' => $b,
            'hunks' => $hunks,
            'text' => $text,
            'reveal' => $reveal,
            'credentials' => ConfigCredential::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function download(Request $request, ConfigVersion $version): Response
    {
        $reveal = $request->boolean('reveal') && $request->user()->can('ant-config.update');
        $device = $version->device;
        app(AuditLogger::class)->log('exported', 'config', "Unduh konfigurasi {$device?->displayName()} versi {$version->collected_at->format('Y-m-d H:i')}" . ($reveal ? ' (tanpa penyamaran)' : ''));
        $text = $reveal ? $version->text() : ConfigProfiles::mask($version->text());
        $name = preg_replace('/[^A-Za-z0-9._-]/', '_', ($device?->hostname ?? 'device') . '-' . $version->collected_at->format('Ymd-His')) . '.txt';

        return response($text, 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'Content-Disposition' => "attachment; filename=\"{$name}\""]);
    }

    public function acknowledge(Request $request, Device $device): RedirectResponse
    {
        $data = $request->validate(['ack_note' => ['required', 'string', 'max:500']], ['ack_note.required' => 'Tuliskan siapa/kenapa konfigurasi diubah.']);
        $n = ConfigVersion::where('device_id', $device->device_id)->where('unauthorized', true)->whereNull('acknowledged_at')
            ->update(['acknowledged_at' => now(), 'acknowledged_by' => $request->user()->user_id, 'ack_note' => $data['ack_note']]);
        app(AuditLogger::class)->log('updated', 'config', "Perubahan konfigurasi {$device->displayName()} tanpa change request diverifikasi ({$n} versi): {$data['ack_note']}");

        return back()->with('antareja_success', "{$n} versi diverifikasi. Problem akan selesai pada siklus berikutnya.");
    }

    public function collect(Device $device, ConfigBackupService $service): RedirectResponse
    {
        $target = ConfigTarget::findOrFail($device->device_id);
        $r = $service->collect($target);

        return back()->with(in_array($r['status'], ['ok', 'unchanged'], true) ? 'antareja_success' : 'antareja_error', $r['message']);
    }

    public function storeTargets(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'devices' => ['required', 'array', 'min:1', 'max:1000'],
            'devices.*' => ['integer', 'exists:devices,device_id'],
            'profile' => ['required', Rule::in(array_keys(ConfigProfiles::PROFILES))],
            'credential_id' => ['required', 'integer', 'exists:ant_config_credentials,id'],
            'interval_hours' => ['required', 'integer', 'between:1,168'],
        ], [], ['devices' => 'perangkat', 'profile' => 'profil vendor', 'credential_id' => 'kredensial']);
        foreach ($data['devices'] as $id) {
            ConfigTarget::updateOrCreate(['device_id' => $id], [
                'profile' => $data['profile'], 'credential_id' => $data['credential_id'], 'interval_hours' => $data['interval_hours'], 'enabled' => true,
            ]);
        }

        return back()->with('antareja_success', count($data['devices']) . ' perangkat ditambahkan ke backup konfigurasi. Pengambilan pertama berjalan pada siklus berikutnya (atau tekan "Ambil sekarang").');
    }

    public function updateTarget(Request $request, Device $device): RedirectResponse
    {
        $target = ConfigTarget::findOrFail($device->device_id);
        $data = $request->validate([
            'profile' => ['required', Rule::in(array_keys(ConfigProfiles::PROFILES))],
            'credential_id' => ['required', 'integer', 'exists:ant_config_credentials,id'],
            'host' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9.:_-]+$/'],
            'interval_hours' => ['required', 'integer', 'between:1,168'],
        ]);
        $target->fill($data + ['enabled' => $request->boolean('enabled')]);
        if ($request->boolean('reset_host_key')) {
            $target->host_key = null;
            app(AuditLogger::class)->log('updated', 'config', "Host key SSH {$device->displayName()} di-reset");
        }
        $target->save();

        return back()->with('antareja_success', 'Pengaturan backup disimpan.');
    }

    public function destroyTarget(Device $device): RedirectResponse
    {
        ConfigTarget::findOrFail($device->device_id)->delete();

        return redirect()->route('antareja.config.index')->with('antareja_success', "Backup konfigurasi {$device->displayName()} dihentikan. Versi lama tetap tersimpan.");
    }

    public function storeCredential(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:64', 'unique:ant_config_credentials,name'],
            'username' => ['required', 'string', 'max:128'],
            'secret' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'between:1,65535'],
        ], [], ['name' => 'nama', 'secret' => 'password']);
        ConfigCredential::create($data);

        return back()->with('antareja_success', "Kredensial {$data['name']} disimpan (terenkripsi).");
    }

    public function updateCredential(Request $request, ConfigCredential $credential): RedirectResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:128'],
            'secret' => ['nullable', 'string', 'max:255'],
            'port' => ['required', 'integer', 'between:1,65535'],
        ]);
        if (blank($data['secret'] ?? null)) {
            unset($data['secret']);
        }
        $credential->update($data);

        return back()->with('antareja_success', "Kredensial {$credential->name} diperbarui.");
    }

    public function destroyCredential(ConfigCredential $credential): RedirectResponse
    {
        if (ConfigTarget::where('credential_id', $credential->id)->exists()) {
            return back()->with('antareja_error', "Kredensial {$credential->name} masih dipakai perangkat.");
        }
        $credential->delete();

        return back()->with('antareja_success', "Kredensial {$credential->name} dihapus.");
    }
}
