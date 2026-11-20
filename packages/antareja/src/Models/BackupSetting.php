<?php

namespace Antareja\Models;

use Antareja\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/** Pengaturan backup NMS (satu baris). Password/kunci SFTP terenkripsi APP_KEY dan tidak pernah dikirim ke browser. */
class BackupSetting extends Model
{
    use Auditable;

    protected $table = 'ant_backup_settings';

    protected $fillable = ['schedule_enabled', 'schedule_time', 'keep_count', 'include_rrd', 'sftp_enabled', 'sftp_host', 'sftp_port',
        'sftp_username', 'sftp_secret', 'sftp_path', 'sftp_keep_count'];

    protected $hidden = ['sftp_secret'];

    protected $casts = [
        'schedule_enabled' => 'boolean', 'include_rrd' => 'boolean', 'sftp_enabled' => 'boolean', 'sftp_secret' => 'encrypted',
    ];

    public static function current(): self
    {
        return self::query()->first() ?? self::query()->create([]);
    }

    public function auditValues(array $values): array
    {
        if (array_key_exists('sftp_secret', $values)) {
            $values['sftp_secret'] = '••••••';
        }

        return $values;
    }

    public function auditType(): string
    {
        return 'backup_setting';
    }

    public function auditLabel(): string
    {
        return 'Pengaturan backup NMS';
    }
}
