<?php

namespace Antareja\Support;

/**
 * Daftar nilai baku ANTAREJA (kategori perangkat, tipe site, status, dsb).
 */
class Catalog
{
    public const DEVICE_CATEGORIES = [
        'router' => 'Router',
        'switch' => 'Switch',
        'core-switch' => 'Core Switch',
        'distribution-switch' => 'Distribution Switch',
        'access-switch' => 'Access Switch',
        'wireless-ap' => 'Wireless Access Point',
        'wireless-controller' => 'Wireless Controller',
        'ptp' => 'PTP Radio',
        'ptmp' => 'PTMP Radio',
        'firewall' => 'Firewall',
        'cctv-camera' => 'CCTV Camera',
        'nvr' => 'NVR',
        'server' => 'Server',
        'ups' => 'UPS',
        'other' => 'Other ICT Infrastructure',
    ];

    /** Kategori yang wajib punya mapping terverifikasi (terhubung ke port switch / NVR / radio lawan). */
    public const EDGE_CATEGORIES = ['wireless-ap', 'cctv-camera', 'ptp', 'ptmp', 'access-switch'];

    public const SITE_TYPES = [
        'mining-site' => 'Mining Operational Site',
        'office' => 'Office',
        'workshop' => 'Workshop',
        'plant' => 'Plant',
        'pit' => 'Pit',
        'rom' => 'ROM',
        'jetty' => 'Jetty',
        'warehouse' => 'Warehouse',
        'mess' => 'Mess',
        'remote' => 'Remote Communication Site',
        'other' => 'Lainnya',
    ];

    public const MANAGEMENT_PROTOCOLS = [
        'snmp-v2c' => 'SNMP v2c',
        'snmp-v3' => 'SNMP v3',
        'icmp' => 'ICMP saja',
        'api' => 'Vendor API',
        'other' => 'Lainnya',
    ];

    public const LIFECYCLE = [
        'procurement' => 'Procurement',
        'installation' => 'Installation',
        'operational' => 'Operational',
        'maintenance' => 'Maintenance',
        'repair' => 'Repair',
        'replacement' => 'Replacement',
        'retired' => 'Retired',
    ];

    /** Status yang ditampilkan (gabungan status verifikasi + kelengkapan data). */
    public const VERIFICATION_DISPLAY = [
        'discovered' => ['label' => 'Discovered', 'class' => 'label-info', 'tone' => 'info'],
        'pending_verification' => ['label' => 'Pending Verification', 'class' => 'label-warning', 'tone' => 'warn'],
        'verified' => ['label' => 'Verified', 'class' => 'label-success', 'tone' => 'ok'],
        'incomplete_mapping' => ['label' => 'Incomplete Mapping', 'class' => 'label-primary', 'tone' => 'warn'],
        'rejected' => ['label' => 'Rejected', 'class' => 'label-danger', 'tone' => 'crit'],
        'retired' => ['label' => 'Retired', 'class' => 'label-default', 'tone' => 'unknown'],
    ];

    public const LINK_TYPES = [
        'uplink' => 'Uplink (perangkat ← upstream)',
        'ap-port' => 'Access Point ↔ port switch',
        'cctv-port' => 'CCTV ↔ port switch',
        'nvr-camera' => 'NVR ↔ kamera CCTV',
        'ptp-pair' => 'PTP ↔ PTP lawan',
        'ptmp-client' => 'PTMP base ↔ client',
        'power' => 'Catu daya (UPS → perangkat)',
        'other' => 'Lainnya',
    ];

    /** Tipe link yang berarti A adalah parent (upstream) dari B, disinkronkan ke dependensi LibreNMS. */
    public const PARENT_CHILD_LINK_TYPES = ['uplink', 'ap-port', 'cctv-port', 'ptmp-client', 'nvr-camera'];

    public const LINK_STATUS = [
        'suggested' => ['label' => 'Saran', 'class' => 'label-info', 'tone' => 'info'],
        'verified' => ['label' => 'Terverifikasi', 'class' => 'label-success', 'tone' => 'ok'],
        'rejected' => ['label' => 'Ditolak', 'class' => 'label-danger', 'tone' => 'crit'],
    ];

    public const LINK_SOURCES = [
        'manual' => 'Manual',
        'lldp' => 'LLDP',
        'cdp' => 'CDP',
        'fdb' => 'FDB / ARP',
    ];

    public static function label(array $list, ?string $key, string $fallback = '—'): string
    {
        if ($key === null || $key === '') {
            return $fallback;
        }
        $value = $list[$key] ?? $key;

        return is_array($value) ? $value['label'] : $value;
    }

    /**
     * Saran kategori dari data LibreNMS (OS & tipe perangkat). Hanya saran — engineer tetap memverifikasi.
     */
    public static function suggestCategory(?string $os, ?string $type, ?string $hardware = null): ?string
    {
        $os = strtolower((string) $os);
        $type = strtolower(trim((string) $type));
        $hardware = strtolower((string) $hardware);

        return match (true) {
            str_contains($os, 'cam') || $type === 'camera' => 'cctv-camera',
            str_contains($os, 'nvr') || $type === 'encoder' => 'nvr',
            in_array($os, ['airos-af', 'airos-af60', 'airos-af-ltu', 'mimosa'], true) => 'ptp',
            in_array($os, ['ciscowlc', 'arubaos'], true) => 'wireless-controller',
            $type === 'wireless' => 'wireless-ap',
            $type === 'firewall' || in_array($os, ['fortigate', 'pfsense', 'sophos-xg'], true) => 'firewall',
            $type === 'power' => 'ups',
            $type === 'server' || in_array($os, ['linux', 'windows', 'proxmox', 'vmware-esxi'], true) => 'server',
            $os === 'routeros' && ! str_contains($hardware, 'crs') => 'router',
            $type === 'network' => 'switch',
            default => null,
        };
    }
}
