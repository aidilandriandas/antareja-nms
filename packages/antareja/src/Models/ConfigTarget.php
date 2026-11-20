<?php

namespace Antareja\Models;

use Antareja\Models\Concerns\Auditable;
use App\Models\Device;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConfigTarget extends Model
{
    use Auditable;

    protected $table = 'ant_config_targets';

    protected $primaryKey = 'device_id';

    public $incrementing = false;

    protected $fillable = ['device_id', 'profile', 'credential_id', 'host', 'interval_hours', 'enabled'];

    protected $casts = [
        'enabled' => 'boolean',
        'last_attempt_at' => 'datetime',
        'last_collected_at' => 'datetime',
        'last_changed_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id', 'device_id');
    }

    public function credential(): BelongsTo
    {
        return $this->belongsTo(ConfigCredential::class, 'credential_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ConfigVersion::class, 'device_id', 'device_id');
    }

    public function isDue(): bool
    {
        return $this->enabled && (! $this->last_attempt_at || $this->last_attempt_at->lte(now()->subHours(max(1, $this->interval_hours))->addMinutes(5)));
    }

    public function statusMeta(): array
    {
        return match ($this->last_status) {
            'ok' => ['label' => 'Berubah', 'tone' => 'info'],
            'unchanged' => ['label' => 'Tersimpan', 'tone' => 'ok'],
            'error' => ['label' => 'Gagal', 'tone' => 'crit'],
            'hostkey' => ['label' => 'Host key berubah', 'tone' => 'down'],
            default => ['label' => 'Belum diambil', 'tone' => 'unknown'],
        };
    }

    public function auditType(): string
    {
        return 'config_target';
    }

    public function auditLabel(): string
    {
        return 'Backup konfigurasi';
    }

    public function auditName(): string
    {
        return $this->device?->displayName() ?? "device #{$this->device_id}";
    }
}
