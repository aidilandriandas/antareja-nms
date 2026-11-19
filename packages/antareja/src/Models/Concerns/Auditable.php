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
            app(AuditLogger::class)->log('created', $model, $model->auditDescription('dibuat'), null, $model->getAttributes());
        });

        static::updated(function ($model): void {
            $changes = array_diff_key($model->getChanges(), array_flip(['updated_at']));
            if ($changes === []) {
                return;
            }
            $old = array_intersect_key($model->getOriginal(), $changes);
            app(AuditLogger::class)->log('updated', $model, $model->auditDescription('diubah'), $old, $changes);
        });

        static::deleted(function ($model): void {
            app(AuditLogger::class)->log('deleted', $model, $model->auditDescription('dihapus'), $model->getOriginal(), null);
        });
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
