<?php

namespace Antareja\Models;

use App\Models\Device;
use App\Models\Port;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu alamat IP yang tercatat di IPAM. Alamat yang tidak punya record dianggap "available".
 * Perubahan dicatat di ant_ip_history (bukan audit log umum) agar histori per IP lengkap.
 */
class IpAddress extends Model
{
    public const STATUSES = [
        'used' => ['label' => 'Used', 'class' => 'label-primary', 'color' => '#2563eb'],
        'static' => ['label' => 'Static', 'class' => 'label-success', 'color' => '#059669'],
        'reserved' => ['label' => 'Reserved', 'class' => 'label-warning', 'color' => '#d97706'],
        'dhcp' => ['label' => 'DHCP', 'class' => 'label-info', 'color' => '#0891b2'],
    ];

    /** Status yang ditentukan engineer dan tidak boleh ditimpa sinkronisasi otomatis. */
    public const MANUAL_STATUSES = ['static', 'reserved', 'dhcp'];

    public const SOURCES = [
        'manual' => 'Manual',
        'interface' => 'Interface perangkat',
        'arp' => 'Tabel ARP',
        'device' => 'IP manajemen device',
    ];

    protected $table = 'ant_ip_addresses';

    protected $guarded = ['id'];

    protected $casts = [
        'conflict' => 'boolean',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function subnet(): BelongsTo
    {
        return $this->belongsTo(Subnet::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id', 'device_id');
    }

    public function port(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'port_id', 'port_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by', 'user_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(IpHistory::class)->latest('created_at')->latest('id');
    }

    public function statusMeta(): array
    {
        return self::STATUSES[$this->status] ?? ['label' => $this->status, 'class' => 'label-default', 'color' => '#6b7280'];
    }
}
