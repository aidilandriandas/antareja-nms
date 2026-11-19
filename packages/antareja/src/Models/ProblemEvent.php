<?php

namespace Antareja\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProblemEvent extends Model
{
    public const UPDATED_AT = null;

    public const TYPES = [
        'opened' => 'Problem muncul', 'source_added' => 'Sumber tambahan', 'source_cleared' => 'Sumber pulih',
        'severity' => 'Severity berubah', 'suppressed' => 'Ditekan', 'unsuppressed' => 'Tidak lagi ditekan',
        'acknowledged' => 'Acknowledge', 'incident' => 'Incident', 'resolved' => 'Selesai', 'reopened' => 'Muncul lagi',
    ];

    protected $table = 'ant_problem_events';

    protected $guarded = ['id'];

    protected $casts = ['created_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
