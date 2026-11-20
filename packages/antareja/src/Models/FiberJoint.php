<?php

namespace Antareja\Models;

use Antareja\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Joint closure / ODP / handhole tempat kabel disambung (splice). */
class FiberJoint extends Model
{
    use Auditable;

    public const TYPES = ['closure' => 'Joint closure', 'odp' => 'ODP / FDT', 'splitter' => 'Splitter', 'handhole' => 'Handhole / manhole'];

    protected $table = 'ant_fiber_joints';

    protected $fillable = ['code', 'type', 'site_id', 'lat', 'lng', 'location', 'notes'];

    protected $casts = ['lat' => 'float', 'lng' => 'float'];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function splices(): HasMany
    {
        return $this->hasMany(FiberSplice::class, 'joint_id');
    }

    public function label(): string
    {
        return $this->code . ($this->location ? " ({$this->location})" : '');
    }

    public function auditType(): string
    {
        return 'fiber_joint';
    }

    public function auditLabel(): string
    {
        return 'Joint fiber';
    }

    public function auditName(): string
    {
        return $this->code;
    }
}
