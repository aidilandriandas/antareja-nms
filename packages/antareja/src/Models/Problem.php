<?php

namespace Antareja\Models;

use Antareja\Support\Severity;
use App\Models\Device;
use App\Models\Port;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Problem = satu masalah nyata. Beberapa event dari sumber berbeda (device down, alert LibreNMS, Quality Probe)
 * untuk masalah yang sama digabung lewat correlation_key; event aslinya disimpan sebagai ProblemSource.
 */
class Problem extends Model
{
    public const OPEN = 'problem';
    public const RESOLVED = 'resolved';

    public const CLASSES = [
        'device_down' => 'Perangkat down',
        'port_down' => 'Port uplink down',
        'quality' => 'Kualitas link',
        'ip_conflict' => 'Konflik IP',
        'alert' => 'Alert rule',
        'trigger' => 'Trigger expression',
        'config_change' => 'Perubahan konfigurasi',
        'anomaly' => 'Anomali statistik',
    ];

    public const SUPPRESSION = [
        'parent' => 'Akibat perangkat induk down',
        'maintenance' => 'Dalam jendela maintenance',
    ];

    protected $table = 'ant_problems';

    protected $guarded = ['id'];

    protected $casts = [
        'started_at' => 'datetime',
        'resolved_at' => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id', 'device_id');
    }

    public function port(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'port_id', 'port_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function sources(): HasMany
    {
        return $this->hasMany(ProblemSource::class)->orderByDesc('active')->orderBy('first_seen_at');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ProblemEvent::class)->latest('created_at')->latest('id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_problem_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_problem_id');
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    public function acknowledger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by', 'user_id');
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->where('status', self::OPEN);
    }

    public function isOpen(): bool
    {
        return $this->status === self::OPEN;
    }

    public function severityMeta(): array
    {
        return Severity::problem($this->severity);
    }

    public function duration(): string
    {
        $end = $this->resolved_at ?? now();

        return \LibreNMS\Util\Time::formatInterval(max(0, $end->getTimestamp() - $this->started_at->getTimestamp()), true) ?: '0s';
    }

    public function log(string $type, string $message, ?int $userId = null): void
    {
        $this->events()->create(['type' => $type, 'message' => mb_substr($message, 0, 255), 'user_id' => $userId]);
    }
}
