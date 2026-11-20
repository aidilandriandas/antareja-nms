<?php

namespace Antareja\Models;

use Illuminate\Database\Eloquent\Model;

class Backup extends Model
{
    protected $table = 'ant_backups';

    protected $guarded = ['id'];

    protected $casts = ['summary' => 'array', 'include_rrd' => 'boolean', 'started_at' => 'datetime', 'finished_at' => 'datetime'];

    public function path(): string
    {
        return \Antareja\Services\BackupService::dir() . '/' . $this->filename;
    }

    public function exists(): bool
    {
        return is_file($this->path());
    }
}
