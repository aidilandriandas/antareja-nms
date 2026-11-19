<?php

namespace Antareja\Support;

use App\Models\WirelessSensor;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Daftar resmi 17 modul ANTAREJA NMS beserta status pengembangannya.
 *
 * status:
 *  - native  : sudah tersedia penuh dari inti LibreNMS
 *  - partial : sebagian tersedia dari LibreNMS, fitur khusus ANTAREJA menyusul
 *  - planned : belum dibangun (disembunyikan dari menu, tampil di halaman Beranda ANTAREJA)
 */
class ModuleRegistry
{
    public const NATIVE = 'native';
    public const PARTIAL = 'partial';
    public const PLANNED = 'planned';

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function all(): Collection
    {
        return collect([
            $this->module('dashboard', 'Dashboard', 'tachometer-alt', self::PARTIAL, 'overview', 1,
                'Dashboard teknis tersedia dari LibreNMS. Dashboard manajemen & ringkasan per site menyusul.'),
            $this->module('network-monitoring', 'Network Monitoring', 'server', self::NATIVE, 'devices', 0,
                'Status perangkat, SNMP v2c/v3, ICMP, uptime, sensor hardware.', 'device.viewAll'),
            $this->module('device-inventory', 'Device Inventory', 'boxes', self::PARTIAL, 'inventory', 1,
                'Inventaris hardware (entPhysical) tersedia. Serial, PIC, rak, garansi, evidence menyusul.', 'inventory.viewAll'),
            $this->module('device-discovery', 'Device Discovery', 'search-plus', self::PLANNED, null, 1,
                'Auto-discovery + alur verifikasi engineer (Pending → Verified/Rejected).'),
            $this->module('device-mapping', 'Device Mapping', 'project-diagram', self::PARTIAL, 'device-dependencies', 1,
                'Relasi parent/child tersedia. Mapping port ↔ device terverifikasi & saran LLDP/FDB menyusul.', 'device.viewAll'),
            $this->module('ipam', 'IP Address Management', 'network-wired', self::PLANNED, null, 2,
                'Subnet, VLAN, alokasi IP, reserved, duplikat, histori, utilisasi.'),
            $this->module('interface-monitoring', 'Interface Monitoring', 'ethernet', self::NATIVE, 'ports', 0,
                'Status port, traffic, bandwidth, error, utilisasi.', 'port.viewAll'),
            $this->module('wireless-monitoring', 'Wireless Monitoring', 'wifi', self::PARTIAL, $this->wirelessPath(), 2,
                'Sensor wireless (client, sinyal, frekuensi) tersedia. Ringkasan PTP/PTMP menyusul.', 'device.viewAll'),
            $this->module('cctv', 'CCTV Infrastructure', 'video', self::PLANNED, null, 2,
                'Kamera ↔ NVR ↔ port switch, status, PIC, histori incident.'),
            $this->module('network-quality', 'Network Quality', 'signal', self::PLANNED, null, 2,
                'Quality Probe (20 ping/5 menit): latency, packet loss, jitter, grafik smoke.'),
            $this->module('alert-center', 'Event & Alert Center', 'bell', self::PARTIAL, 'alerts', 3,
                'Alert LibreNMS tersedia. Problem lifecycle, assignment & korelasi ala Zabbix menyusul.', 'alert.viewAll'),
            $this->module('incident', 'Incident Management', 'fire-extinguisher', self::PLANNED, null, 3,
                'Incident ID, severity, PIC, ack/resolution time, root cause, evidence.'),
            $this->module('sla', 'SLA Management', 'percentage', self::PARTIAL, 'outages', 4,
                'Data outage tersedia. Kebijakan SLA, service tree, MTTR/MTBF menyusul.', 'outage.viewAll'),
            $this->module('maintenance', 'Maintenance Management', 'tools', self::PARTIAL, 'alert-schedule', 3,
                'Jadwal maintenance (penekan alert) tersedia. Kalender, preventive/corrective, change request menyusul.', 'alert-schedule.view'),
            $this->module('reports', 'Reports', 'file-alt', self::PLANNED, null, 4,
                'Laporan bulanan PDF / Excel / CSV.'),
            $this->module('users', 'User Management', 'users', self::NATIVE, 'users', 0,
                'Pengguna, role & permission granular.', 'user.view'),
            $this->module('system', 'System Administration', 'cogs', self::NATIVE, 'settings', 0,
                'Pengaturan global, poller, validasi sistem.', 'settings.view'),
        ]);
    }

    /**
     * Modul yang tampil di menu: sudah tersedia (native/partial) dan boleh diakses user.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function menuFor(?Authenticatable $user): Collection
    {
        if ($user === null) {
            return collect();
        }

        return $this->all()
            ->reject(fn (array $m) => $m['status'] === self::PLANNED || $m['path'] === null)
            ->filter(fn (array $m) => $m['permission'] === null || Gate::forUser($user)->allows($m['permission']))
            ->values();
    }

    /**
     * Halaman wireless LibreNMS hanya ada untuk kelas sensor yang sudah ditemukan (selain itu 404),
     * jadi arahkan ke kelas pertama yang tersedia, atau null bila belum ada perangkat wireless.
     */
    private function wirelessPath(): ?string
    {
        try {
            $class = WirelessSensor::query()->orderBy('sensor_class')->value('sensor_class');
        } catch (\Throwable) {
            return null;
        }

        return $class === null ? null : 'wireless/' . ($class instanceof \BackedEnum ? $class->value : $class);
    }

    private function module(
        string $key,
        string $label,
        string $icon,
        string $status,
        ?string $path,
        int $phase,
        string $description,
        ?string $permission = null,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'icon' => $icon,
            'status' => $status,
            'path' => $path,
            'url' => $path !== null ? url($path) : null,
            'phase' => $phase,
            'description' => $description,
            'permission' => $permission,
        ];
    }
}
