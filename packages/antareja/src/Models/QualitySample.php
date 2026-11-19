<?php

namespace Antareja\Models;

use Illuminate\Database\Eloquent\Model;

class QualitySample extends Model
{
    public $timestamps = false;

    protected $table = 'ant_quality_samples';

    protected $guarded = ['id'];

    protected $casts = [
        'measured_at' => 'datetime',
        'loss_pct' => 'float',
        'min_ms' => 'float', 'p10_ms' => 'float', 'p25_ms' => 'float', 'median_ms' => 'float', 'avg_ms' => 'float',
        'p75_ms' => 'float', 'p90_ms' => 'float', 'max_ms' => 'float', 'jitter_ms' => 'float', 'stddev_ms' => 'float',
    ];
}
