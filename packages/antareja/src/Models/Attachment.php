<?php

namespace Antareja\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * File evidence (foto instalasi, label serial, dokumen garansi, dsb).
 */
class Attachment extends Model
{
    protected $table = 'ant_attachments';

    protected $guarded = ['id'];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by', 'user_id');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }

    public function humanSize(): string
    {
        $size = $this->size;
        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($size < 1024) {
                return round($size, 1) . ' ' . $unit;
            }
            $size /= 1024;
        }

        return round($size, 1) . ' TB';
    }

    protected static function booted(): void
    {
        static::deleted(function (Attachment $attachment): void {
            Storage::disk($attachment->disk)->delete($attachment->path);
        });
    }
}
