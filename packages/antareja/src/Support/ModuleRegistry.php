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
    /** Modul yang boleh dimatikan admin (menu & halamannya hilang; data tetap tersimpan). */
    public const TOGGLEABLE = [
        'device-inventory' => 'Inventaris aset (serial, PIC, garansi, lifecycle, evidence, kelengkapan data)',
        'device-discovery' => 'Discovery — antrean verifikasi perangkat sebagai aset resmi',
        'fiber' => 'Fiber Optic — kabel, core, ODF, splice, OTDR',
        'workorders' => 'Work Order — pekerjaan lapangan, checklist, material',
        'changes' => 'Change Request — rencana perubahan & persetujuan',
        'config' => 'Backup Konfigurasi — salin konfigurasi perangkat lewat SSH',
        'capacity' => 'Capacity Planning — tren & perkiraan kapasitas',
        'assistant' => 'Asisten AI & deteksi anomali',
        'ipam' => 'IP Address Management',
        'sla' => 'SLA Management',
        'reports' => 'Laporan Bulanan',
        'management' => 'Dashboard Manajemen',
    ];

    /** Profil NOC (bawaan): fokus monitoring jaringan, tanpa modul manajemen aset & pekerjaan lapangan. */
    public const NOC_DISABLED = ['device-inventory', 'device-discovery', 'fiber', 'workorders', 'changes'];

    private static ?array $disabledCache = null;

    /** @return list<string> */
    public static function disabled(): array
    {
        if (self::$disabledCache !== null) {
            return self::$disabledCache;
        }
        try {
            $raw = \Illuminate\Support\Facades\DB::table('ant_settings')->where('key', 'modules_disabled')->value('value');
            $list = $raw === null ? self::NOC_DISABLED : (json_decode($raw, true) ?: []);
        } catch (\Throwable) {
            $list = []; // tabel belum ada (sebelum migrasi): jangan sembunyikan apa pun
        }

        return self::$disabledCache = array_values(array_intersect($list, array_keys(self::TOGGLEABLE)));
    }

    public static function enabled(string $key): bool
    {
        return ! in_array($key, self::disabled(), true);
    }

    /** @param  list<string>  $keys */
    public static function setDisabled(array $keys): void
    {
        $keys = array_values(array_intersect($keys, array_keys(self::TOGGLEABLE)));
        \Illuminate\Support\Facades\DB::table('ant_settings')->updateOrInsert(['key' => 'modules_disabled'],
            ['value' => json_encode($keys), 'updated_at' => now(), 'created_at' => now()]);
        self::$disabledCache = null;
    }

    public const NATIVE = 'native';
    public const PARTIAL = 'partial';
    public const PLANNED = 'planned';

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function all(): Collection
    {
        return collect([
            $this->module('dashboard', 'Dashboard Teknis', 'tachometer-alt', self::NATIVE, 'overview', 0,
                'Dashboard teknis dengan widget yang bisa diatur.'),
            $this->module('management', 'Dashboard Manajemen', 'chart-bar', self::NATIVE, 'antareja/management', 4,
                'Availability jaringan, SLA layanan, incident, MTTA/MTTR, tren 6 bulan, per site — tanpa detail teknis.', 'ant-dashboard.management'),
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
                'Kamera & NVR: status, IP, MAC, port switch, NVR perekam, lokasi, PIC, riwayat gangguan; live view & snapshot dari NVR Hikvision; cek kesehatan gambar (gelap, beku, buram, bergeser).', 'ant-cctv.view'),
            $this->module('network-quality', 'Network Quality', 'signal', self::NATIVE, 'antareja/quality', 2,
                'Quality Probe (20 ping / 5 menit): latency, packet loss, jitter, grafik smoke 3 jam–30 hari, ambang status.', 'ant-quality.view'),
            $this->module('alert-center', 'Event & Alert Center', 'bell', self::NATIVE, 'antareja/problems', 3,
                'Problem ala Zabbix dari status polling, port uplink, Quality Probe, IPAM, dan alert rule; dedup lintas sumber, korelasi induk/anak, acknowledge.', 'ant-problem.view'),
            $this->module('incident', 'Incident Management', 'fire-extinguisher', self::NATIVE, 'antareja/incidents', 3,
                'Nomor incident, severity, PIC, waktu ack/resolve, root cause, corrective action, evidence, work notes, MTTA/MTTR.', 'ant-incident.view'),
            $this->module('sla', 'SLA Management', 'percentage', self::NATIVE, 'antareja/sla', 4,
                'Service tree (seri / redundan / minimal N up), target SLA, availability bulanan, MTTR/MTBF, pengecualian maintenance.', 'ant-sla.view'),
            $this->module('triggers', 'Trigger', 'crosshairs', self::NATIVE, 'antareja/triggers', 4,
                'Trigger expression ala Zabbix: last/avg/min/max atas jendela waktu untuk Quality Probe, port, sensor, wireless, perangkat; histeresis.', 'ant-trigger.view'),
            $this->module('maintenance', 'Maintenance Management', 'tools', self::NATIVE, 'antareja/maintenance', 3,
                'Kalender preventive / corrective / change, penekanan alert otomatis, laporan penyelesaian & evidence, pengecualian SLA.', 'ant-maintenance.view'),
            $this->module('escalation', 'Eskalasi & Notifikasi', 'paper-plane', self::NATIVE, 'antareja/escalation', 3,
                'Incident otomatis dan notifikasi bertahap lewat Telegram, email, dan kanal lain sampai di-acknowledge.', 'ant-escalation.view'),
            $this->module('workorders', 'Work Order', 'clipboard-list', self::NATIVE, 'antareja/workorders', 5,
                'Penugasan pekerjaan lapangan dengan checklist, material, evidence, tenggat per prioritas, dan verifikasi supervisor.', 'ant-workorder.view'),
            $this->module('changes', 'Change Request', 'exchange-alt', self::NATIVE, 'antareja/changes', 5,
                'Pengajuan perubahan (RFC): rencana, rollback, risiko, persetujuan oleh orang lain, otomatis menjadi jendela maintenance.', 'ant-change.view'),
            $this->module('config', 'Backup Konfigurasi', 'file-code', self::NATIVE, 'antareja/config', 5,
                'Backup konfigurasi via SSH (perintah baca saja), riwayat versi & diff, deteksi perubahan tanpa change request.', 'ant-config.view'),
            $this->module('capacity', 'Capacity Planning', 'chart-line', self::NATIVE, 'antareja/capacity', 5,
                'Utilisasi uplink (p95 harian dari histori RRD), storage NVR/server, pool IP, dan prakiraan kapan menyentuh ambang.', 'ant-capacity.view'),
            $this->module('fiber', 'Fiber Optic', 'grip-lines', self::NATIVE, 'antareja/fiber', 5,
                'Kabel, core (warna TIA-598), ODF, joint & splice, uji OTDR, penelusuran jalur end-to-end, lokasi gangguan dari jarak OTDR.', 'ant-fiber.view'),
            $this->module('assistant', 'Asisten & Anomali', 'magic', self::NATIVE, 'antareja/assistant', 5,
                'Deteksi anomali statistik offline (latency, loss, traffic, flapping) dan asisten AI opsional untuk draf serah terima shift & analisis incident — tanpa eksekusi perubahan.', 'ant-ai.use'),
            $this->module('reports', 'Reports', 'file-alt', self::NATIVE, 'antareja/reports', 4,
                'Laporan bulanan per site: SLA, availability, incident, maintenance, kualitas, utilisasi IP — cetak PDF, Excel, CSV.', 'ant-report.view'),
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
            ->reject(fn (array $m) => $m['status'] === self::PLANNED || $m['path'] === null || ! self::enabled($m['key']))
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
