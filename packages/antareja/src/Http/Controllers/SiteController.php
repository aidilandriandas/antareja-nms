<?php

namespace Antareja\Http\Controllers;

use Antareja\Models\Area;
use Antareja\Models\DeviceDetail;
use Antareja\Models\Site;
use Antareja\Support\Catalog;
use App\Models\Location;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SiteController extends Controller
{
    public function index(Request $request): View
    {
        $sites = Site::query()
            ->withCount(['areas', 'devices as devices_count' => fn ($q) => $q->active()])
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->input('type')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', '%' . $request->input('q') . '%')
                ->orWhere('code', 'like', '%' . $request->input('q') . '%')))
            ->orderBy('code')
            ->paginate(50)->withQueryString();

        // Status perangkat per site (online/offline) dari data polling LibreNMS
        $status = DeviceDetail::query()->active()
            ->join('devices', 'devices.device_id', '=', 'ant_device_details.device_id')
            ->whereNotNull('site_id')->where('devices.disabled', 0)
            ->selectRaw('site_id, SUM(devices.status = 1) AS up, SUM(devices.status = 0) AS down')
            ->groupBy('site_id')->get()->keyBy('site_id');

        return view('antareja::sites.index', [
            'sites' => $sites,
            'status' => $status,
            'types' => Catalog::SITE_TYPES,
            'unassigned' => DeviceDetail::active()->whereNull('site_id')->count(),
        ]);
    }

    public function show(Site $site): View
    {
        $site->load(['areas', 'location', 'picUser']);
        $devices = DeviceDetail::with(['device', 'area'])->active()->where('site_id', $site->id)
            ->get()->sortBy(fn ($d) => $d->device?->displayName());

        return view('antareja::sites.show', ['site' => $site, 'devices' => $devices]);
    }

    public function create(): View
    {
        return view('antareja::sites.form', $this->formData(new Site(['type' => 'mining-site', 'is_active' => true])));
    }

    public function store(Request $request): RedirectResponse
    {
        $site = Site::create($this->validated($request));

        return redirect()->route('antareja.sites.edit', $site)->with('antareja_success', "Site {$site->code} dibuat. Tambahkan area bila perlu.");
    }

    public function edit(Site $site): View
    {
        return view('antareja::sites.form', $this->formData($site->load('areas')));
    }

    public function update(Request $request, Site $site): RedirectResponse
    {
        $site->update($this->validated($request, $site));

        return redirect()->route('antareja.sites.show', $site)->with('antareja_success', "Site {$site->code} disimpan.");
    }

    public function destroy(Site $site): RedirectResponse
    {
        if (DeviceDetail::where('site_id', $site->id)->exists()) {
            return back()->with('antareja_error', "Site {$site->code} masih dipakai perangkat. Pindahkan perangkat atau nonaktifkan site.");
        }
        $site->delete();

        return redirect()->route('antareja.sites.index')->with('antareja_success', "Site {$site->code} dihapus.");
    }

    public function storeArea(Request $request, Site $site): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:128', Rule::unique('ant_areas', 'name')->where('site_id', $site->id)],
            'code' => ['nullable', 'string', 'max:32'],
            'description' => ['nullable', 'string', 'max:1000'],
        ], [], ['name' => 'nama area']);
        $site->areas()->create($data);

        return back()->with('antareja_success', "Area {$data['name']} ditambahkan ke {$site->code}.");
    }

    public function destroyArea(Site $site, Area $area): RedirectResponse
    {
        DeviceDetail::where('area_id', $area->id)->update(['area_id' => null]);
        $area->delete();

        return back()->with('antareja_success', "Area {$area->name} dihapus.");
    }

    private function validated(Request $request, ?Site $site = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('ant_sites', 'code')->ignore($site?->id)],
            'name' => ['required', 'string', 'max:128'],
            'type' => ['required', Rule::in(array_keys(Catalog::SITE_TYPES))],
            'description' => ['nullable', 'string', 'max:2000'],
            'address' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'pic_user_id' => ['nullable', 'integer', 'exists:users,user_id'],
            'pic_name' => ['nullable', 'string', 'max:128'],
            'pic_phone' => ['nullable', 'string', 'max:64'],
            'is_active' => ['nullable', 'boolean'],
        ], ['code.regex' => 'Kode site hanya boleh huruf, angka, titik, minus, dan garis bawah.'], [
            'code' => 'kode site', 'name' => 'nama site', 'type' => 'tipe site',
        ]);
        $data['code'] = strtoupper($data['code']);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    private function formData(Site $site): array
    {
        return [
            'site' => $site,
            'types' => Catalog::SITE_TYPES,
            'locations' => Location::orderBy('location')->get(['id', 'location']),
            'users' => User::orderBy('username')->get(['user_id', 'username', 'realname']),
        ];
    }
}
