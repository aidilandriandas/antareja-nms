<?php

namespace Antareja\Http\Controllers;

use Antareja\Models\Site;
use Antareja\Services\NocSummary;
use Antareja\Services\TopologyService;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Layar NOC: tampilan layar penuh untuk monitor/TV ruang kontrol, diperbarui otomatis tanpa reload. */
class NocController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Device::class);

        return view('antareja::noc.index', [
            'sites' => Site::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function data(Request $request, NocSummary $summary, TopologyService $topology): JsonResponse
    {
        Gate::authorize('viewAny', Device::class);
        $siteId = $request->integer('site_id') ?: null;

        return response()->json($summary->build($request->user(), $siteId) + [
            'graph' => $topology->graph($request->user(), ['site_id' => $siteId]),
        ]);
    }
}
