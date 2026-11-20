<?php

namespace Antareja\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FiberSplice extends Model
{
    protected $table = 'ant_fiber_splices';

    protected $fillable = ['joint_id', 'core_a_id', 'core_b_id', 'loss_db'];

    protected $casts = ['loss_db' => 'float'];

    public function joint(): BelongsTo
    {
        return $this->belongsTo(FiberJoint::class, 'joint_id');
    }

    public function coreA(): BelongsTo
    {
        return $this->belongsTo(FiberCore::class, 'core_a_id');
    }

    public function coreB(): BelongsTo
    {
        return $this->belongsTo(FiberCore::class, 'core_b_id');
    }
}
