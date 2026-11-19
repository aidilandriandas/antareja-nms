<?php

namespace Antareja\Services;

use Antareja\Models\DeviceDetail;
use Antareja\Models\Site;
use Antareja\Support\Catalog;
use App\Models\Device;

/**
 * Menjamin setiap device LibreNMS punya record ant_device_details.
 * Device baru (hasil discovery / ditambah manual) masuk status "pending" dan menunggu verifikasi engineer.
 */
class InventorySync
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function ensure(Device $device): DeviceDetail
    {
        $detail = DeviceDetail::find($device->device_id);
        if ($detail) {
            // Perbarui saran kategori bila LibreNMS sudah mengenali OS setelah discovery
            $suggested = Catalog::suggestCategory($device->os, $device->type, $device->hardware);
            if ($suggested && $detail->suggested_category !== $suggested) {
                $detail->forceFill(['suggested_category' => $suggested])->saveQuietly();
            }

            return $detail;
        }

        $detail = new DeviceDetail(['device_id' => $device->device_id]);
        $detail->forceFill([
            'verification_status' => DeviceDetail::PENDING,
            'lifecycle_status' => 'operational',
            'suggested_category' => Catalog::suggestCategory($device->os, $device->type, $device->hardware),
            'discovered_at' => $device->inserted ?? now(),
            'site_id' => $this->suggestSiteId($device),
        ]);
        $detail->saveQuietly();

        $this->audit->log('created', $detail, sprintf('Device %s terdeteksi, menunggu verifikasi', $device->displayName()));

        return $detail;
    }

    /**
     * @return array{created:int, total:int}
     */
    public function syncAll(): array
    {
        $created = 0;
        $existing = DeviceDetail::pluck('device_id')->flip();

        Device::query()->orderBy('device_id')->chunk(200, function ($devices) use (&$created, $existing): void {
            foreach ($devices as $device) {
                $isNew = ! isset($existing[$device->device_id]);
                $this->ensure($device);
                $created += $isNew ? 1 : 0;
            }
        });

        return ['created' => $created, 'total' => Device::count()];
    }

    /** Saran site: site yang dipetakan ke lokasi LibreNMS (sysLocation) device ini. */
    public function suggestSiteId(Device $device): ?int
    {
        if (! $device->location_id) {
            return null;
        }

        return Site::where('location_id', $device->location_id)->where('is_active', true)->value('id');
    }
}
