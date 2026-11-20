<?php

namespace Antareja\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FiberTest extends Model
{
    protected $table = 'ant_fiber_tests';

    protected $fillable = ['core_id', 'tested_at', 'method', 'wavelength_nm', 'length_m', 'total_loss_db', 'result', 'events', 'tested_by'];

    protected $casts = ['tested_at' => 'datetime', 'length_m' => 'float', 'total_loss_db' => 'float'];

    public function core(): BelongsTo
    {
        return $this->belongsTo(FiberCore::class, 'core_id');
    }

    public function tester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tested_by', 'user_id');
    }
}
