<?php

namespace Antareja\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderLog extends Model
{
    public const UPDATED_AT = null;

    public const TYPES = ['note' => 'Catatan', 'status' => 'Status', 'checklist' => 'Checklist', 'material' => 'Material', 'assignment' => 'Penugasan', 'system' => 'Sistem'];

    protected $table = 'ant_work_order_logs';

    protected $guarded = ['id'];

    protected $casts = ['created_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
