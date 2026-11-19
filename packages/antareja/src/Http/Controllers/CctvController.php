<?php

namespace Antareja\Http\Controllers;

use Antareja\Models\DeviceDetail;
use Antareja\Models\DeviceLink;
use Antareja\Models\IpAddress;
use Antareja\Models\Site;
use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * CCTV Infrastructure: kamera & NVR, status, port switch, relasi NVR, PIC, riwayat gangguan (outage).
 * Kamera tanpa SNMP tetap dipantau dengan ICMP (ping-only) oleh poller.
 */
class CctvController extends Controller
{
    public function index(Request $request): View
    {
        $allowed = Device::hasAccess($request->user())->pluck('device_id');
        $details = DeviceDetail::with(['device', 'site', 'area', 'picUser'])->active()
            ->whereIn('category', ['cctv-camera', 'nvr'])->whereIn('device_id', $allowed)
            ->when($request->filled('site'), fn ($q) => $q->where('site_id', (int) $request->input('site')))
            ->get()->filter(fn ($d) => $d->device);

        $ids = $details->pluck('device_id');
        $links = DeviceLink::with(['aDevice', 'aPort'])->where('status', DeviceLink::VERIFIED)
            ->whereIn('b_device_id', $ids)->whereIn('link_type', ['cctv-port', 'nvr-camera'])->get();
        $switchPort = $links->where('link_type', 'cctv-port')->keyBy('b_device_id');
        $nvrOf = $links->where('link_type', 'nvr-camera')->keyBy('b_device_id');

        // Riwayat gangguan 30 hari dari outage poller
        $since = now()->subDays(30)->getTimestamp();
        $outages = DB::table('device_outages')->whereIn('device_id', $ids)->where('going_down', '>=', $since)
            ->selectRaw('device_id, COUNT(*) AS n, MAX(going_down) AS last_down, SUM(COALESCE(up_again, UNIX_TIMESTAMP()) - going_down) AS seconds')
            ->groupBy('device_id')->get()->keyBy('device_id');

        // MAC: inventaris → IPAM → interface
        $ipamMac = IpAddress::whereIn('device_id', $ids)->whereNotNull('mac_address')->pluck('mac_address', 'device_id');

        $cameras = $details->where('category', 'cctv-camera')
            ->when($request->input('nvr'), fn ($c, $nvr) => $c->filter(fn ($d) => (string) ($nvrOf[$d->device_id]->a_device_id ?? '') === (string) $nvr))
            ->when($request->input('state') === 'down', fn ($c) => $c->filter(fn ($d) => ! $d->device->status && ! $d->device->disabled))
            ->when($request->input('state') === 'unmapped', fn ($c) => $c->filter(fn ($d) => ! isset($switchPort[$d->device_id])))
            ->sortBy(fn ($d) => [$d->device->status ? 1 : 0, $d->site?->code, $d->device->displayName()]);

        $nvrs = $details->where('category', 'nvr')->sortBy(fn ($d) => $d->device->displayName());
        $camerasPerNvr = $nvrOf->groupBy(fn ($l) => $l->a_device_id);
        $allCameras = $details->where('category', 'cctv-camera');

        return view('antareja::cctv.index', [
            'cameras' => $cameras,
            'nvrs' => $nvrs,
            'switchPort' => $switchPort,
            'nvrOf' => $nvrOf,
            'camerasPerNvr' => $camerasPerNvr,
            'outages' => $outages,
            'ipamMac' => $ipamMac,
            'cameraStatus' => $allCameras->mapWithKeys(fn ($d) => [$d->device_id => (bool) $d->device->status]),
            'totals' => [
                'cameras' => $allCameras->count(),
                'online' => $allCameras->filter(fn ($d) => $d->device->status)->count(),
                'offline' => $allCameras->filter(fn ($d) => ! $d->device->status && ! $d->device->disabled)->count(),
                'nvrs' => $nvrs->count(),
                'unmapped' => $allCameras->reject(fn ($d) => isset($switchPort[$d->device_id]))->count(),
                'no_nvr' => $allCameras->reject(fn ($d) => isset($nvrOf[$d->device_id]))->count(),
            ],
            'sites' => Site::orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }
}
