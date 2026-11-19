<?php

namespace Antareja\Http\Controllers;

use Antareja\Models\Attachment;
use Antareja\Services\AttachmentService;
use Antareja\Services\AuditLogger;
use App\Models\Device;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function __construct(private readonly AttachmentService $attachments)
    {
    }

    public function store(Request $request, Device $device): RedirectResponse
    {
        Gate::authorize('view', $device);
        $request->validate([
            'file' => ['required', 'file', 'mimes:' . AttachmentService::ALLOWED_MIMES, 'max:' . AttachmentService::MAX_KB],
            'description' => ['nullable', 'string', 'max:255'],
        ], [
            'file.required' => 'Pilih file evidence yang akan diunggah.',
            'file.mimes' => 'Evidence harus berupa JPG, PNG, WEBP, atau PDF.',
            'file.max' => 'Ukuran evidence maksimal 10 MB.',
            'file.uploaded' => 'Upload gagal. Pastikan ukuran file tidak lebih dari 10 MB.',
        ]);

        $this->attachments->store('device', $device->device_id, $request->file('file'), $request->input('description'));

        return back()->with('antareja_success', 'Evidence diunggah.');
    }

    public function show(Attachment $attachment): StreamedResponse
    {
        $this->authorizeAttachment($attachment);
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        // Gambar & PDF ditampilkan inline; tipe lain diunduh. nosniff mencegah browser menebak tipe konten.
        $inline = $attachment->isImage() || $attachment->mime === 'application/pdf';

        return Storage::disk($attachment->disk)->response($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'",
        ], $inline ? 'inline' : 'attachment');
    }

    public function destroy(Attachment $attachment): RedirectResponse
    {
        $this->authorizeAttachment($attachment);
        $name = $attachment->original_name;
        $attachment->delete();
        app(AuditLogger::class)->log('deleted', $attachment->attachable_type,
            "Evidence \"{$name}\" dihapus dari {$attachment->attachable_type} #{$attachment->attachable_id}");

        return back()->with('antareja_success', "Evidence {$name} dihapus.");
    }

    private function authorizeAttachment(Attachment $attachment): void
    {
        if ($attachment->attachable_type === 'device') {
            $device = Device::find($attachment->attachable_id);
            abort_unless($device && Gate::allows('view', $device), 403);
        }
    }
}
