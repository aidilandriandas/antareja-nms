<?php

namespace Antareja\Http\Controllers;

use Antareja\Models\CctvStream;
use Antareja\Models\DeviceDetail;
use Antareja\Models\DeviceLink;
use Antareja\Models\Site;
use Antareja\Models\VideoCredential;
use Antareja\Services\AttachmentService;
use Antareja\Services\AuditLogger;
use Antareja\Services\CctvHealth;
use Antareja\Services\VideoGateway;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Live view & snapshot CCTV lewat gateway video internal, plus pengaturan sumber stream dan cek kesehatan gambar.
 */
class CctvLiveController extends Controller
{
    public function __construct(private readonly VideoGateway $gateway, private readonly AuditLogger $audit)
    {
    }

    /** Grid snapshot (mirip tampilan multi-layar), diperbarui otomatis; klik untuk live. */
    public function grid(Request $request): View
    {
        $q = CctvStream::with(['device', 'nvr'])->where('enabled', true);
        $siteOf = DeviceDetail::pluck('site_id', 'device_id');
        $streams = $q->get()->filter(fn ($s) => $s->device && Gate::allows('view', $s->device))
            ->when($request->filled('site'), fn ($c) => $c->filter(fn ($s) => (string) ($siteOf[$s->device_id] ?? '') === (string) $request->input('site')))
            ->sortBy(fn ($s) => [$siteOf[$s->device_id] ?? PHP_INT_MAX, $s->device->displayName()])->values();
        $sites = Site::orderBy('code')->get(['id', 'code', 'name']);

        return view('antareja::cctv.live', [
            'streams' => $streams,
            'sites' => $sites,
            'siteOf' => $siteOf,
            'siteCodes' => $sites->pluck('code', 'id'),
            'cols' => in_array((int) $request->input('cols'), [2, 3, 4, 6], true) ? (int) $request->input('cols') : 4,
        ]);
    }

    /** Snapshot JPEG (lewat server; kredensial NVR tidak pernah ke browser). Di-cache 4 detik agar NVR tidak dibanjiri. */
    public function snapshot(Request $request, CctvStream $stream): Response
    {
        $this->authorizeStream($stream);
        $width = in_array((int) $request->query('w'), [320, 640, 1280], true) ? (int) $request->query('w') : 640;
        try {
            $jpeg = Cache::remember("antareja:snap:{$stream->id}:{$width}", 4, fn () => base64_encode($this->gateway->frame($stream, $width)));
        } catch (\Throwable $e) {
            return response($this->placeholder('Tidak ada gambar'), 503, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'no-store', 'X-Antareja-Error' => mb_substr($e->getMessage(), 0, 200)]);
        }

