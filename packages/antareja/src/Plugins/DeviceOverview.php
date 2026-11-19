<?php

namespace Antareja\Plugins;

use Antareja\Models\DeviceDetail;
use Antareja\Models\DeviceLink;
use Antareja\Services\InventorySync;
use App\Models\Device;
use App\Plugins\Hooks\DeviceOverviewHook;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Panel "ANTAREJA Inventaris" di halaman overview device LibreNMS.
 */
class DeviceOverview extends DeviceOverviewHook
{
    public string $view = 'partials.device-overview';

    public function authorize(Authenticatable $user, Device $device): bool
    {
        return $user->can('ant-inventory.view');
    }

    public function data(Device $device): array
    {
        $detail = app(InventorySync::class)->ensure($device)->load(['site', 'area', 'picUser']);
        $links = DeviceLink::with(['aDevice', 'bDevice', 'aPort', 'bPort'])
            ->where('status', '!=', DeviceLink::REJECTED)
            ->where(fn ($q) => $q->where('a_device_id', $device->device_id)->orWhere('b_device_id', $device->device_id))
            ->get();
        $completeness = $detail->completeness($links->where('status', DeviceLink::VERIFIED)->isNotEmpty());

        return [
            'device' => $device,
            'detail' => $detail,
            'links' => $links,
            'completeness' => $completeness,
            'status' => $detail->displayStatus($completeness),
        ];
    }
}
