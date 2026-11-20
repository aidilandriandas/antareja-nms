<?php

namespace Antareja\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiReport extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'ant_ai_reports';

    protected $guarded = ['id'];

    protected $casts = ['created_at' => 'datetime', 'fallback' => 'boolean'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }
}
