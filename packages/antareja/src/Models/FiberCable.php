<?php

namespace Antareja\Models;

use Antareja\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kabel fiber antara dua titik ujung (ODF atau joint). Core diberi nomor 1..N dengan warna TIA-598.
 */
class FiberCable extends Model
{
    use Auditable;

    /** Urutan warna standar TIA/EIA-598 (untuk core dan tube). */
    public const COLORS = [
        1 => ['Biru', '#1d4ed8'], 2 => ['Oranye', '#ea580c'], 3 => ['Hijau', '#16a34a'], 4 => ['Cokelat', '#78350f'],
        5 => ['Abu-abu', '#64748b'], 6 => ['Putih', '#f8fafc'], 7 => ['Merah', '#dc2626'], 8 => ['Hitam', '#111827'],
        9 => ['Kuning', '#facc15'], 10 => ['Ungu', '#7c3aed'], 11 => ['Merah muda', '#f9a8d4'], 12 => ['Aqua', '#22d3ee'],
    ];

    public const INSTALLATION = ['aerial' => 'Udara (tiang)', 'duct' => 'Duct / pipa', 'buried' => 'Tanam langsung', 'indoor' => 'Indoor'];

    public const STATUSES = [
        'planned' => ['label' => 'Rencana', 'tone' => 'info'],
        'active' => ['label' => 'Aktif', 'tone' => 'ok'],
        'damaged' => ['label' => 'Rusak', 'tone' => 'crit'],
        'retired' => ['label' => 'Tidak dipakai', 'tone' => 'unknown'],
    ];

    /** Atenuasi tipikal (dB/km) per mode & panjang gelombang, untuk estimasi loss budget. */
    public const ATTENUATION = ['sm' => [1310 => 0.35, 1550 => 0.25], 'mm' => [850 => 3.0, 1300 => 1.0]];

    protected $table = 'ant_fiber_cables';

    protected $fillable = ['code', 'name', 'mode', 'fiber_type', 'core_count', 'cores_per_tube', 'length_m', 'installation',
        'a_type', 'a_id', 'b_type', 'b_id', 'status', 'installed_on', 'vendor', 'route'];

    protected $casts = ['installed_on' => 'date'];

    public function cores(): HasMany
    {
        return $this->hasMany(FiberCore::class, 'cable_id')->orderBy('core_no');
    }

    /** @return FiberOdf|FiberJoint|null */
    public function endpoint(string $side): ?Model
    {
        $type = $side === 'a' ? $this->a_type : $this->b_type;
        $id = $side === 'a' ? $this->a_id : $this->b_id;

        return $type === 'odf' ? FiberOdf::with('site')->find($id) : FiberJoint::find($id);
    }

    public function endpointLabel(string $side): string
    {
        $e = $this->endpoint($side);

        return $e ? ($e instanceof FiberOdf ? 'ODF ' . $e->label() : 'Joint ' . $e->label()) : '—';
    }

    /** Sisi kabel yang berada di titik (type,id), atau null. */
    public function sideAt(string $type, int $id): ?string
    {
        return $this->a_type === $type && (int) $this->a_id === $id ? 'a' : ($this->b_type === $type && (int) $this->b_id === $id ? 'b' : null);
    }

    public static function color(int $n): array
    {
        return self::COLORS[(($n - 1) % 12) + 1];
    }

    public function statusMeta(): array
    {
        return self::STATUSES[$this->status] ?? ['label' => $this->status, 'tone' => 'unknown'];
    }

    public function auditType(): string
    {
        return 'fiber_cable';
    }

    public function auditLabel(): string
    {
        return 'Kabel fiber';
    }

    public function auditName(): string
    {
        return $this->code;
    }
}
