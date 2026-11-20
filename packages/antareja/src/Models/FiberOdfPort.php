<?php

namespace Antareja\Models;

use App\Models\Device;
use App\Models\Port;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FiberOdfPort extends Model
{
    protected $table = 'ant_fiber_odf_ports';

    protected $fillable = ['odf_id', 'port_no', 'core_id', 'device_id', 'port_id', 'notes'];

    public function odf(): BelongsTo
    {
        return $this->belongsTo(FiberOdf::class, 'odf_id');
    }

    public function core(): BelongsTo
    {
        return $this->belongsTo(FiberCore::class, 'core_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id', 'device_id');
    }

    public function port(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'port_id', 'port_id');
    }

    public function label(): string
    {
        return ($this->odf?->name ?? 'ODF') . ' port ' . $this->port_no;
    }
}
