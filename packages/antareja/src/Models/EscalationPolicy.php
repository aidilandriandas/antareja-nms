<?php

namespace Antareja\Models;

use Antareja\Models\Concerns\Auditable;
use Antareja\Support\Severity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kebijakan eskalasi: incident dengan severity ≥ min_severity (dan site yang cocok) dinotifikasi bertahap
 * lewat transport alert poller (Telegram, email, …) sampai di-acknowledge.
 */
class EscalationPolicy extends Model
{
    use Auditable;

    protected $table = 'ant_escalation_policies';

    protected $fillable = ['name', 'min_severity', 'site_id', 'active', 'auto_create_incident', 'auto_create_after_minutes', 'notify_resolved', 'steps'];

    protected $casts = [
        'active' => 'boolean',
        'auto_create_incident' => 'boolean',
        'notify_resolved' => 'boolean',
        'steps' => 'array',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function matches(string $incidentSeverity, ?int $siteId): bool
    {
        return $this->active
            && Severity::incidentRank($incidentSeverity) >= Severity::incidentRank($this->min_severity)
            && ($this->site_id === null || $this->site_id === $siteId);
    }

    /** Kebijakan yang berlaku: yang khusus site didahulukan, lalu yang global. */
    public static function for(string $incidentSeverity, ?int $siteId): ?self
    {
        return self::where('active', true)->orderByRaw('site_id IS NULL')->orderBy('id')->get()
            ->first(fn (self $p) => $p->matches($incidentSeverity, $siteId));
    }

    public function auditType(): string
    {
        return 'escalation_policy';
    }

    public function auditLabel(): string
    {
        return 'Kebijakan eskalasi';
    }

    public function auditName(): string
    {
        return $this->name;
    }
}
