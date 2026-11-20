<?php

namespace Antareja\Models;

use Illuminate\Database\Eloquent\Model;

class WorkOrderMaterial extends Model
{
    protected $table = 'ant_work_order_materials';

    protected $fillable = ['item', 'quantity', 'unit', 'notes'];

    protected $casts = ['quantity' => 'float'];
}
