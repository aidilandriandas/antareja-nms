<?php

namespace Antareja\Http\Controllers;

use Antareja\Models\Site;
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
}
