<?php

namespace Antareja\Services;

use Antareja\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Pencatat audit log terpusat ANTAREJA. Kegagalan mencatat tidak boleh menggagalkan aksi pengguna.
 */
class AuditLogger
{
    /** Kolom yang tidak perlu dicatat. */
    private const IGNORED = ['created_at', 'updated_at', 'remember_token', 'password'];

    public function log(
        string $event,
        Model|string|null $subject = null,
        ?string $description = null,
        ?array $old = null,
        ?array $new = null,
        ?\Illuminate\Contracts\Auth\Authenticatable $user = null,
        ?string $username = null,
    ): void {
        try {
            $user ??= Auth::user();
            $request = app()->runningInConsole() ? null : request();

            [$type, $id] = match (true) {
                $subject instanceof Model => [$this->typeOf($subject), (string) $subject->getKey()],
                is_string($subject) => [$subject, null],
                default => [null, null],
            };

            AuditLog::create([
                'user_id' => $user?->getAuthIdentifier(),
                'username' => $username ?? $user?->username ?? (app()->runningInConsole() ? 'system' : null),
                'event' => $event,
                'auditable_type' => $type,
                'auditable_id' => $id,
                'description' => $description ? mb_substr($description, 0, 255) : null,
                'old_values' => $old ? $this->clean($old) : null,
                'new_values' => $new ? $this->clean($new) : null,
                'ip_address' => $request?->ip(),
                'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null,
            ]);
        } catch (\Throwable $e) {
            Log::error('ANTAREJA audit log gagal: ' . $e->getMessage());
        }
    }

    public function typeOf(Model $model): string
    {
        return match ($model::class) {
            \App\Models\Device::class => 'device',
            \App\Models\User::class => 'user',
            default => method_exists($model, 'auditType') ? $model->auditType() : class_basename($model),
        };
    }

    private function clean(array $values): array
    {
        $values = array_diff_key($values, array_flip(self::IGNORED));

        // Nilai biner (mis. alamat IP VARBINARY) diubah ke teks agar bisa disimpan sebagai JSON
        foreach ($values as $key => $value) {
            if (is_string($value) && ! mb_check_encoding($value, 'UTF-8')) {
                $values[$key] = (in_array(strlen($value), [4, 16], true) && ($ip = @inet_ntop($value))) ? $ip : '0x' . bin2hex($value);
            }
        }

        return $values;
    }
}
