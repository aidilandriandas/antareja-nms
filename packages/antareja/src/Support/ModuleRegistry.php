<?php

namespace Antareja\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Daftar modul ANTAREJA NMS (17 menu utama + Site & Area dan Audit Log) beserta status pengembangannya.
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
            $this->module('sites', 'Site & Area', 'map-marked-alt', self::NATIVE, 'antareja/sites', 1,
                'Site operasional tambang (office, workshop, plant, pit, ROM, jetty, mess, remote) dan area di dalamnya.', 'ant-site.view'),
            $this->module('device-inventory', 'Device Inventory', 'boxes', self::NATIVE, 'antareja/inventory', 1,
                'Inventaris resmi: kategori, site/area, rak, serial, PIC, garansi, lifecycle, evidence, kelengkapan data, export CSV.', 'ant-inventory.view'),
            $this->module('device-discovery', 'Device Discovery', 'search-plus', self::NATIVE, 'antareja/discovery', 1,
                'Auto-discovery + verifikasi engineer: Discovered → Pending → Verified / Incomplete / Rejected / Retired.', 'ant-discovery.view'),
            $this->module('device-mapping', 'Device Mapping', 'project-diagram', self::NATIVE, 'antareja/mapping', 1,
                'Relasi port ↔ perangkat terverifikasi, saran dari LLDP/CDP & tabel MAC, sinkron dependensi alert parent/child.', 'ant-mapping.view'),
            $this->module('ipam', 'IP Address Management', 'network-wired', self::NATIVE, 'antareja/ipam', 2,
                'Subnet, VLAN, alokasi IP (static/reserved/DHCP), IP terpakai dari interface & ARP, IP kosong, duplikat/konflik, histori, utilisasi.', 'ant-ipam.view'),
            $this->module('interface-monitoring', 'Interface Monitoring', 'ethernet', self::NATIVE, 'ports', 0,
                'Status port, traffic, bandwidth, error, utilisasi.', 'port.viewAll'),
            $this->module('wireless-monitoring', 'Wireless Monitoring', 'wifi', self::NATIVE, 'antareja/wireless', 2,
                'AP, controller, PTP, PTMP: status, client, RSSI, SNR, frekuensi, rate, CCQ, pasangan link. Metrik sesuai dukungan vendor.', 'ant-inventory.view'),
            $this->module('cctv', 'CCTV Infrastructure', 'video', self::NATIVE, 'antareja/cctv', 2,
                'Kamera & NVR: status, IP, MAC, port switch, NVR perekam, lokasi, PIC, riwayat gangguan 30 hari.', 'ant-cctv.view'),
            $this->module('network-quality', 'Network Quality', 'signal', self::NATIVE, 'antareja/quality', 2,
                'Quality Probe (20 ping / 5 menit): latency, packet loss, jitter, grafik smoke 3 jam–30 hari, ambang status.', 'ant-quality.view'),
            $this->module('alert-center', 'Event & Alert Center', 'bell', self::NATIVE, 'antareja/problems', 3,
                'Problem ala Zabbix dari status polling, port uplink, Quality Probe, IPAM, dan alert rule; dedup lintas sumber, korelasi induk/anak, acknowledge.', 'ant-problem.view'),
            $this->module('incident', 'Incident Management', 'fire-extinguisher', self::NATIVE, 'antareja/incidents', 3,
                'Nomor incident, severity, PIC, waktu ack/resolve, root cause, corrective action, evidence, work notes, MTTA/MTTR.', 'ant-incident.view'),
            $this->module('sla', 'SLA Management', 'percentage', self::PARTIAL, 'outages', 4,
                'Data outage tersedia. Kebijakan SLA, service tree, MTTR/MTBF menyusul.', 'outage.viewAll'),
            $this->module('maintenance', 'Maintenance Management', 'tools', self::NATIVE, 'antareja/maintenance', 3,
                'Kalender preventive / corrective / change, penekanan alert otomatis, laporan penyelesaian & evidence, pengecualian SLA.', 'ant-maintenance.view'),
            $this->module('escalation', 'Eskalasi & Notifikasi', 'paper-plane', self::NATIVE, 'antareja/escalation', 3,
                'Incident otomatis dan notifikasi bertahap lewat Telegram, email, dan kanal lain sampai di-acknowledge.', 'ant-escalation.view'),
            $this->module('reports', 'Reports', 'file-alt', self::PLANNED, null, 4,
                'Laporan bulanan PDF / Excel / CSV.'),
            $this->module('users', 'User Management', 'users', self::NATIVE, 'users', 0,
                'Pengguna, role & permission granular.', 'user.view'),
            $this->module('system', 'System Administration', 'cogs', self::NATIVE, 'settings', 0,
                'Pengaturan global, poller, validasi sistem.', 'settings.view'),
            $this->module('audit', 'Audit Log', 'history', self::NATIVE, 'antareja/audit', 1,
                'Jejak login, perubahan inventaris, IP, mapping, site, verifikasi, evidence.', 'ant-audit.view'),
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
