<?php

namespace Antareja\Models;

use Illuminate\Database\Eloquent\Model;

class CapacityDaily extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $table = 'ant_capacity_daily';

    protected $guarded = [];

    protected $casts = ['day' => 'date'];
}
