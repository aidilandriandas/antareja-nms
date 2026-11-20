<?php

namespace Antareja\Models;

use Antareja\Models\Concerns\Auditable;
use App\Models\Device;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Change request (RFC). Perubahan jaringan/konfigurasi harus diajukan, disetujui orang lain, dijadwalkan
 * (otomatis menjadi jendela maintenance), lalu dicatat hasilnya. ANTAREJA tidak pernah menerapkan perubahan
 * ke perangkat — pelaksanaan tetap oleh engineer.
 */
class Change extends Model
{
    use Auditable;

    public const TYPES = [
        'standard' => 'Standard (rutin, risiko rendah)',
        'normal' => 'Normal (perlu persetujuan)',
        'emergency' => 'Emergency (darurat)',
    ];

    public const RISKS = [
        'low' => ['label' => 'Rendah', 'tone' => 'info'],
        'medium' => ['label' => 'Sedang', 'tone' => 'warn'],
        'high' => ['label' => 'Tinggi', 'tone' => 'crit'],
    ];

    public const STATUSES = [
        'draft' => ['label' => 'Draft', 'tone' => 'unknown'],
        'submitted' => ['label' => 'Menunggu persetujuan', 'tone' => 'warn'],
        'approved' => ['label' => 'Disetujui', 'tone' => 'info'],
        'rejected' => ['label' => 'Ditolak', 'tone' => 'crit'],
        'in_progress' => ['label' => 'Dilaksanakan', 'tone' => 'warn'],
        'completed' => ['label' => 'Selesai', 'tone' => 'ok'],
        'failed' => ['label' => 'Gagal / rollback', 'tone' => 'crit'],
        'cancelled' => ['label' => 'Dibatalkan', 'tone' => 'unknown'],
    ];

    public const RESULTS = ['success' => 'Berhasil', 'partial' => 'Sebagian', 'failed' => 'Gagal', 'rolled_back' => 'Di-rollback'];

    /** Status di mana perubahan konfigurasi pada perangkat terkait dianggap sah (dalam jendela waktunya). */
    public const AUTHORIZING = ['approved', 'in_progress', 'completed', 'failed'];

    protected $table = 'ant_changes';

    protected $fillable = ['title', 'type', 'risk', 'description', 'impact', 'rollback_plan', 'test_plan', 'site_id',
        'planned_start', 'planned_end', 'exclude_from_sla', 'review_notes'];

    protected $casts = [
        'planned_start' => 'datetime',
        'planned_end' => 'datetime',
        'approved_at' => 'datetime',
        'implemented_at' => 'datetime',
        'exclude_from_sla' => 'boolean',
    ];

    public function devices(): BelongsToMany
    {
        return $this->belongsToMany(Device::class, 'ant_change_devices', 'change_id', 'device_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by', 'user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by', 'user_id');
    }

    public function maintenance(): BelongsTo
    {
        return $this->belongsTo(MaintenanceWindow::class, 'maintenance_window_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ChangeLog::class)->latest('id');
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    public function statusMeta(): array
    {
        return self::STATUSES[$this->status] ?? ['label' => $this->status, 'tone' => 'unknown'];
    }

    public function riskMeta(): array
    {
        return self::RISKS[$this->risk] ?? ['label' => $this->risk, 'tone' => 'unknown'];
    }

    public function log(string $type, ?string $body, ?string $old = null, ?string $new = null, ?int $userId = null): ChangeLog
    {
        return $this->logs()->create(['type' => $type, 'body' => $body, 'old_value' => $old, 'new_value' => $new, 'user_id' => $userId]);
    }

    public function auditType(): string
    {
        return 'change';
    }

    public function auditLabel(): string
    {
        return 'Change request';
    }

    public function auditName(): string
    {
        return $this->number ?? "#{$this->id}";
    }
}
