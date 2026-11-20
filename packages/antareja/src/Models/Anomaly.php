<?php

namespace Antareja\Models;

use App\Models\Device;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Anomaly extends Model
{
    public $timestamps = false;

    public const KINDS = [
        'latency' => 'Latency naik tidak wajar',
        'loss' => 'Packet loss tidak wajar',
        'traffic_drop' => 'Traffic turun drastis',
        'traffic_spike' => 'Traffic melonjak',
        'flapping' => 'Perangkat flapping (naik-turun)',
    ];

    protected $table = 'ant_anomalies';

    protected $guarded = ['id'];

    protected $casts = ['detected_at' => 'datetime', 'last_seen_at' => 'datetime', 'closed_at' => 'datetime'];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id', 'device_id');
    }

    public function url(): ?string
    {
        [$type, $id] = array_pad(explode(':', $this->ref, 2), 2, null);

        return match ($type) {
            'quality' => route('antareja.quality.show', $id),
            'capacity' => route('antareja.capacity.show', $id),
            'device' => route('antareja.inventory.show', $id),
            default => null,
        };
    }
}
