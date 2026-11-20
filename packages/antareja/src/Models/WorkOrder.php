<?php

namespace Antareja\Models;

use Antareja\Models\Concerns\Auditable;
use App\Models\Device;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Work order lapangan: penugasan pekerjaan fisik (perbaikan, instalasi, relokasi, inspeksi, penarikan kabel)
 * dengan checklist, pemakaian material, evidence, dan verifikasi supervisor.
 */
class WorkOrder extends Model
{
    use Auditable;

    public const TYPES = [
        'corrective' => 'Perbaikan',
        'preventive' => 'Preventive / PM',
        'installation' => 'Instalasi baru',
        'relocation' => 'Relokasi',
        'inspection' => 'Inspeksi',
        'cabling' => 'Kabel / fiber',
    ];

    public const PRIORITIES = [
        'urgent' => ['label' => 'Urgent', 'tone' => 'down', 'hours' => 4],
        'high' => ['label' => 'High', 'tone' => 'crit', 'hours' => 24],
        'normal' => ['label' => 'Normal', 'tone' => 'warn', 'hours' => 72],
        'low' => ['label' => 'Low', 'tone' => 'info', 'hours' => 168],
    ];

    public const STATUSES = [
        'open' => ['label' => 'Open', 'tone' => 'crit'],
        'assigned' => ['label' => 'Ditugaskan', 'tone' => 'warn'],
        'in_progress' => ['label' => 'Dikerjakan', 'tone' => 'warn'],
        'on_hold' => ['label' => 'Ditahan', 'tone' => 'info'],
        'done' => ['label' => 'Selesai', 'tone' => 'ok'],
        'verified' => ['label' => 'Terverifikasi', 'tone' => 'ok'],
        'cancelled' => ['label' => 'Dibatalkan', 'tone' => 'unknown'],
    ];

    public const TRANSITIONS = [
        'open' => ['assigned', 'in_progress', 'cancelled'],
        'assigned' => ['in_progress', 'on_hold', 'cancelled'],
        'in_progress' => ['on_hold', 'done'],
        'on_hold' => ['in_progress', 'cancelled'],
        'done' => ['verified', 'in_progress'],
        'verified' => [],
        'cancelled' => [],
    ];

    public const ACTIVE = ['open', 'assigned', 'in_progress', 'on_hold'];

    /** Checklist bawaan per jenis pekerjaan (bisa diubah per work order). */
    public const CHECKLISTS = [
        'corrective' => ['Cek kondisi fisik & catu daya', 'Identifikasi penyebab', 'Perbaikan / penggantian', 'Uji fungsi (ping, link up, traffic)', 'Foto sesudah pekerjaan'],
        'preventive' => ['Bersihkan perangkat & rak', 'Cek suhu & ventilasi', 'Cek UPS / baterai', 'Cek kabel & label', 'Catat temuan'],
        'installation' => ['Pasang & rapikan perangkat', 'Label perangkat & kabel', 'Konfigurasi dasar sesuai standar', 'Daftarkan di inventaris ANTAREJA', 'Uji koneksi & monitoring muncul'],
        'relocation' => ['Koordinasi downtime dengan pengguna', 'Lepas & pindahkan perangkat', 'Pasang di lokasi baru', 'Perbarui site/area di inventaris', 'Uji koneksi'],
        'inspection' => ['Cek fisik perangkat', 'Cek kabel & konektor', 'Foto kondisi', 'Catat temuan'],
        'cabling' => ['Tarik / sambung kabel', 'Splicing & ukur loss', 'Label kedua ujung', 'Uji OTDR / power meter', 'Perbarui data fiber di ANTAREJA'],
    ];

    protected $table = 'ant_work_orders';

    protected $fillable = ['title', 'type', 'priority', 'description', 'site_id', 'area_id', 'device_id', 'incident_id',
        'maintenance_window_id', 'change_id', 'assigned_to', 'team', 'due_at', 'result_notes', 'checklist'];

    protected $casts = [
        'due_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'verified_at' => 'datetime',
        'checklist' => 'array',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id', 'device_id');
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    public function maintenance(): BelongsTo
    {
        return $this->belongsTo(MaintenanceWindow::class, 'maintenance_window_id');
    }

    public function change(): BelongsTo
    {
        return $this->belongsTo(Change::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to', 'user_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by', 'user_id');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(WorkOrderMaterial::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(WorkOrderLog::class)->latest('id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereIn('status', self::ACTIVE);
    }

    public function isOverdue(): bool
    {
        return $this->due_at && in_array($this->status, self::ACTIVE, true) && $this->due_at->isPast();
    }

    public function statusMeta(): array
    {
        return self::STATUSES[$this->status] ?? ['label' => $this->status, 'tone' => 'unknown'];
    }

    public function priorityMeta(): array
    {
        return self::PRIORITIES[$this->priority] ?? ['label' => $this->priority, 'tone' => 'unknown', 'hours' => 72];
    }

    public function assigneeLabel(): ?string
    {
        return $this->assignee?->realname ?: $this->assignee?->username ?: $this->team;
    }

    /** @return array{done:int, total:int} */
    public function checklistProgress(): array
    {
        $items = $this->checklist ?? [];

        return ['done' => count(array_filter($items, fn ($i) => ! empty($i['done']))), 'total' => count($items)];
    }

    public function log(string $type, ?string $body, ?string $old = null, ?string $new = null, ?int $userId = null): WorkOrderLog
    {
        return $this->logs()->create(['type' => $type, 'body' => $body, 'old_value' => $old, 'new_value' => $new, 'user_id' => $userId]);
    }

    public function auditType(): string
    {
        return 'work_order';
    }

    public function auditLabel(): string
    {
        return 'Work order';
    }

    public function auditName(): string
    {
        return $this->number ?? "#{$this->id}";
    }
}
