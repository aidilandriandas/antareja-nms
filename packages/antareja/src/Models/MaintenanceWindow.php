<?php

namespace Antareja\Models;

use Antareja\Models\Concerns\Auditable;
use App\Models\Device;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MaintenanceWindow extends Model
{
    use Auditable;

    public const TYPES = [
        'preventive' => 'Preventive (terjadwal)',
        'corrective' => 'Corrective (perbaikan)',
        'change' => 'Change (perubahan konfigurasi / perangkat)',
    ];

    public const STATUSES = [
        'planned' => ['label' => 'Terjadwal', 'tone' => 'info'],
        'in_progress' => ['label' => 'Berlangsung', 'tone' => 'warn'],
        'completed' => ['label' => 'Selesai', 'tone' => 'ok'],
        'cancelled' => ['label' => 'Dibatalkan', 'tone' => 'unknown'],
    ];

    public const RESULTS = ['success' => 'Berhasil', 'partial' => 'Sebagian', 'failed' => 'Gagal'];

    protected $table = 'ant_maintenance_windows';

    protected $fillable = ['title', 'type', 'starts_at', 'ends_at', 'description', 'site_id', 'pic_user_id', 'pic_name', 'exclude_from_sla'];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'completed_at' => 'datetime',
        'exclude_from_sla' => 'boolean',
    ];

    public function devices(): BelongsToMany
    {
        return $this->belongsToMany(Device::class, 'ant_maintenance_devices', 'window_id', 'device_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function picUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_user_id', 'user_id');
    }

    /** Status efektif: planned yang sudah dimulai dianggap berlangsung. */
    public function effectiveStatus(): string
    {
        if ($this->status === 'planned' && $this->starts_at->isPast() && $this->ends_at->isFuture()) {
            return 'in_progress';
        }

        return $this->status;
    }

    public function statusMeta(): array
    {
        return self::STATUSES[$this->effectiveStatus()] ?? ['label' => $this->status, 'tone' => 'unknown'];
    }

    public function picLabel(): ?string
    {
        return $this->picUser?->realname ?: $this->picUser?->username ?: $this->pic_name;
    }

    public function auditType(): string
    {
        return 'maintenance';
    }

    public function auditLabel(): string
    {
        return 'Maintenance';
    }

    public function auditName(): string
    {
        return $this->title;
    }
}
