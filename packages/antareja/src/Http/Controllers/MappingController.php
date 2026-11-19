<?php

namespace Antareja\Http\Controllers;

use Antareja\Models\DeviceLink;
use Antareja\Models\Site;
use Antareja\Services\MappingService;
use Antareja\Support\Catalog;
use App\Models\Device;
use App\Models\Port;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MappingController extends Controller
{
    public function __construct(private readonly MappingService $mapping)
    {
    }

    public function index(Request $request): View
    {
        $allowed = Device::hasAccess($request->user())->select('device_id');
        $status = $request->input('status', 'open');

        $links = DeviceLink::with(['aDevice', 'bDevice', 'aPort', 'bPort', 'verifier'])
            ->whereIn('a_device_id', $allowed)->whereIn('b_device_id', $allowed)
            ->when($status === 'open', fn ($q) => $q->where('status', '!=', DeviceLink::REJECTED))
            ->when(in_array($status, ['suggested', 'verified', 'rejected'], true), fn ($q) => $q->where('status', $status))
            ->when($request->filled('type'), fn ($q) => $q->where('link_type', $request->input('type')))
            ->when($request->filled('site'), fn ($q) => $q->where(fn ($q) => $q
                ->whereIn('a_device_id', fn ($s) => $s->select('device_id')->from('ant_device_details')->where('site_id', (int) $request->input('site')))
                ->orWhereIn('b_device_id', fn ($s) => $s->select('device_id')->from('ant_device_details')->where('site_id', (int) $request->input('site')))))
            ->when($request->filled('q'), function ($q) use ($request): void {
                $ids = Device::where('hostname', 'like', '%' . $request->input('q') . '%')
                    ->orWhere('sysName', 'like', '%' . $request->input('q') . '%')
                    ->orWhere('display', 'like', '%' . $request->input('q') . '%')->pluck('device_id');
                $q->where(fn ($q) => $q->whereIn('a_device_id', $ids)->orWhereIn('b_device_id', $ids));
            })
            ->orderByRaw("FIELD(status, 'suggested', 'verified', 'rejected')")->latest('updated_at')
            ->paginate(50)->withQueryString();

        $counts = DeviceLink::selectRaw('status, COUNT(*) AS c')->groupBy('status')->pluck('c', 'status');

        return view('antareja::mapping.index', [
            'links' => $links,
            'counts' => $counts,
            'status' => $status,
            'sites' => Site::orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function create(Request $request): View
    {
        $devices = Device::hasAccess($request->user())->orderBy('hostname')->get();

        return view('antareja::mapping.create', [
            'devices' => $devices,
            'types' => Catalog::LINK_TYPES,
            'preselect' => (int) $request->input('device'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'a_device_id' => ['required', 'integer', 'exists:devices,device_id'],
            'a_port_id' => ['nullable', 'integer', Rule::exists('ports', 'port_id')->where('device_id', (int) $request->input('a_device_id'))],
            'b_device_id' => ['required', 'integer', 'different:a_device_id', 'exists:devices,device_id'],
            'b_port_id' => ['nullable', 'integer', Rule::exists('ports', 'port_id')->where('device_id', (int) $request->input('b_device_id'))],
            'link_type' => ['required', Rule::in(array_keys(Catalog::LINK_TYPES))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'b_device_id.different' => 'Perangkat A dan B harus berbeda.',
            'a_port_id.exists' => 'Port A harus milik perangkat A.',
            'b_port_id.exists' => 'Port B harus milik perangkat B.',
        ], ['a_device_id' => 'perangkat A', 'b_device_id' => 'perangkat B', 'link_type' => 'tipe relasi']);

        foreach (['a_device_id', 'b_device_id'] as $key) {
            Gate::authorize('view', Device::findOrFail($data[$key]));
        }

        $link = new DeviceLink($data);
        $link->forceFill(['status' => DeviceLink::SUGGESTED, 'source' => 'manual', 'created_by' => $request->user()->user_id]);
        $link->save();

        if ($request->boolean('verify_now') && $request->user()->can('ant-mapping.verify')) {
            $this->mapping->verify($link);
        }

        return redirect()->route('antareja.mapping.index')->with('antareja_success', 'Mapping disimpan' . ($link->status === DeviceLink::VERIFIED ? ' dan diverifikasi.' : ', menunggu verifikasi.'));
    }

    public function verify(DeviceLink $link): RedirectResponse
    {
        $this->authorizeLink($link);
        $this->mapping->verify($link);

        return back()->with('antareja_success', 'Mapping diverifikasi.' . ($link->isParentChild() ? ' Dependensi parent/child alert ikut diperbarui.' : ''));
    }

    public function reject(DeviceLink $link): RedirectResponse
    {
        $this->authorizeLink($link);
        $this->mapping->reject($link);

        return back()->with('antareja_success', 'Mapping ditolak dan tidak akan disarankan ulang.');
    }

    public function destroy(DeviceLink $link): RedirectResponse
    {
        $this->authorizeLink($link);
        $this->mapping->delete($link);

        return back()->with('antareja_success', 'Mapping dihapus.');
    }

    public function suggest(): RedirectResponse
    {
        $result = $this->mapping->generateSuggestions();

        return back()->with('antareja_success', "Saran baru: {$result['lldp']} dari LLDP/CDP, {$result['fdb']} dari tabel MAC (FDB/ARP).");
    }

    public function ports(Device $device): JsonResponse
    {
        Gate::authorize('view', $device);

        return response()->json(Port::where('device_id', $device->device_id)
            ->where('deleted', 0)->orderBy('ifIndex')
            ->get(['port_id', 'ifName', 'ifDescr', 'ifAlias', 'ifOperStatus'])
            ->map(fn (Port $p) => [
                'id' => $p->port_id,
                'label' => trim($p->getShortLabel() . ($p->ifAlias && $p->ifAlias !== $p->ifName ? " — {$p->ifAlias}" : '')),
                'up' => $p->ifOperStatus === 'up',
            ]));
    }

    private function authorizeLink(DeviceLink $link): void
    {
        foreach ([$link->aDevice, $link->bDevice] as $device) {
            abort_unless($device && Gate::allows('view', $device), 403);
        }
    }
}
