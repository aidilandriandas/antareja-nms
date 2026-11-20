<?php

namespace Antareja\Http\Controllers;

use Antareja\Models\Service;
use Antareja\Models\Site;
use Antareja\Models\SlaMonthly;
use Antareja\Services\SlaCalculator;
use App\Models\Device;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SlaController extends Controller
{
    public function index(Request $request): View
    {
        $month = $this->month($request);
        $all = Service::with(['devices:device_id', 'site'])->withCount('devices')->where('active', true)->orderBy('sort')->orderBy('name')->get();
        $stats = SlaCalculator::monthlyForServices($month);
        $states = SlaCalculator::deviceStates();
        $live = $all->mapWithKeys(fn (Service $s) => [$s->id => SlaCalculator::liveState($s, $states, $all)]);

        return view('antareja::sla.index', [
            'month' => $month,
            'tree' => $this->flatten($all),
            'stats' => $stats,
            'live' => $live,
            'inactive' => Service::where('active', false)->orderBy('name')->get(),
        ]);
    }

    public function show(Request $request, Service $service): View
    {
        $month = $this->month($request);
        $calc = SlaCalculator::forMonth($month);
        $service->load(['devices', 'site', 'parent', 'children']);
        $stats = $calc->service($service);
        $states = SlaCalculator::deviceStates();
        $all = Service::with('devices:device_id')->where('active', true)->get();

        // Riwayat 12 bulan: bulan tertutup dari snapshot, bulan berjalan langsung
        $history = SlaMonthly::where('service_id', $service->id)->where('month', '>=', $month->subMonths(11)->toDateString())
            ->where('month', '<', $month->toDateString())->orderBy('month')->get();

        $deviceStats = $service->devices->mapWithKeys(fn (Device $d) => [$d->device_id => $calc->device($d->device_id, $d->inserted ? \Carbon\Carbon::parse($d->inserted)->getTimestamp() : null)]);

        return view('antareja::sla.show', [
            'service' => $service,
            'month' => $month,
            'stats' => $stats,
            'live' => SlaCalculator::liveState($service, $states, $all),
            'deviceStates' => $states,
            'deviceStats' => $deviceStats,
            'childStats' => $service->children->mapWithKeys(fn (Service $c) => [$c->id => $calc->service($c)]),
            'childLive' => $service->children->mapWithKeys(fn (Service $c) => [$c->id => SlaCalculator::liveState($c, $states, $all)]),
            'history' => $history,
        ]);
    }

    /** Ketersediaan per perangkat untuk satu bulan (semua perangkat aktif). */
    public function devices(Request $request): View
    {
        $month = $this->month($request);
        $calc = SlaCalculator::forMonth($month);
        $siteOf = DB::table('ant_device_details')->join('ant_sites', 'ant_sites.id', '=', 'ant_device_details.site_id')
            ->pluck('ant_sites.code', 'ant_device_details.device_id');
        $q = Device::where('disabled', 0)->where('ignore', 0)->orderBy('hostname');
        if ($request->filled('site')) {
            $q->whereIn('device_id', DB::table('ant_device_details')->where('site_id', $request->integer('site'))->pluck('device_id'));
        }
        $rows = $q->get()->reject(fn (Device $d) => $d->inserted && \Carbon\Carbon::parse($d->inserted)->getTimestamp() >= $calc->to)
            ->map(fn (Device $d) => ['device' => $d, 'site' => $siteOf[$d->device_id] ?? null,
                'stats' => $calc->device($d->device_id, $d->inserted ? \Carbon\Carbon::parse($d->inserted)->getTimestamp() : null)])
            ->sortBy(fn ($r) => $r['stats']['availability'] ?? 101)->values();

        return view('antareja::sla.devices', [
            'month' => $month,
            'rows' => $rows,
            'sites' => Site::orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function create(Request $request): View
    {
        return view('antareja::sla.form', $this->formData(new Service([
            'calc' => 'all', 'sla_target' => 99.5, 'active' => true, 'parent_id' => $request->integer('parent') ?: null,
        ]), []));
    }

    public function store(Request $request): RedirectResponse
    {
        [$data, $devices] = $this->validated($request);
        $service = Service::create($data);
        $service->devices()->sync($devices);

        return redirect()->route('antareja.sla.show', $service)->with('antareja_success', "Layanan {$service->name} dibuat dengan " . count($devices) . ' perangkat.');
    }

    public function edit(Service $service): View
    {
        return view('antareja::sla.form', $this->formData($service, $service->devices()->pluck('devices.device_id')->all()));
    }

    public function update(Request $request, Service $service): RedirectResponse
    {
        [$data, $devices] = $this->validated($request, $service);
        $service->update($data);
        $service->devices()->sync($devices);

        return redirect()->route('antareja.sla.show', $service)->with('antareja_success', "Layanan {$service->name} disimpan.");
    }

    public function destroy(Service $service): RedirectResponse
    {
        Service::where('parent_id', $service->id)->update(['parent_id' => $service->parent_id]);
        $service->delete();

        return redirect()->route('antareja.sla.index')->with('antareja_success', "Layanan {$service->name} dihapus. Layanan anaknya dipindah ke induk di atasnya.");
    }

    private function validated(Request $request, ?Service $service = null): array
    {
        $exclude = $service?->exists ? $service->descendantIds() : [];
        $data = $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'description' => ['nullable', 'string', 'max:2000'],
            'parent_id' => ['nullable', 'integer', 'exists:ant_services,id', Rule::notIn($exclude)],
            'site_id' => ['nullable', 'integer', 'exists:ant_sites,id'],
            'calc' => ['required', Rule::in(array_keys(Service::CALC))],
            'min_up' => ['nullable', 'required_if:calc,min', 'integer', 'min:1', 'max:1000'],
            'sla_target' => ['required', 'numeric', 'between:50,100'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'devices' => ['nullable', 'array', 'max:2000'],
            'devices.*' => ['integer', 'exists:devices,device_id'],
        ], [
            'parent_id.not_in' => 'Layanan induk tidak boleh layanan ini sendiri atau turunannya.',
            'min_up.required_if' => 'Isi jumlah minimal anggota yang harus up.',
        ], ['name' => 'nama', 'sla_target' => 'target SLA', 'min_up' => 'minimal up']);

        $devices = $data['devices'] ?? [];
        unset($data['devices']);
        $data['active'] = $request->boolean('active');
        $data['sort'] = $data['sort'] ?? 0;
        if ($data['calc'] !== 'min') {
            $data['min_up'] = null;
        }

        return [$data, $devices];
    }

    private function formData(Service $service, array $selected): array
    {
        $exclude = $service->exists ? $service->descendantIds() : [];

        return [
            'service' => $service,
            'selected' => $selected,
            'devices' => Device::orderBy('hostname')->get(),
            'sites' => Site::orderBy('code')->get(['id', 'code', 'name']),
            'parents' => Service::whereNotIn('id', $exclude)->orderBy('name')->get(['id', 'name']),
        ];
    }

    private function month(Request $request): CarbonImmutable
    {
        $m = $request->query('month');

        return (is_string($m) && preg_match('/^\d{4}-\d{2}$/', $m))
            ? CarbonImmutable::createFromFormat('Y-m-d', $m . '-01')->startOfDay()
            : CarbonImmutable::now()->startOfMonth();
    }

    /**
     * Urutkan pohon layanan secara depth-first dengan kedalaman.
     *
     * @return Collection<int, array{service:Service, depth:int}>
     */
    private function flatten(Collection $all): Collection
    {
        $out = collect();
        $ids = $all->pluck('id')->flip();
        $walk = function ($parentId, int $depth) use (&$walk, $all, $out, $ids): void {
            foreach ($all->filter(fn ($s) => $parentId === null ? ($s->parent_id === null || ! isset($ids[$s->parent_id])) : $s->parent_id === $parentId) as $s) {
                $out->push(['service' => $s, 'depth' => $depth]);
                if ($depth < 10) {
                    $walk($s->id, $depth + 1);
                }
            }
        };
        $walk(null, 0);

        return $out;
    }
}
