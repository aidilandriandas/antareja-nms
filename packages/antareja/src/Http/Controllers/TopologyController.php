<?php

namespace Antareja\Http\Controllers;

use Antareja\Models\DeviceLink;
use Antareja\Models\Site;
use Antareja\Services\MappingService;
use Antareja\Support\Catalog;
use Illuminate\Validation\Rule;
use Antareja\Services\AuditLogger;
use Antareja\Services\TopologyService;
use App\Models\Device;
use App\Models\DeviceGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TopologyController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Device::class);

        return view('antareja::topology.index', [
            'sites' => Site::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'groups' => DeviceGroup::hasAccess($request->user())->orderBy('name')->get(['id', 'name']),
            'canEdit' => $request->user()->can('ant-mapping.update'),
            'canVerify' => $request->user()->can('ant-mapping.verify'),
            'linkTypes' => Catalog::LINK_TYPES,
        ]);
    }

    public function data(Request $request, TopologyService $topology): JsonResponse
    {
        Gate::authorize('viewAny', Device::class);
        $filter = $request->validate(['site_id' => ['nullable', 'integer'], 'group_id' => ['nullable', 'integer']]);
        if (! empty($filter['group_id'])) {
            abort_unless(DeviceGroup::hasAccess($request->user())->whereKey($filter['group_id'])->exists(), 403);
        }

        return response()->json(['graph' => $topology->graph($request->user(), $filter)]);
    }

    public function savePositions(Request $request, TopologyService $topology, AuditLogger $audit): JsonResponse
    {
        Gate::authorize('ant-mapping.update');
        $data = $request->validate([
            'positions' => ['required', 'array', 'max:5000'],
            'positions.*.x' => ['required', 'numeric', 'between:-1000000,1000000'],
            'positions.*.y' => ['required', 'numeric', 'between:-1000000,1000000'],
        ]);
        $n = $topology->savePositions($data['positions'], $request->user()->user_id);
        $audit->log('updated', 'topology', "Tata letak peta topologi disimpan ($n perangkat)");

        return response()->json(['saved' => $n]);
    }

    public function resetPositions(Request $request, TopologyService $topology, AuditLogger $audit): JsonResponse
    {
        Gate::authorize('ant-mapping.update');
        $topology->resetPositions();
        $audit->log('deleted', 'topology', 'Tata letak peta topologi dikembalikan ke otomatis');

        return response()->json(['ok' => true]);
    }

    /** Hubungkan dua perangkat langsung dari peta (= membuat Mapping Perangkat). A = sisi atas / upstream. */
    public function storeLink(Request $request, MappingService $mapping, AuditLogger $audit): JsonResponse
    {
        Gate::authorize('ant-mapping.update');
        $data = $request->validate([
            'a_device_id' => ['required', 'integer', 'exists:devices,device_id'],
            'a_port_id' => ['nullable', 'integer', Rule::exists('ports', 'port_id')->where('device_id', (int) $request->input('a_device_id'))],
            'b_device_id' => ['required', 'integer', 'different:a_device_id', 'exists:devices,device_id'],
            'b_port_id' => ['nullable', 'integer', Rule::exists('ports', 'port_id')->where('device_id', (int) $request->input('b_device_id'))],
            'link_type' => ['required', Rule::in(array_keys(Catalog::LINK_TYPES))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'b_device_id.different' => 'Pilih dua perangkat yang berbeda.',
            'a_port_id.exists' => 'Port harus milik perangkat atas.',
            'b_port_id.exists' => 'Port harus milik perangkat bawah.',
        ]);
        foreach (['a_device_id', 'b_device_id'] as $key) {
            Gate::authorize('view', Device::findOrFail($data[$key]));
        }
        $a = (int) $data['a_device_id'];
        $b = (int) $data['b_device_id'];
        $existing = DeviceLink::whereIn('status', [DeviceLink::VERIFIED, DeviceLink::SUGGESTED])
            ->where(fn ($q) => $q->where(fn ($x) => $x->where('a_device_id', $a)->where('b_device_id', $b))
                ->orWhere(fn ($x) => $x->where('a_device_id', $b)->where('b_device_id', $a)))->first();
        if ($existing) {
            return response()->json(['message' => 'Kedua perangkat ini sudah terhubung di Mapping Perangkat. Hapus dulu sambungan lama bila ingin mengganti.'], 422);
        }

        $link = new DeviceLink($data);
        $link->forceFill(['status' => DeviceLink::SUGGESTED, 'source' => 'manual', 'created_by' => $request->user()->user_id]);
        $link->save();
        $verified = $request->boolean('verify') && $request->user()->can('ant-mapping.verify');
        if ($verified) {
            $mapping->verify($link);
        }
        $audit->log('created', $link, 'Sambungan dibuat dari peta topologi: ' . $link->auditName());

        return response()->json(['id' => $link->id, 'verified' => $verified]);
    }

    public function destroyLink(DeviceLink $link, MappingService $mapping, AuditLogger $audit): JsonResponse
    {
        Gate::authorize('ant-mapping.update');
        Gate::authorize('view', Device::findOrFail($link->a_device_id));
        Gate::authorize('view', Device::findOrFail($link->b_device_id));
        $name = $link->auditName();
        $mapping->delete($link);
        $audit->log('deleted', 'device_link', "Sambungan dihapus dari peta topologi: $name");

        return response()->json(['ok' => true]);
    }
}
