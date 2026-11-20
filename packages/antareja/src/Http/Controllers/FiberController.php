<?php

namespace Antareja\Http\Controllers;

use Antareja\Models\Area;
use Antareja\Models\FiberCable;
use Antareja\Models\FiberCore;
use Antareja\Models\FiberJoint;
use Antareja\Models\FiberOdf;
use Antareja\Models\FiberOdfPort;
use Antareja\Models\FiberSplice;
use Antareja\Models\Site;
use Antareja\Services\AuditLogger;
use Antareja\Services\FiberService;
use App\Models\Device;
use App\Models\Port;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FiberController extends Controller
{
    public function __construct(private readonly FiberService $fiber, private readonly AuditLogger $audit)
    {
    }

    public function index(): View
    {
        $coreStats = FiberCore::selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n', 'status');
        $perCable = FiberCore::selectRaw("cable_id, SUM(status = 'used') AS used, SUM(status = 'broken') AS broken, COUNT(*) AS total")
            ->groupBy('cable_id')->get()->keyBy('cable_id');

        return view('antareja::fiber.index', [
            'cables' => FiberCable::orderBy('code')->get(),
            'perCable' => $perCable,
            'odfs' => FiberOdf::with(['site', 'area'])->withCount(['ports as used_ports' => fn ($q) => $q->whereNotNull('core_id')])->orderBy('name')->get(),
            'joints' => FiberJoint::with('site')->withCount('splices')->orderBy('code')->get(),
            'coreStats' => $coreStats,
        ]);
    }

    // ---------- Kabel ----------

    public function createCable(): View
    {
        return view('antareja::fiber.cable-form', $this->cableForm(new FiberCable(['mode' => 'sm', 'core_count' => 24, 'cores_per_tube' => 12, 'installation' => 'aerial', 'status' => 'active'])));
    }

    public function storeCable(Request $request): RedirectResponse
    {
        $cable = $this->fiber->createCable($this->cableData($request));

        return redirect()->route('antareja.fiber.cables.show', $cable)->with('antareja_success', "Kabel {$cable->code} dibuat dengan {$cable->core_count} core.");
    }

    public function showCable(FiberCable $cable): View
    {
        $cores = $cable->cores()->with('tests')->get();
        $ports = FiberOdfPort::with('odf', 'device', 'port')->whereIn('core_id', $cores->pluck('id'))->get()->groupBy('core_id');
        $splices = FiberSplice::with(['joint', 'coreA.cable', 'coreB.cable'])
            ->where(fn ($q) => $q->whereIn('core_a_id', $cores->pluck('id'))->orWhereIn('core_b_id', $cores->pluck('id')))->get();

        return view('antareja::fiber.cable', ['cable' => $cable, 'cores' => $cores, 'ports' => $ports, 'splices' => $splices]);
    }

    public function editCable(FiberCable $cable): View
    {
        return view('antareja::fiber.cable-form', $this->cableForm($cable));
    }

    public function updateCable(Request $request, FiberCable $cable): RedirectResponse
    {
        $data = $this->cableData($request, $cable);
        if ($data['core_count'] < $cable->core_count) {
            throw ValidationException::withMessages(['core_count' => 'Jumlah core tidak bisa dikurangi.']);
        }
        DB::transaction(function () use ($cable, $data): void {
            $old = $cable->core_count;
            $cable->update($data);
            for ($i = $old + 1; $i <= $cable->core_count; $i++) {
                FiberCore::create(['cable_id' => $cable->id, 'core_no' => $i, 'status' => 'free']);
            }
        });

        return redirect()->route('antareja.fiber.cables.show', $cable)->with('antareja_success', "Kabel {$cable->code} disimpan.");
    }

    public function updateCore(Request $request, FiberCore $core): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(FiberCore::STATUSES))],
            'service' => ['nullable', 'string', 'max:255'],
            'loss_db' => ['nullable', 'numeric', 'between:0,100'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);
        $old = $core->status;
        $core->update($data);
        if ($old !== $core->status) {
            $this->audit->log('updated', 'fiber_core', "Core {$core->label()}: {$old} → {$core->status}");
        }

        return back()->with('antareja_success', "Core {$core->core_no} disimpan.");
    }

    public function storeTest(Request $request, FiberCore $core): RedirectResponse
    {
        $data = $request->validate([
            'tested_at' => ['required', 'date'],
            'method' => ['required', Rule::in(['otdr', 'opm'])],
            'wavelength_nm' => ['required', 'integer', Rule::in([850, 1300, 1310, 1490, 1550, 1625])],
            'length_m' => ['nullable', 'numeric', 'between:0,200000'],
            'total_loss_db' => ['nullable', 'numeric', 'between:0,100'],
            'result' => ['required', Rule::in(['pass', 'fail'])],
            'events' => ['nullable', 'string', 'max:5000'],
        ]);
        $core->tests()->create($data + ['tested_by' => $request->user()->user_id]);
        if (isset($data['total_loss_db'])) {
            $core->update(['loss_db' => $data['total_loss_db']]);
        }

        return back()->with('antareja_success', "Hasil uji core {$core->core_no} dicatat.");
    }

    // ---------- ODF ----------

    public function storeOdf(Request $request): RedirectResponse
    {
        $odf = FiberOdf::create($this->odfData($request));
        $this->fiber->ensurePorts($odf);

        return redirect()->route('antareja.fiber.odfs.show', $odf)->with('antareja_success', "ODF {$odf->name} dibuat dengan {$odf->port_count} port.");
    }

    public function showOdf(FiberOdf $odf): View
    {
        $odf->load(['site', 'area', 'ports.core.cable', 'ports.device', 'ports.port']);

        return view('antareja::fiber.odf', [
            'odf' => $odf,
            'cores' => $this->fiber->coresAt('odf', $odf->id),
            'devices' => Device::orderBy('hostname')->get(),
            'sites' => Site::orderBy('code')->get(['id', 'code', 'name']),
            'areas' => Area::orderBy('name')->get(['id', 'site_id', 'name']),
        ]);
    }

    public function updateOdf(Request $request, FiberOdf $odf): RedirectResponse
    {
        $data = $this->odfData($request);
        if ($data['port_count'] < $odf->ports()->whereNotNull('core_id')->max('port_no')) {
            throw ValidationException::withMessages(['port_count' => 'Ada port terpakai di atas jumlah port baru.']);
        }
        $odf->update($data);
        $odf->ports()->where('port_no', '>', $odf->port_count)->delete();
        $this->fiber->ensurePorts($odf);

        return back()->with('antareja_success', "ODF {$odf->name} disimpan.");
    }

    public function updateOdfPort(Request $request, FiberOdfPort $port): RedirectResponse
    {
        $data = $request->validate([
            'core_id' => ['nullable', 'integer', 'exists:ant_fiber_cores,id'],
            'device_port_id' => ['nullable', 'integer', 'exists:ports,port_id'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);
        $core = $data['core_id'] ? FiberCore::with('cable')->find($data['core_id']) : null;
        if ($core && ! $core->cable->sideAt('odf', $port->odf_id)) {
            throw ValidationException::withMessages(['core_id' => 'Kabel core ini tidak berujung di ODF ini.']);
        }
        if ($core && FiberOdfPort::where('core_id', $core->id)->where('id', '!=', $port->id)->exists()) {
            throw ValidationException::withMessages(['core_id' => "Core {$core->label()} sudah terpasang di port lain."]);
        }
        $devPort = ! empty($data['device_port_id']) ? Port::find($data['device_port_id']) : null;
        $port->update([
            'core_id' => $core?->id, 'port_id' => $devPort?->port_id, 'device_id' => $devPort?->device_id, 'notes' => $data['notes'] ?? null,
        ]);
        if ($core && $core->status === 'free' && $devPort) {
            $core->update(['status' => 'used']);
        }
        $this->audit->log('updated', 'fiber_odf', "Port {$port->label()}: core " . ($core?->label() ?? '—') . ', patch ' . ($devPort ? $devPort->device?->displayName() . ' ' . $devPort->getShortLabel() : '—'));

        return back()->with('antareja_success', "Port {$port->port_no} disimpan.");
    }

    /** Port perangkat untuk dropdown patch (JSON ringan, dipanggil saat memilih perangkat). */
    public function devicePorts(Device $device)
    {
        return response()->json($device->ports()->where('deleted', 0)->orderBy('ifIndex')->get()
            ->map(fn ($p) => ['id' => $p->port_id, 'label' => $p->getShortLabel() . ($p->ifAlias && $p->ifAlias !== $p->ifDescr ? ' — ' . $p->ifAlias : '')]));
    }

    // ---------- Joint & splice ----------

    public function storeJoint(Request $request): RedirectResponse
    {
        $joint = FiberJoint::create($this->jointData($request));

        return redirect()->route('antareja.fiber.joints.show', $joint)->with('antareja_success', "Joint {$joint->code} dibuat.");
    }

    public function showJoint(FiberJoint $joint): View
    {
        return view('antareja::fiber.joint', [
            'joint' => $joint->load(['site', 'splices.coreA.cable', 'splices.coreB.cable']),
            'cores' => $this->fiber->coresAt('joint', $joint->id),
            'sites' => Site::orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function updateJoint(Request $request, FiberJoint $joint): RedirectResponse
    {
        $joint->update($this->jointData($request, $joint));

        return back()->with('antareja_success', "Joint {$joint->code} disimpan.");
    }

    public function storeSplice(Request $request, FiberJoint $joint): RedirectResponse
    {
        $data = $request->validate([
            'core_a_id' => ['required', 'integer', 'exists:ant_fiber_cores,id', 'different:core_b_id'],
            'core_b_id' => ['required', 'integer', 'exists:ant_fiber_cores,id'],
            'loss_db' => ['nullable', 'numeric', 'between:0,10'],
        ], ['core_a_id.different' => 'Pilih dua core yang berbeda.']);
        $a = FiberCore::with('cable')->find($data['core_a_id']);
        $b = FiberCore::with('cable')->find($data['core_b_id']);
        foreach ([$a, $b] as $c) {
            if (! $c->cable->sideAt('joint', $joint->id)) {
                throw ValidationException::withMessages(['core_a_id' => "Kabel {$c->cable->code} tidak masuk ke joint ini."]);
            }
            if (FiberSplice::where('core_a_id', $c->id)->orWhere('core_b_id', $c->id)->exists()) {
                throw ValidationException::withMessages(['core_a_id' => "Core {$c->label()} sudah disambung."]);
            }
        }
        if ($a->cable_id === $b->cable_id) {
            throw ValidationException::withMessages(['core_b_id' => 'Splice harus menyambung dua kabel berbeda.']);
        }
        $joint->splices()->create($data);
        $this->audit->log('created', 'fiber_joint', "Splice di {$joint->code}: {$a->label()} ↔ {$b->label()}");

        return back()->with('antareja_success', "Splice {$a->label()} ↔ {$b->label()} dicatat.");
    }

    public function destroySplice(FiberSplice $splice): RedirectResponse
    {
        $splice->delete();
        $this->audit->log('deleted', 'fiber_joint', "Splice #{$splice->id} di joint {$splice->joint?->code} dihapus");

        return back()->with('antareja_success', 'Splice dihapus.');
    }

    // ---------- Jalur & lokasi gangguan ----------

    public function trace(Request $request, FiberCore $core): View
    {
        $trace = $this->fiber->trace($core);
        $locate = null;
        if ($request->filled('distance')) {
            $request->validate(['distance' => ['numeric', 'between:0,200000'], 'slack' => ['nullable', 'numeric', 'between:0,20']]);
            $locate = $this->fiber->locate($trace, (float) $request->query('distance'), (float) $request->query('slack', 0));
        }

        return view('antareja::fiber.trace', ['core' => $core->load('cable'), 'trace' => $trace, 'budget' => $this->fiber->lossBudget($trace), 'locate' => $locate]);
    }

    /** Jalur fiber untuk port perangkat (dipakai tautan dari problem / inventaris). */
    public function traceForPort(int $portId): RedirectResponse
    {
        $odfPort = FiberOdfPort::where('port_id', $portId)->whereNotNull('core_id')->firstOrFail();

        return redirect()->route('antareja.fiber.trace', $odfPort->core_id);
    }

    // ---------- Helper ----------

    private function cableData(Request $request, ?FiberCable $cable = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:64', Rule::unique('ant_fiber_cables', 'code')->ignore($cable?->id)],
            'name' => ['required', 'string', 'max:255'],
            'mode' => ['required', Rule::in(['sm', 'mm'])],
            'fiber_type' => ['nullable', 'string', 'max:16'],
            'core_count' => ['required', 'integer', 'between:1,864'],
            'cores_per_tube' => ['required', 'integer', 'between:1,24'],
            'length_m' => ['nullable', 'integer', 'between:1,200000'],
            'installation' => ['required', Rule::in(array_keys(FiberCable::INSTALLATION))],
            'a_end' => ['required', 'regex:/^(odf|joint):\d+$/'],
            'b_end' => ['required', 'regex:/^(odf|joint):\d+$/', 'different:a_end'],
            'status' => ['required', Rule::in(array_keys(FiberCable::STATUSES))],
            'installed_on' => ['nullable', 'date'],
            'vendor' => ['nullable', 'string', 'max:128'],
            'route' => ['nullable', 'string', 'max:5000'],
        ], ['b_end.different' => 'Ujung A dan B harus berbeda.'], ['code' => 'kode', 'a_end' => 'ujung A', 'b_end' => 'ujung B', 'core_count' => 'jumlah core']);
        foreach (['a', 'b'] as $s) {
            [$type, $id] = explode(':', $data["{$s}_end"]);
            $exists = $type === 'odf' ? FiberOdf::whereKey($id)->exists() : FiberJoint::whereKey($id)->exists();
            if (! $exists) {
                throw ValidationException::withMessages(["{$s}_end" => 'Titik ujung tidak ditemukan.']);
            }
            $data["{$s}_type"] = $type;
            $data["{$s}_id"] = (int) $id;
            unset($data["{$s}_end"]);
        }

        return $data;
    }

    private function cableForm(FiberCable $cable): array
    {
        return [
            'cable' => $cable,
            'odfs' => FiberOdf::with('site')->orderBy('name')->get(),
            'joints' => FiberJoint::orderBy('code')->get(),
        ];
    }

    private function odfData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'site_id' => ['nullable', 'integer', 'exists:ant_sites,id'],
            'area_id' => ['nullable', 'integer', 'exists:ant_areas,id'],
            'rack' => ['nullable', 'string', 'max:64'],
            'port_count' => ['required', 'integer', 'between:1,288'],
            'connector' => ['required', Rule::in(['SC', 'LC', 'FC', 'ST'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [], ['name' => 'nama', 'port_count' => 'jumlah port']);
    }

    private function jointData(Request $request, ?FiberJoint $joint = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:64', Rule::unique('ant_fiber_joints', 'code')->ignore($joint?->id)],
            'type' => ['required', Rule::in(array_keys(FiberJoint::TYPES))],
            'site_id' => ['nullable', 'integer', 'exists:ant_sites,id'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [], ['code' => 'kode']);
    }
}
