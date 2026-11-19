<?php

namespace Antareja\Models;

use Antareja\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vlan extends Model
{
    use Auditable;

    protected $table = 'ant_vlans';

    protected $fillable = ['site_id', 'vlan_number', 'name', 'purpose', 'description'];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function subnets(): HasMany
    {
        return $this->hasMany(Subnet::class);
    }

    public function label(): string
    {
        return "VLAN {$this->vlan_number} · {$this->name}" . ($this->site ? " ({$this->site->code})" : '');
    }

    public function auditType(): string
    {
        return 'vlan';
    }

    public function auditLabel(): string
    {
        return 'VLAN';
    }

    public function auditName(): string
    {
        return "{$this->vlan_number} {$this->name}";
    }
}
