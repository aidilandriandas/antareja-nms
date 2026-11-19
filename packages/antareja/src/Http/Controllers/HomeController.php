<?php

namespace Antareja\Http\Controllers;

use Antareja\AntarejaServiceProvider;
use Antareja\Services\DataQuality;
use Antareja\Support\ModuleRegistry;
use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(Request $request, ModuleRegistry $modules, DataQuality $quality): View
    {
        $stats = null;
        if (Gate::allows('viewAny', Device::class)) {
            $devices = Device::hasAccess($request->user());
            $stats = [
                'total' => (clone $devices)->count(),
                'up' => (clone $devices)->where('status', 1)->where('disabled', 0)->where('ignore', 0)->count(),
                'down' => (clone $devices)->where('status', 0)->where('disabled', 0)->where('ignore', 0)->count(),
                'disabled' => (clone $devices)->where(fn ($q) => $q->where('disabled', 1)->orWhere('ignore', 1))->count(),
            ];
        }

        $all = $modules->all();
        $issues = $request->user()->can('ant-inventory.view') ? $quality->counts($request->user()) : null;

        return view('antareja::pages.home', [
            'modules' => $all,
            'counts' => $all->countBy('status'),
            'stats' => $stats,
            'issues' => $issues,
            'version' => AntarejaServiceProvider::VERSION,
        ]);
    }
}
