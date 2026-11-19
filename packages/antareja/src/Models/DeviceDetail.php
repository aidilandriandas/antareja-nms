<?php

namespace Antareja\Models;

use Antareja\Models\Concerns\Auditable;
use Antareja\Support\Catalog;
use App\Models\Device;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Data inventaris ANTAREJA untuk satu device LibreNMS (relasi 1:1 lewat device_id).
 */
class DeviceDetail extends Model
{
    use Auditable;

    public const PENDING = 'pending';
    public const VERIFIED = 'verified';
    public const REJECTED = 'rejected';
    public const RETIRED = 'retired';

    protected $table = 'ant_device_details';

    protected $primaryKey = 'device_id';

    public $incrementing = false;

    protected $fillable = [
        'device_id', 'category', 'site_id', 'area_id', 'department', 'installation_location', 'rack',
        'serial_number', 'asset_tag', 'model', 'mac_address', 'management_protocol',
        'pic_user_id', 'pic_name', 'pic_phone', 'installed_at', 'warranty_until', 'warranty_info',
        'lifecycle_status', 'notes',
    ];

    protected $casts = [
        'installed_at' => 'date',
        'warranty_until' => 'date',
        'discovered_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    /** Field yang dinilai untuk indikator kelengkapan data inventaris. */
    public const COMPLETENESS_FIELDS = [
        'category' => 'Kategori',
        'site' => 'Site',
        'location' => 'Lokasi instalasi / rak',
        'pic' => 'PIC',
        'serial' => 'Serial number',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id', 'device_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function picUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_user_id', 'user_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by', 'user_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('verification_status', [self::REJECTED, self::RETIRED]);
    }

    public function effectiveSerial(): ?string
    {
        return $this->serial_number ?: ($this->device?->serial ?: null);
    }

    public function effectiveModel(): ?string
    {
        return $this->model ?: ($this->device?->hardware ?: null);
    }

    public function picLabel(): ?string
    {
        return $this->picUser?->realname ?: $this->picUser?->username ?: $this->pic_name;
    }

    public function categoryLabel(): string
    {
        return Catalog::label(Catalog::DEVICE_CATEGORIES, $this->category);
    }

    public function requiresMapping(): bool
    {
        return in_array($this->category, Catalog::EDGE_CATEGORIES, true);
    }

    public function hasVerifiedMapping(): bool
    {
        return DeviceLink::query()
            ->where('status', DeviceLink::VERIFIED)
            ->where(fn ($q) => $q->where('a_device_id', $this->device_id)->orWhere('b_device_id', $this->device_id))
            ->exists();
    }

    /**
     * @return array{score:int, missing:string[]}
     */
    public function completeness(?bool $hasMapping = null): array
    {
        $checks = [
            'category' => filled($this->category),
            'site' => filled($this->site_id),
            'location' => filled($this->installation_location) || filled($this->rack),
            'pic' => filled($this->pic_user_id) || filled($this->pic_name),
            'serial' => filled($this->effectiveSerial()),
        ];
        $labels = self::COMPLETENESS_FIELDS;

        if ($this->requiresMapping()) {
            $checks['mapping'] = $hasMapping ?? $this->hasVerifiedMapping();
            $labels['mapping'] = 'Mapping terverifikasi';
        }

        $missing = [];
        foreach ($checks as $key => $ok) {
            if (! $ok) {
                $missing[] = $labels[$key];
            }
        }

        return [
            'score' => (int) round(100 * (count($checks) - count($missing)) / count($checks)),
            'missing' => $missing,
        ];
    }

    /**
     * Status tampilan sesuai alur verifikasi:
     * Discovered → Pending Verification → Verified / Incomplete Mapping, atau Rejected / Retired.
     */
    public function displayStatus(?array $completeness = null): string
    {
        return match ($this->verification_status) {
            self::REJECTED => 'rejected',
            self::RETIRED => 'retired',
            self::VERIFIED => ($completeness ?? $this->completeness())['score'] < 100 ? 'incomplete_mapping' : 'verified',
            default => $this->wasReviewed() ? 'pending_verification' : 'discovered',
        };
    }

    /** Pending yang sudah mulai diisi engineer dianggap "Pending Verification", yang belum disentuh "Discovered". */
    public function wasReviewed(): bool
    {
        return filled($this->site_id) || filled($this->category) || filled($this->pic_user_id) || filled($this->pic_name);
    }

    public function auditType(): string
    {
        return 'device';
    }

    public function auditLabel(): string
    {
        return 'Detail inventaris';
    }

    public function auditName(): string
    {
        return $this->device?->displayName() ?? "#{$this->device_id}";
    }
}
