<?php

namespace Antareja\Services;

use Antareja\Models\Attachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Simpan evidence di disk "antareja" (/data/antareja di container → ikut volume & backup).
 */
class AttachmentService
{
    public const DISK = 'antareja';

    /** Ekstensi yang diizinkan untuk evidence. */
    public const ALLOWED_MIMES = 'jpg,jpeg,png,webp,pdf';

    public const MAX_KB = 10240;

    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function store(string $type, string|int $id, UploadedFile $file, ?string $description = null): Attachment
    {
        $extension = strtolower($file->extension() ?: $file->getClientOriginalExtension());
        $path = $file->storeAs(
            sprintf('evidence/%s/%s/%s', $type, date('Y/m'), Str::slug((string) $id)),
            Str::uuid()->toString() . '.' . $extension,
            self::DISK,
        );

        $attachment = Attachment::create([
            'attachable_type' => $type,
            'attachable_id' => (string) $id,
            'disk' => self::DISK,
            'path' => $path,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'mime' => $file->getMimeType() ?: 'application/octet-stream',
            'size' => $file->getSize(),
            'description' => $description ? mb_substr($description, 0, 255) : null,
            'uploaded_by' => Auth::id(),
        ]);

        $this->audit->log('uploaded', $type, "Evidence \"{$attachment->original_name}\" diunggah ke {$type} #{$id}");

        return $attachment;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Attachment>
     */
    public function for(string $type, string|int $id)
    {
        return Attachment::where('attachable_type', $type)->where('attachable_id', (string) $id)->latest()->get();
    }

    public static function root(): string
    {
        return is_dir('/data/antareja') ? '/data/antareja' : storage_path('app/antareja');
    }
}
