<?php

namespace Antareja\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChangeLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'ant_change_logs';

    protected $guarded = ['id'];

    protected $casts = ['created_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