        return response(base64_decode($jpeg), 200, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'no-store, private']);
    }

    /** Siapkan live view: daftarkan stream di gateway, kembalikan alamat WebSocket (bukan URL kamera). */
    public function player(Request $request, CctvStream $stream): JsonResponse
    {
        $this->authorizeStream($stream);
        $main = $request->boolean('main');
        try {
            $name = $this->gateway->ensure($stream, $main);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 503);
        }
        // Catat siapa menonton kamera mana (maks. sekali per 10 menit per user & kamera)
        if (Cache::add('antareja:live-audit:' . $request->user()->user_id . ':' . $stream->id, 1, 600)) {
            $this->audit->log('viewed', 'cctv_stream', "Live view {$stream->device?->displayName()} (" . ($main ? 'main' : 'sub') . ' stream)');
        }

        return response()->json(['ws' => url('antareja/live/ws') . '?src=' . rawurlencode($name), 'name' => $stream->device?->displayName()]);
    }

    /**
     * Dipanggil Nginx (auth_request) sebelum meneruskan WebSocket video ke gateway.
     * 204 = boleh; 401/403 = tolak. Nama stream harus milik stream aktif yang boleh dilihat user.
     */
    public function liveAuth(Request $request): Response
    {
        $user = Auth::user();
        if (! $user) {
            return response('', 401);
        }
        parse_str((string) parse_url((string) $request->header('X-Original-URI'), PHP_URL_QUERY), $q);
        $src = (string) ($q['src'] ?? '');
        if (! preg_match('/^cam(\d+)[ms]-[0-9a-f]{10}$/', $src, $m) || ! $user->can('ant-cctv.live')) {
            return response('', 403);
        }
        $stream = CctvStream::with(['device', 'credential'])->where('enabled', true)->find((int) $m[1]);
        if (! $stream || ! $stream->device || ! Gate::forUser($user)->allows('view', $stream->device)
            || ! in_array($src, [$stream->gatewayName(false), $stream->gatewayName(true)], true)) {
            return response('', 403);
        }

        return response('', 204);
    }

    // ---------- Pengaturan sumber stream ----------

    public function streams(): View
    {
        $streams = CctvStream::with(['device', 'nvr', 'credential'])->get()->sortBy(fn ($s) => $s->device?->displayName())->values();
        $cams = DeviceDetail::with('device')->active()->where('category', 'cctv-camera')->get()->filter(fn ($d) => $d->device)
            ->reject(fn ($d) => $streams->contains('device_id', $d->device_id))->sortBy(fn ($d) => $d->device->displayName());
        $nvrOf = DeviceLink::where('status', DeviceLink::VERIFIED)->where('link_type', 'nvr-camera')->pluck('a_device_id', 'b_device_id');

        return view('antareja::cctv.streams', [
            'streams' => $streams,
            'cameras' => $cams,
            'nvrs' => DeviceDetail::with('device')->active()->where('category', 'nvr')->get()->filter(fn ($d) => $d->device),
            'nvrOf' => $nvrOf,
            'credentials' => VideoCredential::withCount('streams')->orderBy('name')->get(),
            'gatewayUp' => $this->gateway->reachable(),
        ]);
    }

    public function showStream(CctvStream $stream): View
    {
        $this->authorizeStream($stream);

        return view('antareja::cctv.stream', [
            'stream' => $stream->load(['device', 'nvr', 'credential']),
            'credentials' => VideoCredential::orderBy('name')->get(['id', 'name']),
            'hasLatest' => Storage::disk(AttachmentService::DISK)->exists("cctv/{$stream->id}/latest.jpg"),
            'hasReference' => Storage::disk(AttachmentService::DISK)->exists("cctv/{$stream->id}/reference.jpg"),
        ]);
    }

    public function storeStream(Request $request): RedirectResponse
    {
        $stream = CctvStream::create($this->streamData($request));

        return redirect()->route('antareja.cctv.streams.show', $stream)->with('antareja_success', "Stream {$stream->device?->displayName()} ditambahkan. Tekan \"Cek sekarang\" untuk menguji.");
    }

    public function updateStream(Request $request, CctvStream $stream): RedirectResponse
    {
        $stream->update($this->streamData($request, $stream));

        return back()->with('antareja_success', 'Pengaturan stream disimpan.');
    }

    public function destroyStream(CctvStream $stream): RedirectResponse
    {
        $stream->delete();
        Storage::disk(AttachmentService::DISK)->deleteDirectory("cctv/{$stream->id}");

        return redirect()->route('antareja.cctv.streams')->with('antareja_success', 'Stream dihapus.');
    }

    public function check(CctvStream $stream, CctvHealth $health): RedirectResponse
    {
        $r = $health->check($stream);

        $flash = match ($r['health']) {
            'ok' => 'antareja_success',
            'error', 'blank', 'frozen' => 'antareja_error',
            default => 'antareja_warning',
        };

        // Detail sudah tampil di banner halaman; pesan flash cukup label
        return back()->with($flash, 'Hasil cek: ' . (CctvStream::HEALTH[$r['health']]['label'] ?? $r['health']) . ($r['health'] === 'error' && $r['detail'] ? ' — ' . $r['detail'] : '.'));
    }

    public function reference(CctvStream $stream, CctvHealth $health): RedirectResponse
    {
        try {
            $health->setReference($stream);
        } catch (\Throwable $e) {
            return back()->with('antareja_error', 'Gagal mengambil gambar acuan: ' . $e->getMessage());
        }
        $this->audit->log('updated', 'cctv_stream', "Gambar acuan {$stream->device?->displayName()} diperbarui");

        return back()->with('antareja_success', 'Gambar acuan disimpan. Pemeriksaan buram & pergeseran memakai gambar ini.');
    }

    /** Snapshot tersimpan (terakhir dicek / acuan). */
    public function image(CctvStream $stream, string $which): Response
    {
        $this->authorizeStream($stream);
        abort_unless(in_array($which, ['latest', 'reference'], true), 404);
        $path = "cctv/{$stream->id}/{$which}.jpg";
        abort_unless(Storage::disk(AttachmentService::DISK)->exists($path), 404);

        return response(Storage::disk(AttachmentService::DISK)->get($path), 200, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'no-store, private']);
    }

    public function storeCredential(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:64', 'unique:ant_video_credentials,name'],
            'username' => ['required', 'string', 'max:128'],
            'secret' => ['required', 'string', 'max:255'],
        ], [], ['name' => 'nama', 'secret' => 'password']);
        VideoCredential::create($data);

        return back()->with('antareja_success', "Kredensial {$data['name']} disimpan (terenkripsi).");
    }

    public function destroyCredential(VideoCredential $credential): RedirectResponse
    {
        if ($credential->streams()->exists()) {
            return back()->with('antareja_error', "Kredensial {$credential->name} masih dipakai stream.");
        }
        $credential->delete();

        return back()->with('antareja_success', "Kredensial {$credential->name} dihapus.");
    }

    private function streamData(Request $request, ?CctvStream $stream = null): array
    {
        $data = $request->validate([
            'device_id' => [$stream ? 'prohibited' : 'required', 'integer', 'exists:devices,device_id', Rule::unique('ant_cctv_streams', 'device_id')->ignore($stream?->id)],
            'nvr_device_id' => ['nullable', 'integer', 'exists:devices,device_id'],
            'host' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9.:_-]+$/'],
            'rtsp_port' => ['required', 'integer', 'between:1,65535'],
            'channel' => ['required', 'integer', 'between:1,256'],
            'rtsp_path' => ['nullable', 'string', 'max:255', 'regex:/^\/?[A-Za-z0-9._~\/?=&{}-]*$/'],
            'credential_id' => ['required', 'integer', 'exists:ant_video_credentials,id'],
        ], ['rtsp_path.regex' => 'Path RTSP hanya boleh huruf, angka, / . _ - ? = & dan {stream}.'], ['device_id' => 'kamera', 'credential_id' => 'kredensial']);

        // Host bawaan: IP NVR (bila lewat NVR) atau IP kamera
        if (blank($data['host'] ?? null)) {
            $src = Device::find($data['nvr_device_id'] ?? null) ?? ($stream?->device ?? Device::find($data['device_id'] ?? null));
            // Sama seperti poller LibreNMS: overwrite_ip bila ada, selain itu hostname yang dimasukkan saat menambah perangkat
            $data['host'] = $src?->overwrite_ip ?: $src?->hostname;
            if (blank($data['host'])) {
                throw ValidationException::withMessages(['host' => 'Isi host / IP NVR atau kamera.']);
            }
        }

        return $data + ['enabled' => $request->boolean('enabled', true), 'health_check' => $request->boolean('health_check', true)];
    }

    private function authorizeStream(CctvStream $stream): void
    {
        abort_unless($stream->device && Gate::allows('view', $stream->device), 403);
    }

    private function placeholder(string $text): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="640" height="360" viewBox="0 0 640 360"><rect width="640" height="360" fill="#1c1917"/>'
            . '<text x="320" y="185" fill="#a8a29e" font-family="sans-serif" font-size="22" text-anchor="middle">' . htmlspecialchars($text) . '</text></svg>';
    }
}
