<?php

namespace Antareja\Models\Concerns;

use Antareja\Services\AuditLogger;

/**
 * Mencatat create / update / delete model ke ant_audit_logs, lengkap dengan nilai sebelum & sesudah.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model): void {
            app(AuditLogger::class)->log('created', $model, $model->auditDescription('dibuat'), null, $model->auditValues($model->getAttributes()));
        });

        static::updated(function ($model): void {
            $changes = array_diff_key($model->getChanges(), array_flip(['updated_at']));
            if ($changes === []) {
                return;
            }
            $old = array_intersect_key($model->getRawOriginal(), $changes);
            app(AuditLogger::class)->log('updated', $model, $model->auditDescription('diubah'), $model->auditValues($old), $model->auditValues($changes));
        });

        static::deleted(function ($model): void {
            app(AuditLogger::class)->log('deleted', $model, $model->auditDescription('dihapus'), $model->auditValues($model->getRawOriginal()), null);
        });
    }

    /**
     * Ubah kolom IP biner (didaftarkan di $ipBinaryAttributes pada model) menjadi teks sebelum dicatat.
     */
    public function auditValues(array $values): array
    {
        foreach ($this->ipBinaryAttributes ?? [] as $key) {
            if (array_key_exists($key, $values)) {
                $values[$key] = \Antareja\Support\IpMath::toText($values[$key]);
            }
        }

        return $values;
    }

    public function auditDescription(string $verb): string
    {
        return sprintf('%s %s %s', $this->auditLabel(), $this->auditName(), $verb);
    }

    public function auditLabel(): string
    {
        return class_basename($this);
    }

    public function auditName(): string
    {
        return (string) $this->getKey();
    }
}
