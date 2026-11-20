<?php

namespace Antareja\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FiberCore extends Model
{
    public const STATUSES = [
        'free' => ['label' => 'Kosong', 'tone' => 'unknown'],
        'used' => ['label' => 'Terpakai', 'tone' => 'ok'],
        'reserved' => ['label' => 'Dicadangkan', 'tone' => 'info'],
        'broken' => ['label' => 'Putus / rusak', 'tone' => 'crit'],
    ];

    protected $table = 'ant_fiber_cores';

    protected $fillable = ['cable_id', 'core_no', 'status', 'service', 'loss_db', 'notes'];

    protected $casts = ['loss_db' => 'float'];

    public function cable(): BelongsTo
    {
        return $this->belongsTo(FiberCable::class, 'cable_id');
    }

    public function tests(): HasMany
    {
        return $this->hasMany(FiberTest::class, 'core_id')->latest('tested_at');
    }

    public function tube(): int
    {
        return intdiv($this->core_no - 1, max(1, $this->cable?->cores_per_tube ?? 12)) + 1;
    }

    /** Nomor & warna core di dalam tube, mis. "Tube 1 Biru / core Oranye". */
    public function colorLabel(): string
    {
        $per = max(1, $this->cable?->cores_per_tube ?? 12);
        $inTube = (($this->core_no - 1) % $per) + 1;

        return 'tube ' . FiberCable::color($this->tube())[0] . ' · ' . FiberCable::color($inTube)[0];
    }

    public function colorHex(): string
    {
        $per = max(1, $this->cable?->cores_per_tube ?? 12);

        return FiberCable::color((($this->core_no - 1) % $per) + 1)[1];
    }

    public function label(): string
    {
        return ($this->cable?->code ?? 'kabel') . ' core ' . $this->core_no;
    }

    public function statusMeta(): array
    {
        return self::STATUSES[$this->status] ?? ['label' => $this->status, 'tone' => 'unknown'];
    }
}
