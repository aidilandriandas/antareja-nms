<?php

namespace Antareja\Models;

use Illuminate\Database\Eloquent\Model;

class TriggerSample extends Model
{
    public $timestamps = false;

    protected $table = 'ant_trigger_samples';

    protected $guarded = ['id'];

    protected $casts = ['measured_at' => 'datetime', 'value' => 'float'];
}
