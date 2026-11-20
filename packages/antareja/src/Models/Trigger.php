<?php

namespace Antareja\Models;

use Antareja\Models\Concerns\Auditable;
use Antareja\Support\Severity;
use App\Models\Device;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Trigger expression ala Zabbix dalam bentuk terstruktur:
 *   fungsi(item.metrik, jendela) operator ambang  → problem bila terpenuhi.
 * Contoh: avg(PTP Pit A.loss, 15m) > 5
 */
class Trigger extends Model
{
    use Auditable;

    public const SOURCES = [
        'quality' => 'Quality Probe',
        'port' => 'Port / interface',
        'sensor' => 'Sensor hardware',
        'wireless' => 'Sensor wireless',
        'device' => 'Perangkat',
    ];

    /** metrik per sumber: label & satuan */
    public const METRICS = [
        'quality' => [
            'median_ms' => ['Latency median', 'ms'],
            'avg_ms' => ['Latency rata-rata', 'ms'],
            'loss_pct' => ['Packet loss', '%'],
            'jitter_ms' => ['Jitter', 'ms'],
        ],
        'port' => [
            'in_util_pct' => ['Utilisasi masuk', '%'],
            'out_util_pct' => ['Utilisasi keluar', '%'],
            'in_mbps' => ['Traffic masuk', 'Mbps'],
            'out_mbps' => ['Traffic keluar', 'Mbps'],
            'in_errors' => ['Error masuk', '/s'],
            'out_errors' => ['Error keluar', '/s'],
        ],
        'sensor' => ['value' => ['Nilai sensor', '']],
        'wireless' => ['value' => ['Nilai sensor wireless', '']],
        'device' => [
            'uptime_s' => ['Uptime (deteksi reboot)', 's'],
            'poll_s' => ['Durasi polling SNMP', 's'],
        ],
    ];

    public const FUNCTIONS = ['last' => 'nilai terakhir', 'avg' => 'rata-rata', 'min' => 'minimum', 'max' => 'maksimum'];

    public const OPERATORS = ['>' => '>', '>=' => '≥', '<' => '<', '<=' => '≤', '=' => '=', '<>' => '≠'];

    protected $table = 'ant_triggers';

    protected $fillable = ['name', 'description', 'source', 'ref_id', 'device_id', 'metric', 'function', 'window_minutes',
        'operator', 'threshold', 'recovery_threshold', 'severity', 'enabled', 'created_by'];

    protected $casts = [
        'enabled' => 'boolean',
        'threshold' => 'float',
        'recovery_threshold' => 'float',
        'last_value' => 'float',
        'last_evaluated_at' => 'datetime',
        'state_changed_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id', 'device_id');
    }

    public function samples(): HasMany
    {
        return $this->hasMany(TriggerSample::class);
    }

    public static function compare(float $value, string $op, float $threshold): bool
    {
        return match ($op) {
            '>' => $value > $threshold,
            '>=' => $value >= $threshold,
            '<' => $value < $threshold,
            '<=' => $value <= $threshold,
            '=' => abs($value - $threshold) < 1e-9,
            '<>' => abs($value - $threshold) >= 1e-9,
            default => false,
        };
    }

    public function unit(): string
    {
        return self::METRICS[$this->source][$this->metric][1] ?? '';
    }

    public function metricLabel(): string
    {
        return self::METRICS[$this->source][$this->metric][0] ?? $this->metric;
    }

    public static function fmt(?float $v, string $unit = ''): string
    {
        if ($v === null) {
            return '—';
        }
        $s = abs($v) >= 100 ? number_format($v, 0, ',', '.') : rtrim(rtrim(number_format($v, 2, ',', '.'), '0'), ',');

        return trim($s . ' ' . $unit);
    }

    /** Ekspresi yang dapat dibaca, mis. avg(PTP Pit A · Packet loss, 15m) > 5 % */
    public function expression(?string $itemLabel = null): string
    {
        $item = ($itemLabel ?? '#' . $this->ref_id) . ' · ' . $this->metricLabel();
        $fn = $this->function === 'last' ? "last({$item})" : "{$this->function}({$item}, {$this->window_minutes}m)";

        return $fn . ' ' . (self::OPERATORS[$this->operator] ?? $this->operator) . ' ' . self::fmt($this->threshold, $this->unit());
    }

    public function stateMeta(): array
    {
        return match ($this->state) {
            'problem' => ['label' => 'Problem', 'tone' => Severity::problem($this->severity)['tone']],
            'ok' => ['label' => 'OK', 'tone' => 'ok'],
            default => ['label' => 'Tanpa data', 'tone' => 'unknown'],
        };
    }

    public function auditType(): string
    {
        return 'trigger';
    }

    public function auditLabel(): string
    {
        return 'Trigger';
    }

    public function auditName(): string
    {
        return $this->name;
    }
}
