<?php

namespace Antareja\Http\Controllers;

use Antareja\Models\DeviceDetail;
use Antareja\Models\QualitySample;
use Antareja\Models\QualityTarget;
use Antareja\Models\Site;
use Antareja\Services\QualityProbe;
use Antareja\Support\Catalog;
use App\Models\Device;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class QualityController extends Controller
{
    /** Kategori yang ditawarkan saat menambah target massal dari inventaris. */
    private const BULK_CATEGORIES = ['router', 'core-switch', 'distribution-switch', 'firewall', 'ptp', 'ptmp', 'wireless-controller', 'nvr', 'server'];

    public function __construct(private readonly QualityProbe $probe)
    {
    }

    public function index(Request $request): View
    {
        $targets = QualityTarget::with(['site', 'device', 'latestSample'])
            ->when($request->filled('site'), fn ($q) => $q->where('site_id', (int) $request->input('site')))
            ->when($request->filled('group'), fn ($q) => $q->where('group_name', $request->input('group')))
            ->when($request->filled('status'), fn ($q) => $q->where('last_status', $request->input('status')))
            ->orderBy('group_name')->orderBy('name')->get();

        // Sparkline 24 jam: satu query teragregasi per 30 menit untuk semua target
        $spark = QualitySample::whereIn('target_id', $targets->pluck('id'))->where('measured_at', '>=', now()->subDay())
            ->selectRaw('target_id, FLOOR(UNIX_TIMESTAMP(measured_at) / 1800) * 1800 AS t, AVG(median_ms) AS median, AVG(loss_pct) AS loss')
            ->groupBy('target_id', 't')->orderBy('t')->get()
            ->groupBy('target_id')
            ->map(fn ($rows) => $rows->map(fn ($r) => ['t' => (int) $r->t, 'median' => $r->median === null ? null : (float) $r->median, 'loss' => (float) $r->loss])->all());

        $day = QualitySample::whereIn('target_id', $targets->pluck('id'))->where('measured_at', '>=', now()->subDay())
            ->selectRaw('target_id, AVG(loss_pct) AS loss, AVG(median_ms) AS median, AVG(jitter_ms) AS jitter, SUM(received > 0) / COUNT(*) * 100 AS availability')
            ->groupBy('target_id')->get()->keyBy('target_id');

        return view('antareja::quality.index', [
            'targets' => $targets,
            'spark' => $spark,
            'day' => $day,
            'counts' => QualityTarget::where('enabled', true)->selectRaw("COALESCE(last_status, 'unknown') AS s, COUNT(*) AS c")->groupBy('s')->pluck('c', 's'),
            'sites' => Site::orderBy('code')->get(['id', 'code', 'name']),
            'groups' => QualityTarget::whereNotNull('group_name')->distinct()->orderBy('group_name')->pluck('group_name'),
        ]);
    }

    public function show(Request $request, QualityTarget $target): View
    {
        $range = in_array($request->input('range'), ['3h', '30h', '10d', '30d'], true) ? $request->input('range') : null;
        $ranges = $range ? [$range] : ['3h', '30h', '10d', '30d'];

        return view('antareja::quality.show', [
            'target' => $target->load(['site', 'device', 'latestSample']),
            'charts' => collect($ranges)->mapWithKeys(fn ($r) => [$r => $this->probe->series($target, $r)]),
            'summaries' => [
                '24 jam' => $this->probe->summary($target, now()->subDay()),
                '7 hari' => $this->probe->summary($target, now()->subDays(7)),
                '30 hari' => $this->probe->summary($target, now()->subDays(30)),
            ],
            'recent' => QualitySample::where('target_id', $target->id)->latest('measured_at')->limit(12)->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $device = $request->filled('device') ? Device::find($request->input('device')) : null;

        return view('antareja::quality.form', $this->formData(new QualityTarget([
            'name' => $device?->displayName(), 'host' => $device ? ($device->overwrite_ip ?: $device->ip ?: $device->hostname) : null,
            'device_id' => $device?->device_id, 'site_id' => $device ? DeviceDetail::find($device->device_id)?->site_id : null,
            'pings' => 20, 'latency_warn_ms' => 100, 'latency_crit_ms' => 250, 'loss_warn_pct' => 5, 'loss_crit_pct' => 20, 'jitter_warn_ms' => 30, 'enabled' => true,
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $target = QualityTarget::create($this->validated($request));

        return redirect()->route('antareja.quality.show', $target)
            ->with('antareja_success', "Target {$target->name} ditambahkan. Pengukuran pertama berjalan dalam ≤ 5 menit.");
    }

    public function edit(QualityTarget $target): View
    {
        return view('antareja::quality.form', $this->formData($target));
    }

    public function update(Request $request, QualityTarget $target): RedirectResponse
    {
        $target->update($this->validated($request, $target));

        return redirect()->route('antareja.quality.show', $target)->with('antareja_success', "Target {$target->name} disimpan.");
    }

    public function destroy(QualityTarget $target): RedirectResponse
    {
        $target->delete();

        return redirect()->route('antareja.quality.index')->with('antareja_success', "Target {$target->name} dan seluruh datanya dihapus.");
    }

    public function measure(QualityTarget $target): RedirectResponse
    {
        $this->probe->run(collect([$target]));
        $target->refresh();

        return back()->with('antareja_success', "Pengukuran {$target->name} selesai: " . $target->statusMeta()['label'] . '.');
    }

    public function bulkForm(): View
    {
        $existing = QualityTarget::whereNotNull('device_id')->pluck('device_id')->flip();
        $candidates = DeviceDetail::with(['device', 'site'])->active()
            ->whereIn('category', self::BULK_CATEGORIES)->get()
            ->reject(fn ($d) => isset($existing[$d->device_id]) || ! $d->device)
            ->sortBy(fn ($d) => [$d->site?->code, $d->category, $d->device->displayName()]);

        return view('antareja::quality.bulk', ['candidates' => $candidates]);
    }

    public function bulkStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'devices' => ['required', 'array', 'max:500'],
            'devices.*' => ['integer', 'exists:devices,device_id'],
            'group_name' => ['nullable', 'string', 'max:64'],
        ], ['devices.required' => 'Pilih minimal satu perangkat.']);

        $created = 0;
        foreach (Device::whereIn('device_id', $data['devices'])->get() as $device) {
            if (QualityTarget::where('device_id', $device->device_id)->exists()) {
                continue;
            }
            $detail = DeviceDetail::find($device->device_id);
            QualityTarget::create([
                'name' => $device->displayName(),
                'host' => $device->overwrite_ip ?: $device->ip ?: $device->hostname,
                'device_id' => $device->device_id,
                'site_id' => $detail?->site_id,
                'group_name' => $data['group_name'] ?: Catalog::label(Catalog::DEVICE_CATEGORIES, $detail?->category, 'Lainnya'),
                'pings' => 20, 'enabled' => true,
            ]);
            $created++;
        }

        return redirect()->route('antareja.quality.index')->with('antareja_success', "{$created} target ditambahkan.");
    }

    private function validated(Request $request, ?QualityTarget $target = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'host' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9.:\-_]+$/'],
            'device_id' => ['nullable', 'integer', 'exists:devices,device_id'],
            'site_id' => ['nullable', 'integer', 'exists:ant_sites,id'],
            'group_name' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:2000'],
            'pings' => ['required', 'integer', 'between:5,60'],
            'latency_warn_ms' => ['required', 'integer', 'between:1,10000'],
            'latency_crit_ms' => ['required', 'integer', 'between:1,10000', 'gte:latency_warn_ms'],
            'loss_warn_pct' => ['required', 'numeric', 'between:0,100'],
            'loss_crit_pct' => ['required', 'numeric', 'between:0,100', 'gte:loss_warn_pct'],
            'jitter_warn_ms' => ['required', 'integer', 'between:1,10000'],
        ], [
            'host.regex' => 'Host hanya boleh berisi IP atau hostname (huruf, angka, titik, titik dua, minus).',
            'latency_crit_ms.gte' => 'Batas kritis latency harus ≥ batas peringatan.',
            'loss_crit_pct.gte' => 'Batas kritis loss harus ≥ batas peringatan.',
        ], ['name' => 'nama', 'host' => 'host / IP', 'pings' => 'jumlah ping']);
        $data['enabled'] = $request->boolean('enabled');

        return $data;
    }

    private function formData(QualityTarget $target): array
    {
        return [
            'target' => $target,
            'sites' => Site::orderBy('code')->get(['id', 'code', 'name']),
            'devices' => Device::orderBy('hostname')->get(),
            'groups' => QualityTarget::whereNotNull('group_name')->distinct()->orderBy('group_name')->pluck('group_name'),
        ];
    }
}
