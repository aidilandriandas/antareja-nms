<?php

namespace Antareja\Models;

use Antareja\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * Kredensial SSH bersama untuk backup konfigurasi (mis. "Switch Pit — read-only").
 * Password disimpan terenkripsi dengan APP_KEY dan tidak pernah dikirim balik ke browser.
 * Gunakan akun read-only di perangkat.
 */
class ConfigCredential extends Model
{
    use Auditable;

    protected $table = 'ant_config_credentials';

    protected $fillable = ['name', 'username', 'secret', 'port'];

    protected $hidden = ['secret'];

    protected $casts = ['secret' => 'encrypted', 'port' => 'integer'];

    public function targets(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ConfigTarget::class, 'credential_id');
    }

    /** Jangan pernah mencatat password ke audit log. */
    public function auditValues(array $values): array
    {
        if (array_key_exists('secret', $values)) {
            $values['secret'] = '••••••';
        }

        return $values;
    }

    public function auditType(): string
    {
        return 'config_credential';
    }

    public function auditLabel(): string
    {
        return 'Kredensial backup';
    }

    public function auditName(): string
    {
        return $this->name;
    }
}
