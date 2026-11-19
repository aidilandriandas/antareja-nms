<?php

namespace Antareja\Services;

use Antareja\Models\DeviceDetail;
use Antareja\Models\DeviceLink;
use App\Models\Device;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Query inventaris dengan filter yang dipakai bersama oleh halaman daftar, export CSV, dan discovery.
 */
class InventoryQuery
{
    public const FILTERS = ['q', 'site', 'area', 'category', 'department', 'verification', 'state', 'vendor', 'issue'];

    public function __construct(private readonly DataQuality $quality)
    {
    }

    public function build(Request $request, User $user): Builder
    {
        $query = DeviceDetail::query()
            ->select('ant_device_details.*')
            ->join('devices', 'devices.device_id', '=', 'ant_device_details.device_id')
            ->whereIn('ant_device_details.device_id', Device::hasAccess($user)->select('devices.device_id'))
            ->with(['device', 'site', 'area', 'picUser']);

        if ($request->filled('q')) {
            $term = '%' . $request->input('q') . '%';
            $query->where(fn ($q) => $q
                ->where('devices.hostname', 'like', $term)
                ->orWhere('devices.sysName', 'like', $term)
                ->orWhere('devices.display', 'like', $term)
                ->orWhere('devices.serial', 'like', $term)
                ->orWhere('devices.hardware', 'like', $term)
                ->orWhere('ant_device_details.serial_number', 'like', $term)
                ->orWhere('ant_device_details.asset_tag', 'like', $term)
                ->orWhere('ant_device_details.mac_address', 'like', $term)
                ->orWhere('ant_device_details.installation_location', 'like', $term)
                ->orWhere('ant_device_details.pic_name', 'like', $term));
        }

        $site = $request->input('site');
        if ($site === 'none') {
            $query->whereNull('ant_device_details.site_id');
        } elseif (filled($site)) {
            $query->where('ant_device_details.site_id', (int) $site);
        }

        if ($request->filled('area')) {
            $query->where('ant_device_details.area_id', (int) $request->input('area'));
        }

        $category = $request->input('category');
        if ($category === 'none') {
            $query->whereNull('ant_device_details.category');
        } elseif (filled($category)) {
            $query->where('ant_device_details.category', $category);
        }

        if ($request->filled('department')) {
            $query->where('ant_device_details.department', $request->input('department'));
        }

        if ($request->filled('vendor')) {
            $query->where('devices.os', $request->input('vendor'));
        }

        match ($request->input('state')) {
            'up' => $query->where('devices.status', 1)->where('devices.disabled', 0),
            'down' => $query->where('devices.status', 0)->where('devices.disabled', 0)->where('devices.ignore', 0),
            'disabled' => $query->where('devices.disabled', 1),
            default => null,
        };

        match ($request->input('verification')) {
            'pending' => $query->where('ant_device_details.verification_status', DeviceDetail::PENDING),
            'verified' => $query->where('ant_device_details.verification_status', DeviceDetail::VERIFIED),
            'rejected' => $query->where('ant_device_details.verification_status', DeviceDetail::REJECTED),
            'retired' => $query->where('ant_device_details.verification_status', DeviceDetail::RETIRED),
            'all' => null,
            // default: sembunyikan rejected & retired
            default => $query->whereNotIn('ant_device_details.verification_status', [DeviceDetail::REJECTED, DeviceDetail::RETIRED]),
        };

        if ($request->filled('issue') && array_key_exists($request->input('issue'), DataQuality::ISSUES)) {
            $this->quality->apply($query, $request->input('issue'));
        }

        $sort = $request->input('sort', 'name');
        match ($sort) {
            'site' => $query->leftJoin('ant_sites', 'ant_sites.id', '=', 'ant_device_details.site_id')->orderBy('ant_sites.code')->orderBy('devices.hostname'),
            'category' => $query->orderBy('ant_device_details.category')->orderBy('devices.hostname'),
            'status' => $query->orderBy('devices.status')->orderBy('devices.hostname'),
            'discovered' => $query->orderByDesc('ant_device_details.discovered_at'),
            default => $query->orderByRaw("COALESCE(NULLIF(devices.display, ''), devices.sysName, devices.hostname)"),
        };

        return $query;
    }

    /**
     * Device id yang punya mapping terverifikasi (untuk perhitungan kelengkapan tanpa N+1 query).
     *
     * @param  int[]  $deviceIds
     * @return Collection<int, true>
     */
    public function mappedDeviceIds(array $deviceIds): Collection
    {
        if ($deviceIds === []) {
            return collect();
        }

        $links = DeviceLink::where('status', DeviceLink::VERIFIED)
            ->where(fn ($q) => $q->whereIn('a_device_id', $deviceIds)->orWhereIn('b_device_id', $deviceIds))
            ->get(['a_device_id', 'b_device_id']);

        return $links->flatMap(fn ($l) => [$l->a_device_id, $l->b_device_id])->unique()->flip()->map(fn () => true);
    }
}
