<?php

namespace Antareja\Models;

use Antareja\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** ODF / patch panel fiber di rak. */
class FiberOdf extends Model
{
    use Auditable;

    protected $table = 'ant_fiber_odfs';

    protected $fillable = ['name', 'site_id', 'area_id', 'rack', 'port_count', 'connector', 'notes'];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function ports(): HasMany
    {
        return $this->hasMany(FiberOdfPort::class, 'odf_id')->orderBy('port_no');
    }

    public function label(): string
    {
        return trim(($this->site?->code ? $this->site->code . ' · ' : '') . $this->name);
    }

    public function auditType(): string
    {
        return 'fiber_odf';
    }

    public function auditLabel(): string
    {
        return 'ODF';
    }

    public function auditName(): string
    {
        return $this->name;
    }
}
