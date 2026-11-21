<?php

namespace Antareja\Models;

use Antareja\Models\Concerns\Auditable;
use App\Models\Device;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class QualityTarget extends Model
{
    use Auditable;

    public const STATUS = [
        'ok' => ['label' => 'Baik', 'class' => 'label-success', 'tone' => 'ok'],
        'warning' => ['label' => 'Menurun', 'class' => 'label-warning', 'tone' => 'warn'],
        'critical' => ['label' => 'Buruk', 'class' => 'label-danger', 'tone' => 'crit'],
        'down' => ['label' => 'Putus', 'class' => 'label-danger', 'tone' => 'down'],
        'unknown' => ['label' => 'Belum ada data', 'class' => 'label-default', 'tone' => 'unknown'],
        'stale' => ['label' => 'Tidak diperbarui', 'class' => 'label-default', 'tone' => 'unknown'],
    ];

    /** Hasil dianggap usang bila tidak diukur > 15 menit (probe berjalan tiap 5 menit). */
    public const STALE_MINUTES = 15;

    protected $table = 'ant_quality_targets';

    protected $fillable = [
        'name', 'host', 'device_id', 'site_id', 'group_name', 'description', 'pings',
        'latency_warn_ms', 'latency_crit_ms', 'loss_warn_pct', 'loss_crit_pct', 'jitter_warn_ms', 'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'loss_warn_pct' => 'float',
        'loss_crit_pct' => 'float',
        'last_checked_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id', 'device_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function samples(): HasMany
    {
        return $this->hasMany(QualitySample::class, 'target_id');
    }

    public function latestSample(): HasOne
    {
        return $this->hasOne(QualitySample::class, 'target_id')->latestOfMany('measured_at');
    }

    /** Status dari satu sampel berdasarkan ambang target. */
    public function evaluate(?QualitySample $s): string
    {
        if (! $s) {
            return 'unknown';
        }
        if ($s->received === 0) {
            return 'down';
        }
        if ($s->loss_pct >= $this->loss_crit_pct || $s->median_ms >= $this->latency_crit_ms) {
            return 'critical';
        }
        if ($s->loss_pct >= $this->loss_warn_pct || $s->median_ms >= $this->latency_warn_ms || ($s->jitter_ms ?? 0) >= $this->jitter_warn_ms) {
            return 'warning';
        }

        return 'ok';
    }

    public function statusMeta(?string $status = null): array
    {
        if ($status === null && $this->isStale()) {
            return self::STATUS['stale'];
        }

        return self::STATUS[$status ?? $this->last_status ?? 'unknown'] ?? self::STATUS['unknown'];
    }

    /** Sudah pernah diukur, tetapi hasil terakhir lebih tua dari STALE_MINUTES (probe/penjadwal berhenti). */
    public function isStale(): bool
    {
        return $this->enabled && $this->last_checked_at !== null && $this->last_checked_at->lt(now()->subMinutes(self::STALE_MINUTES));
    }

    public function auditType(): string
    {
        return 'quality_target';
    }

    public function auditLabel(): string
    {
        return 'Target kualitas';
    }

    public function auditName(): string
    {
        return "{$this->name} ({$this->host})";
    }
}
