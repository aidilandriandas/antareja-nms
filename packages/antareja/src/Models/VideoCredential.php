<?php

namespace Antareja\Models;

use Antareja\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Akun untuk menarik video dari NVR/kamera. Pakai akun viewer/operator (bukan admin).
 * Password terenkripsi APP_KEY, tidak pernah dikirim ke browser.
 */
class VideoCredential extends Model
{
    use Auditable;

    protected $table = 'ant_video_credentials';

    protected $fillable = ['name', 'username', 'secret'];

    protected $hidden = ['secret'];

    protected $casts = ['secret' => 'encrypted'];

    public function streams(): HasMany
    {
        return $this->hasMany(CctvStream::class, 'credential_id');
    }

    public function auditValues(array $values): array
    {
        if (array_key_exists('secret', $values)) {
            $values['secret'] = '••••••';
        }

        return $values;
    }

    public function auditType(): string
    {
        return 'video_credential';
    }

    public function auditLabel(): string
    {
        return 'Kredensial video';
    }

    public function auditName(): string
    {
        return $this->name;
    }
}
