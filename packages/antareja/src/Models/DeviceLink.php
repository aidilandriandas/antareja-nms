<?php

namespace Antareja\Models;

use Antareja\Models\Concerns\Auditable;
use Antareja\Support\Catalog;
use App\Models\Device;
use App\Models\Port;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Relasi fisik/logis antar perangkat (device mapping). Bukan topologi otomatis:
 * relasi berstatus "suggested" dari LLDP/CDP/FDB wajib diverifikasi engineer.
 * Konvensi: sisi A = upstream / parent, sisi B = downstream / child.
 */
class DeviceLink extends Model
{
    use Auditable;

    public const SUGGESTED = 'suggested';
    public const VERIFIED = 'verified';
    public const REJECTED = 'rejected';

    protected $table = 'ant_device_links';

    protected $fillable = [
        'a_device_id', 'a_port_id', 'b_device_id', 'b_port_id', 'link_type', 'notes',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
    ];

    public function aDevice(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'a_device_id', 'device_id');
    }

    public function bDevice(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'b_device_id', 'device_id');
    }

    public function aPort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'a_port_id', 'port_id');
    }

    public function bPort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'b_port_id', 'port_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by', 'user_id');
    }

    public function typeLabel(): string
    {
        return Catalog::label(Catalog::LINK_TYPES, $this->link_type);
    }

    public function isParentChild(): bool
    {
        return in_array($this->link_type, Catalog::PARENT_CHILD_LINK_TYPES, true);
    }

    public function auditType(): string
    {
        return 'device_link';
    }

    public function auditLabel(): string
    {
        return 'Mapping';
    }

    public function auditName(): string
    {
        $a = $this->aDevice?->displayName() ?? "#{$this->a_device_id}";
        $b = $this->bDevice?->displayName() ?? "#{$this->b_device_id}";

        return "{$a} → {$b} ({$this->link_type})";
    }
}
