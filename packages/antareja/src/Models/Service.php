<?php

namespace Antareja\Models;

use Antareja\Models\Concerns\Auditable;
use App\Models\Device;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Layanan dalam service tree (mis. "Jaringan Pit A" → "Uplink FO", "CCTV Pit A").
 * Anggota: perangkat dan/atau layanan anak. Status & SLA dihitung dari anggota menurut aturan calc.
 */
class Service extends Model
{
    use Auditable;

    public const CALC = [
        'all' => 'Semua anggota harus up (seri) — down bila satu anggota down',
        'any' => 'Redundan — down hanya bila semua anggota down',
        'min' => 'Minimal N anggota up — down bila anggota up kurang dari N',
    ];

    protected $table = 'ant_services';

    protected $fillable = ['name', 'description', 'parent_id', 'site_id', 'calc', 'min_up', 'sla_target', 'active', 'sort'];

    protected $casts = [
        'active' => 'boolean',
        'sla_target' => 'float',
        'min_up' => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort')->orderBy('name');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function devices(): BelongsToMany
    {
        return $this->belongsToMany(Device::class, 'ant_service_devices', 'service_id', 'device_id');
    }

    public function monthly(): HasMany
    {
        return $this->hasMany(SlaMonthly::class);
    }

    /** ID layanan ini beserta semua turunannya (untuk mencegah siklus induk). */
    public function descendantIds(): array
    {
        $ids = [$this->id];
        $frontier = [$this->id];
        while ($frontier !== []) {
            $frontier = self::whereIn('parent_id', $frontier)->whereNotIn('id', $ids)->pluck('id')->all();
            $ids = array_merge($ids, $frontier);
        }

        return $ids;
    }

    public function calcLabel(): string
    {
        return match ($this->calc) {
            'any' => 'Redundan',
            'min' => "Minimal {$this->min_up} up",
            default => 'Seri',
        };
    }

    public function auditType(): string
    {
        return 'service';
    }

    public function auditLabel(): string
    {
        return 'Layanan';
    }

    public function auditName(): string
    {
        return $this->name;
    }
}
