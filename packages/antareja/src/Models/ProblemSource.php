<?php

namespace Antareja\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Event asli dari satu sumber (dipertahankan dengan ID aslinya) yang menyusun sebuah Problem. */
class ProblemSource extends Model
{
    public $timestamps = false;

    public const LABELS = [
        'device' => 'Status polling perangkat',
        'port' => 'Status port (mapping terverifikasi)',
        'quality' => 'Quality Probe',
        'ipam' => 'IPAM',
        'librenms_alert' => 'Alert rule poller',
        'trigger' => 'Trigger expression',
        'config' => 'Backup konfigurasi',
    ];

    protected $table = 'ant_problem_sources';

    protected $guarded = ['id'];

    protected $casts = [
        'active' => 'boolean',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'cleared_at' => 'datetime',
    ];

    public function problem(): BelongsTo
    {
        return $this->belongsTo(Problem::class);
    }

    public function url(): ?string
    {
        return match ($this->source) {
            'device' => url('device/' . $this->source_ref),
            'port' => url('device/' . ($this->problem?->device_id) . '/tab=port/port=' . $this->source_ref),
            'quality' => route('antareja.quality.show', $this->source_ref),
            'ipam' => route('antareja.ipam.index', ['q' => $this->source_ref]),
            'librenms_alert' => url('alerts'),
            'trigger' => route('antareja.triggers.show', $this->source_ref),
            'config' => route('antareja.config.device', $this->source_ref),
            default => null,
        };
    }
}
