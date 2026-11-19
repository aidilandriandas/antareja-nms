<?php

namespace Antareja\Models;

use Antareja\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Area extends Model
{
    use Auditable;

    protected $table = 'ant_areas';

    protected $fillable = ['site_id', 'name', 'code', 'description'];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function auditType(): string
    {
        return 'area';
    }

    public function auditLabel(): string
    {
        return 'Area';
    }

    public function auditName(): string
    {
        return $this->name;
    }
}
