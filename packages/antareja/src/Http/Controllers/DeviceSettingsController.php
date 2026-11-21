<?php

namespace Antareja\Http\Controllers;

use Antareja\Models\DeviceLink;
use Antareja\Models\Site;
use Antareja\Services\AuditLogger;
use Antareja\Services\DeviceGroupSync;
use Antareja\Services\InventorySync;
use Antareja\Support\Catalog;
use App\Models\Device;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Pengaturan perangkat versi NOC: nama, grup, site/area, uji koneksi — tanpa data aset (serial, PIC, garansi). */
class DeviceSettingsController extends Controller
{
    public function edit(Device $device, InventorySync $inventory): View
    {
        Gate::authorize('view', $device);
        $detail = $inventory->ensure($device);
        $links = DeviceLink::with(['aDevice', 'bDevice', 'aPort', 'bPort'])->whereIn('status', [DeviceLink::VERIFIED, DeviceLink::SUGGESTED])
            ->where(fn ($q) => $q->where('a_device_id', $device->device_id)->orWhere('b_device_id', $device->device_id))->get();

        return view('antareja::devices.settings', [
            'device' => $device,
            'detail' => $detail,
            'links' => $links,
            'categories' => Catalog::GROUP_NAMES,
            'sites' => Site::with('areas')->where('is_active', true)->orderBy('code')->get(),
            'canEdit' => request()->user()->can('device.update') || request()->user()->can('ant-inventory.update'),
        ]);
    }

    public function update(Request $request, Device $device, InventorySync $inventory, DeviceGroupSync $groups, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('view', $device);
        abort_unless($request->user()->can('device.update') || $request->user()->can('ant-inventory.update'), 403);
        $data = $request->validate([
            'display_name' => ['nullable', 'string', 'max:128'],
            'category' => ['nullable', Rule::in(array_keys(Catalog::DEVICE_CATEGORIES))],
            'site_id' => ['nullable', 'integer', 'exists:ant_sites,id'],
            'area_id' => ['nullable', 'integer', Rule::exists('ant_areas', 'id')->where('site_id', (int) $request->input('site_id'))],
        ], ['area_id.exists' => 'Area harus berada di site yang dipilih.'], ['category' => 'grup', 'site_id' => 'site', 'area_id' => 'area']);

        $name = trim((string) ($data['display_name'] ?? ''));
        if ($name !== (string) $device->display_template) {
            $device->display_template = $name !== '' ? $name : null;
            $device->save();
        }
        $detail = $inventory->ensure($device);
        $detail->forceFill(['category' => $data['category'] ?? null, 'site_id' => $data['site_id'] ?? null, 'area_id' => $data['area_id'] ?? null])->save();
        $groups->syncDevice($device->device_id, $detail->category);
        $audit->log('updated', $detail, "Pengaturan perangkat {$device->displayName()} diperbarui (nama/grup/site)");

        return redirect()->route('antareja.devices.edit', $device)->with('antareja_success', "Pengaturan {$device->displayName()} disimpan.");
    }
}
