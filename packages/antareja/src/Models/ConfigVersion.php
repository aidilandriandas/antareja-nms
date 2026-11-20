<?php

namespace Antareja\Models;

use App\Models\Device;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class ConfigVersion extends Model
{
    public $timestamps = false;

    protected $table = 'ant_config_versions';

    protected $guarded = ['id'];

    protected $hidden = ['content'];

    protected $casts = [
        'collected_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'unauthorized' => 'boolean',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id', 'device_id');
    }

    public function change(): BelongsTo
    {
        return $this->belongsTo(Change::class);
    }

    public function acknowledger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by', 'user_id');
    }

    /** Simpan konfigurasi: gzip lalu enkripsi APP_KEY (konfigurasi berisi rahasia seperti community & hash password). */
    public static function pack(string $config): string
    {
        return Crypt::encryptString(base64_encode(gzcompress($config, 6)));
    }

    public function text(): string
    {
        return (string) gzuncompress(base64_decode(Crypt::decryptString($this->content)));
    }
}
