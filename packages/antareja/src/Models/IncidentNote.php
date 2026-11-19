<?php

namespace Antareja\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncidentNote extends Model
{
    public const UPDATED_AT = null;

    public const TYPES = [
        'note' => 'Work note', 'status' => 'Status', 'assignment' => 'Penugasan', 'escalation' => 'Eskalasi',
        'link' => 'Problem terkait', 'system' => 'Sistem',
    ];

    protected $table = 'ant_incident_notes';

    protected $guarded = ['id'];

    protected $casts = ['created_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
