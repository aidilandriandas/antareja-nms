<?php

namespace Antareja\Http\Controllers;

use Antareja\Models\Area;
use Antareja\Models\AuditLog;
use Antareja\Models\DeviceDetail;
use Antareja\Models\DeviceLink;
use Antareja\Models\Site;
use Antareja\Services\AttachmentService;
use Antareja\Services\InventoryQuery;
use Antareja\Services\InventorySync;
use Antareja\Support\Catalog;
use App\Models\Device;
use App\Models\Port;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryController extends Controller
{
    public function __construct(
        private readonly InventoryQuery $query,
        private readonly InventorySync $sync,
    ) {
    }

    public function index(Request $request): View
    {
        $details = $this->query->build($request, $request->user())->paginate(min(max((int) $request->input('per_page', 50), 10), 500))->withQueryString();
        $mapped = $this->query->mappedDeviceIds($details->pluck('device_id')->all());

        return view('antareja::inventory.index', [
            'details' => $details,
            'mapped' => $mapped,
            'filters' => $this->filterOptions(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->query->build($request, $request->user());
        $filename = 'antareja-inventory-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
            fputcsv($out, [
                'Device', 'Hostname', 'IP', 'MAC', 'Vendor/OS', 'Model', 'Serial', 'Asset tag', 'Kategori', 'Firmware',
                'Site', 'Area', 'Departemen', 'Lokasi instalasi', 'Rak', 'Jumlah port', 'Protokol manajemen', 'PIC', 'Kontak PIC',
                'Tanggal instalasi', 'Garansi s/d', 'Lifecycle', 'Status monitoring', 'Verifikasi', 'Kelengkapan %', 'Data belum lengkap',
            ]);

            $query->chunk(200, function ($details) use ($out): void {
                $mapped = $this->query->mappedDeviceIds($details->pluck('device_id')->all());
                $ports = Port::whereIn('device_id', $details->pluck('device_id'))->selectRaw('device_id, COUNT(*) AS c')->groupBy('device_id')->pluck('c', 'device_id');
                foreach ($details as $d) {
                    $c = $d->completeness(isset($mapped[$d->device_id]));
                    $dev = $d->device;
                    fputcsv($out, [
                        $dev?->displayName(), $dev?->hostname, $dev?->overwrite_ip ?: $dev?->ip, $d->mac_address, $dev?->os,
                        $d->effectiveModel(), $d->effectiveSerial(), $d->asset_tag, $d->categoryLabel(), $dev?->version,
                        $d->site?->code, $d->area?->name, $d->department, $d->installation_location, $d->rack, $ports[$d->device_id] ?? 0,
                        Catalog::label(Catalog::MANAGEMENT_PROTOCOLS, $d->management_protocol, ''), $d->picLabel(), $d->pic_phone,
                        $d->installed_at?->format('Y-m-d'), $d->warranty_until?->format('Y-m-d'),
                        Catalog::label(Catalog::LIFECYCLE, $d->lifecycle_status, ''),
                        $dev?->disabled ? 'Disabled' : ($dev?->status ? 'Online' : 'Offline'),
                        Catalog::VERIFICATION_DISPLAY[$d->displayStatus($c)]['label'], $c['score'], implode('; ', $c['missing']),
                    ]);
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function show(Device $device, AttachmentService $attachments): View
    {
        Gate::authorize('view', $device);
        $detail = $this->sync->ensure($device)->load(['site', 'area', 'picUser', 'verifier']);
        $links = DeviceLink::with(['aDevice', 'bDevice', 'aPort', 'bPort'])
            ->where(fn ($q) => $q->where('a_device_id', $device->device_id)->orWhere('b_device_id', $device->device_id))
            ->orderByRaw("FIELD(status, 'verified', 'suggested', 'rejected')")->get();
        $completeness = $detail->completeness($links->where('status', DeviceLink::VERIFIED)->isNotEmpty());

        return view('antareja::inventory.show', [
            'device' => $device->load('location'),
            'detail' => $detail,
            'completeness' => $completeness,
            'status' => $detail->displayStatus($completeness),
            'links' => $links,
            'attachments' => $attachments->for('device', $device->device_id),
            'portCount' => Port::where('device_id', $device->device_id)->count(),
            'macAddress' => $detail->mac_address ?: $this->primaryMac($device),
            'history' => AuditLog::where('auditable_type', 'device')->where('auditable_id', (string) $device->device_id)
                ->latest('created_at')->limit(20)->get(),
        ]);
    }

    public function edit(Device $device): View
    {
        Gate::authorize('view', $device);
        $detail = $this->sync->ensure($device);

        return view('antareja::inventory.edit', [
            'device' => $device,
            'detail' => $detail,
            'sites' => Site::where('is_active', true)->orWhere('id', $detail->site_id)->orderBy('code')->get(),
            'areas' => Area::orderBy('name')->get(['id', 'site_id', 'name']),
            'users' => User::orderBy('username')->get(['user_id', 'username', 'realname']),
            'departments' => DeviceDetail::whereNotNull('department')->distinct()->orderBy('department')->pluck('department'),
            'categories' => Catalog::DEVICE_CATEGORIES,
            'protocols' => Catalog::MANAGEMENT_PROTOCOLS,
            'lifecycle' => Catalog::LIFECYCLE,
        ]);
    }

    public function update(Request $request, Device $device): RedirectResponse
    {
        Gate::authorize('view', $device);
        $detail = $this->sync->ensure($device);

        $data = $request->validate([
            'category' => ['nullable', Rule::in(array_keys(Catalog::DEVICE_CATEGORIES))],
            'site_id' => ['nullable', 'integer', 'exists:ant_sites,id'],
            'area_id' => ['nullable', 'integer', Rule::exists('ant_areas', 'id')->where('site_id', (int) $request->input('site_id'))],
            'department' => ['nullable', 'string', 'max:128'],
            'installation_location' => ['nullable', 'string', 'max:255'],
            'rack' => ['nullable', 'string', 'max:64'],
            'serial_number' => ['nullable', 'string', 'max:128'],
            'asset_tag' => ['nullable', 'string', 'max:64'],
            'model' => ['nullable', 'string', 'max:128'],
            'mac_address' => ['nullable', 'string', 'max:32', 'regex:/^([0-9A-Fa-f]{2}[:\-.]?){5}[0-9A-Fa-f]{2}$/'],
            'management_protocol' => ['nullable', Rule::in(array_keys(Catalog::MANAGEMENT_PROTOCOLS))],
            'pic_user_id' => ['nullable', 'integer', 'exists:users,user_id'],
            'pic_name' => ['nullable', 'string', 'max:128'],
            'pic_phone' => ['nullable', 'string', 'max:64'],
            'installed_at' => ['nullable', 'date'],
            'warranty_until' => ['nullable', 'date'],
            'warranty_info' => ['nullable', 'string', 'max:2000'],
            'lifecycle_status' => ['required', Rule::in(array_keys(Catalog::LIFECYCLE))],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], [
            'mac_address.regex' => 'Format MAC tidak valid (contoh: 00:11:22:AA:BB:CC).',
            'area_id.exists' => 'Area harus berada di site yang dipilih.',
        ], ['site_id' => 'site', 'area_id' => 'area', 'pic_user_id' => 'PIC']);

        if (! empty($data['mac_address'])) {
            $hex = strtolower(preg_replace('/[^0-9a-fA-F]/', '', $data['mac_address']));
            $data['mac_address'] = implode(':', str_split($hex, 2));
        }

        $detail->update($data);

        $message = "Data inventaris {$device->displayName()} disimpan.";
        if ($request->boolean('verify_after_save') && $request->user()->can('ant-discovery.approve')) {
            try {
                app(\Antareja\Services\VerificationService::class)->verify($detail->fresh());
                $message .= ' Perangkat diverifikasi sebagai inventaris resmi.';
            } catch (\Illuminate\Validation\ValidationException $e) {
                return redirect()->route('antareja.inventory.edit', $device)->withErrors($e->errors())->with('antareja_success', $message);
            }
        }

        return redirect()->route('antareja.inventory.show', $device)->with('antareja_success', $message);
    }

    private function primaryMac(Device $device): ?string
    {
        $mac = Port::where('device_id', $device->device_id)->whereNotNull('ifPhysAddress')->where('ifPhysAddress', '!=', '')
            ->orderByRaw("ifOperStatus = 'up' DESC")->orderBy('ifIndex')->value('ifPhysAddress');
        if (! $mac) {
            return null;
        }
        $hex = strtolower(preg_replace('/[^0-9a-fA-F]/', '', $mac));

        return strlen($hex) === 12 ? implode(':', str_split($hex, 2)) : null;
    }

    private function filterOptions(): array
    {
        return [
            'sites' => Site::orderBy('code')->get(['id', 'code', 'name']),
            'areas' => Area::with('site:id,code')->orderBy('name')->get(['id', 'site_id', 'name']),
            'categories' => Catalog::DEVICE_CATEGORIES,
            'departments' => DeviceDetail::whereNotNull('department')->distinct()->orderBy('department')->pluck('department'),
            'vendors' => Device::whereNotNull('os')->distinct()->orderBy('os')->pluck('os'),
        ];
    }
}
