<?php

namespace Antareja\Models;

use Antareja\Models\Concerns\Auditable;
use Antareja\Support\Severity;
use App\Models\Device;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Incident extends Model
{
    use Auditable;

    public const STATUSES = [
        'open' => ['label' => 'Open', 'tone' => 'crit'],
        'acknowledged' => ['label' => 'Acknowledged', 'tone' => 'warn'],
        'in_progress' => ['label' => 'In Progress', 'tone' => 'warn'],
        'pending' => ['label' => 'Pending', 'tone' => 'info'],
        'resolved' => ['label' => 'Resolved', 'tone' => 'ok'],
        'closed' => ['label' => 'Closed', 'tone' => 'unknown'],
    ];

    /** Transisi status yang diizinkan. */
    public const TRANSITIONS = [
        'open' => ['acknowledged', 'in_progress', 'resolved'],
        'acknowledged' => ['in_progress', 'pending', 'resolved'],
        'in_progress' => ['pending', 'resolved'],
        'pending' => ['in_progress', 'resolved'],
        'resolved' => ['closed', 'in_progress'],
        'closed' => ['in_progress'],
    ];

    public const ACTIVE = ['open', 'acknowledged', 'in_progress', 'pending'];

    protected $table = 'ant_incidents';

    protected $fillable = ['title', 'description', 'severity', 'device_id', 'site_id', 'assigned_to', 'pic_name', 'root_cause', 'corrective_action'];

    protected $casts = [
        'started_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
        'resolved_notified' => 'boolean',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id', 'device_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to', 'user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    public function problems(): HasMany
    {
        return $this->hasMany(Problem::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(IncidentNote::class)->latest('created_at')->latest('id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereIn('status', self::ACTIVE);
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE, true);
    }

    public function severityMeta(): array
    {
        return Severity::incident($this->severity);
    }

    public function statusMeta(): array
    {
        return self::STATUSES[$this->status] ?? ['label' => $this->status, 'tone' => 'unknown'];
    }

    public function picLabel(): ?string
    {
        return $this->assignee?->realname ?: $this->assignee?->username ?: $this->pic_name;
    }

    /** Detik sampai acknowledge / resolve (untuk MTTA / MTTR). */
    public function secondsToAck(): ?int
    {
        return $this->acknowledged_at ? max(0, $this->acknowledged_at->getTimestamp() - $this->started_at->getTimestamp()) : null;
    }

    public function secondsToResolve(): ?int
    {
        return $this->resolved_at ? max(0, $this->resolved_at->getTimestamp() - $this->started_at->getTimestamp()) : null;
    }

    public function note(string $type, ?string $body, ?string $old = null, ?string $new = null, ?int $userId = null): IncidentNote
    {
        return $this->notes()->create(['type' => $type, 'body' => $body, 'old_value' => $old, 'new_value' => $new, 'user_id' => $userId]);
    }

    public function auditType(): string
    {
        return 'incident';
    }

    public function auditLabel(): string
    {
        return 'Incident';
    }

    public function auditName(): string
    {
        return $this->number ?? "#{$this->id}";
    }
}
