<?php

namespace Antareja\Services;

use Antareja\Models\DeviceDetail;
use Antareja\Models\DeviceLink;
use Antareja\Support\Catalog;
use App\Models\Device;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Indikator kualitas data inventaris. Setiap indikator dapat dipakai sebagai filter daftar inventaris (?issue=...).
 */
class DataQuality
{
    /** Device dianggap "stale" bila tidak berhasil dipoll selama ini (menit). */
    public const STALE_MINUTES = 30;

    public const ISSUES = [
        'pending' => ['label' => 'Belum diverifikasi', 'icon' => 'search-plus', 'severity' => 'warning'],
        'missing_site' => ['label' => 'Tanpa site', 'icon' => 'map-marker-alt', 'severity' => 'warning'],
        'missing_category' => ['label' => 'Tanpa kategori', 'icon' => 'tags', 'severity' => 'warning'],
        'missing_pic' => ['label' => 'Tanpa PIC', 'icon' => 'user-slash', 'severity' => 'warning'],
        'missing_location' => ['label' => 'Tanpa lokasi instalasi / rak', 'icon' => 'map', 'severity' => 'info'],
        'missing_serial' => ['label' => 'Tanpa serial number', 'icon' => 'barcode', 'severity' => 'info'],
        'unmapped_edge' => ['label' => 'AP/CCTV/radio tanpa mapping terverifikasi', 'icon' => 'project-diagram', 'severity' => 'warning'],
        'duplicate_serial' => ['label' => 'Serial number duplikat', 'icon' => 'clone', 'severity' => 'danger'],
        'duplicate_ip' => ['label' => 'IP manajemen duplikat', 'icon' => 'network-wired', 'severity' => 'danger'],
        'stale' => ['label' => 'Data monitoring basi (> 30 menit)', 'icon' => 'clock', 'severity' => 'danger'],
        'warranty_expired' => ['label' => 'Garansi habis', 'icon' => 'calendar-times', 'severity' => 'info'],
    ];

    /**
     * Terapkan filter isu ke query DeviceDetail yang sudah di-join dengan tabel devices.
     */
    public function apply(Builder $query, string $issue): Builder
    {
        $active = fn (Builder $q) => $q->whereNotIn('ant_device_details.verification_status', [DeviceDetail::REJECTED, DeviceDetail::RETIRED]);

        return match ($issue) {
            'pending' => $query->where('ant_device_details.verification_status', DeviceDetail::PENDING),
            'missing_site' => $active($query)->whereNull('ant_device_details.site_id'),
            'missing_category' => $active($query)->whereNull('ant_device_details.category'),
            'missing_pic' => $active($query)->whereNull('ant_device_details.pic_user_id')
                ->where(fn ($q) => $q->whereNull('ant_device_details.pic_name')->orWhere('ant_device_details.pic_name', '')),
            'missing_location' => $active($query)
                ->where(fn ($q) => $q->whereNull('ant_device_details.installation_location')->orWhere('ant_device_details.installation_location', ''))
                ->where(fn ($q) => $q->whereNull('ant_device_details.rack')->orWhere('ant_device_details.rack', '')),
            'missing_serial' => $active($query)
                ->where(fn ($q) => $q->whereNull('ant_device_details.serial_number')->orWhere('ant_device_details.serial_number', ''))
                ->where(fn ($q) => $q->whereNull('devices.serial')->orWhere('devices.serial', '')),
            'unmapped_edge' => $active($query)->whereIn('ant_device_details.category', Catalog::EDGE_CATEGORIES)
                ->whereNotIn('ant_device_details.device_id', DeviceLink::where('status', DeviceLink::VERIFIED)->select('a_device_id'))
                ->whereNotIn('ant_device_details.device_id', DeviceLink::where('status', DeviceLink::VERIFIED)->select('b_device_id')),
            'duplicate_serial' => $active($query)->whereIn(DB::raw("COALESCE(NULLIF(ant_device_details.serial_number, ''), devices.serial)"), $this->duplicateSerials()),
            'duplicate_ip' => $active($query)->whereIn('devices.ip', $this->duplicateIps()),
            'stale' => $active($query)->where('devices.disabled', 0)->where('devices.ignore', 0)
                ->where(fn ($q) => $q->whereNull('devices.last_polled')->orWhere('devices.last_polled', '<', now()->subMinutes(self::STALE_MINUTES))),
            'warranty_expired' => $active($query)->whereNotNull('ant_device_details.warranty_until')->where('ant_device_details.warranty_until', '<', now()->toDateString()),
            default => $query,
        };
    }

    /**
     * @return array<string, int>
     */
    public function counts(User $user): array
    {
        $counts = [];
        foreach (array_keys(self::ISSUES) as $issue) {
            $query = DeviceDetail::query()
                ->join('devices', 'devices.device_id', '=', 'ant_device_details.device_id')
                ->whereIn('ant_device_details.device_id', Device::hasAccess($user)->select('devices.device_id'));
            $counts[$issue] = $this->apply($query, $issue)->count();
        }
        $counts['suggested_links'] = DeviceLink::where('status', DeviceLink::SUGGESTED)->count();

        return $counts;
    }

    private function duplicateSerials()
    {
        return DB::table('ant_device_details')
            ->join('devices', 'devices.device_id', '=', 'ant_device_details.device_id')
            ->whereNotIn('ant_device_details.verification_status', [DeviceDetail::REJECTED, DeviceDetail::RETIRED])
            ->selectRaw("COALESCE(NULLIF(ant_device_details.serial_number, ''), devices.serial) AS s")
            ->whereRaw("COALESCE(NULLIF(ant_device_details.serial_number, ''), devices.serial) IS NOT NULL")
            ->whereRaw("COALESCE(NULLIF(ant_device_details.serial_number, ''), devices.serial) != ''")
            ->groupBy('s')->havingRaw('COUNT(*) > 1')->pluck('s');
    }

    private function duplicateIps()
    {
        return DB::table('devices')->whereNotNull('ip')->where('ip', '!=', '')
            ->groupBy('ip')->havingRaw('COUNT(*) > 1')->pluck('ip');
    }
}
