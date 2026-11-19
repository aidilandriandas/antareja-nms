<?php

namespace Antareja\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'ant_audit_logs';

    protected $guarded = ['id'];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    public const EVENTS = [
        'created' => 'Dibuat',
        'updated' => 'Diubah',
        'deleted' => 'Dihapus',
        'verified' => 'Diverifikasi',
        'rejected' => 'Ditolak',
        'retired' => 'Dipensiunkan',
        'restored' => 'Dikembalikan',
        'uploaded' => 'Evidence diunggah',
        'login' => 'Login',
        'logout' => 'Logout',
        'login_failed' => 'Login gagal',
        'sync' => 'Sinkronisasi',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
