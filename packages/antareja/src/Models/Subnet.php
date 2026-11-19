<?php

namespace Antareja\Models;

use Antareja\Models\Concerns\Auditable;
use Antareja\Support\IpMath;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subnet extends Model
{
    use Auditable;

    public const PURPOSES = [
        'management' => 'Manajemen perangkat',
        'cctv' => 'CCTV',
        'wifi' => 'Wi-Fi / klien wireless',
        'backhaul' => 'Backhaul PTP / PTMP',
        'server' => 'Server',
        'user' => 'User / kantor',
        'voip' => 'VoIP',
        'scada' => 'SCADA / OT',
        'transit' => 'Transit / WAN',
        'other' => 'Lainnya',
    ];

    protected $table = 'ant_subnets';

    protected $fillable = [
        'family', 'network', 'prefix', 'cidr', 'name', 'purpose', 'site_id', 'vlan_id',
        'gateway', 'dhcp_start', 'dhcp_end', 'description', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    protected array $ipBinaryAttributes = ['network', 'gateway', 'dhcp_start', 'dhcp_end'];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function vlan(): BelongsTo
    {
        return $this->belongsTo(Vlan::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(IpAddress::class);
    }

    public function gatewayText(): ?string
    {
        return IpMath::toText($this->gateway);
    }

    public function dhcpStartText(): ?string
    {
        return IpMath::toText($this->dhcp_start);
    }

    public function dhcpEndText(): ?string
    {
        return IpMath::toText($this->dhcp_end);
    }

    public function usableCount(): int|float
    {
        return IpMath::usableCount($this->family, $this->prefix);
    }

    /** Ukuran pool DHCP (IPv4). */
    public function dhcpPoolSize(): int
    {
        if ($this->family !== 4 || ! $this->dhcp_start || ! $this->dhcp_end) {
            return 0;
        }

        return max(0, IpMath::v4ToLong($this->dhcp_end) - IpMath::v4ToLong($this->dhcp_start) + 1);
    }

    public function containsBinary(string $ip): bool
    {
        return IpMath::contains($this->network, $this->prefix, $ip);
    }

    public function purposeLabel(): string
    {
        return self::PURPOSES[$this->purpose] ?? '—';
    }

    public function auditType(): string
    {
        return 'subnet';
    }

    public function auditLabel(): string
    {
        return 'Subnet';
    }

    public function auditName(): string
    {
        return "{$this->cidr} ({$this->name})";
    }
}
