<?php

namespace Antareja\Models;

use App\Models\Device;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CapacityItem extends Model
{
    public const TYPES = ['port' => 'Link / port', 'storage' => 'Storage', 'subnet' => 'Pool IP'];

    protected $table = 'ant_capacity_items';

    protected $fillable = ['type', 'ref_id', 'device_id', 'label', 'capacity', 'warn_pct', 'crit_pct', 'enabled', 'auto'];

    protected $casts = [
        'enabled' => 'boolean',
        'auto' => 'boolean',
        'forecast_warn_on' => 'date',
        'forecast_crit_on' => 'date',
        'computed_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id', 'device_id');
    }

    public function daily(): HasMany
    {
        return $this->hasMany(CapacityDaily::class, 'item_id')->orderBy('day');
    }

    public function tone(): string
    {
        $v = $this->current_pct;

        return $v === null ? 'unknown' : ($v >= $this->crit_pct ? 'crit' : ($v >= $this->warn_pct ? 'warn' : 'ok'));
    }

    public function capacityLabel(): string
    {
        if ($this->capacity === null) {
            return '—';
        }

        return match ($this->type) {
            'port' => \LibreNMS\Util\Number::formatSi($this->capacity, 0, 0, 'bps'),
            'storage' => \LibreNMS\Util\Number::formatBi($this->capacity, 1, 0, 'B'),
            default => number_format($this->capacity, 0, ',', '.') . ' IP',
        };
    }
}
