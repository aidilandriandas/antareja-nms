<?php

namespace Antareja\Models;

use Antareja\Models\Concerns\Auditable;
use Antareja\Support\Catalog;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Site extends Model
{
    use Auditable;

    protected $table = 'ant_sites';

    protected $fillable = [
        'code', 'name', 'type', 'description', 'address', 'latitude', 'longitude',
        'location_id', 'pic_user_id', 'pic_name', 'pic_phone', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function areas(): HasMany
    {
        return $this->hasMany(Area::class)->orderBy('name');
    }

    public function devices(): HasMany
    {
        return $this->hasMany(DeviceDetail::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function picUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_user_id', 'user_id');
    }

    public function typeLabel(): string
    {
        return Catalog::label(Catalog::SITE_TYPES, $this->type);
    }

    public function picLabel(): ?string
    {
        return $this->picUser?->realname ?: $this->picUser?->username ?: $this->pic_name;
    }

    public function auditType(): string
    {
        return 'site';
    }

    public function auditLabel(): string
    {
        return 'Site';
    }

    public function auditName(): string
    {
        return "{$this->code} ({$this->name})";
    }
}
