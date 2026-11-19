<?php

namespace Antareja\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IpHistory extends Model
{
    public const UPDATED_AT = null;

    public const EVENTS = [
        'discovered' => 'Pertama terlihat',
        'assigned' => 'Dialokasikan',
        'updated' => 'Diubah',
        'released' => 'Dilepas',
        'mac_changed' => 'MAC berubah',
        'device_changed' => 'Perangkat berubah',
        'conflict' => 'Konflik terdeteksi',
        'resolved' => 'Konflik selesai',
    ];

    protected $table = 'ant_ip_history';

    protected $guarded = ['id'];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
