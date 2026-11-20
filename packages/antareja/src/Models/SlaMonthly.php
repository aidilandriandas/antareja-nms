<?php

namespace Antareja\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Hasil SLA bulanan yang dibekukan (agar laporan bulan lalu tetap sama walau data outage dibersihkan). */
class SlaMonthly extends Model
{
    public $timestamps = false;

    protected $table = 'ant_sla_monthly';

    protected $guarded = ['id'];

    protected $casts = [
        'month' => 'date',
        'computed_at' => 'datetime',
        'availability' => 'float',
        'sla_target' => 'float',
        'met' => 'boolean',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
