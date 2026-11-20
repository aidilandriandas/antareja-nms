<?php

namespace Antareja\Http\Controllers;

use Antareja\Models\CapacityDaily;
use Antareja\Models\CapacityItem;
use Antareja\Services\CapacityService;
use App\Models\Port;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CapacityController extends Controller
{
    public function index(Request $request): View
    {
        $type = $request->query('type');
        $q = CapacityItem::with('device')->where('enabled', true);
        if ($type && isset(CapacityItem::TYPES[$type])) {
            $q->where('type', $type);
        }
        // Urutkan: yang paling cepat menyentuh ambang kritis, lalu ambang peringatan, lalu pemakaian tertinggi
        $items = $q->get()->sortBy([
            fn ($a, $b) => ($a->forecast_crit_on?->getTimestamp() ?? PHP_INT_MAX) <=> ($b->forecast_crit_on?->getTimestamp() ?? PHP_INT_MAX),
            fn ($a, $b) => ($a->forecast_warn_on?->getTimestamp() ?? PHP_INT_MAX) <=> ($b->forecast_warn_on?->getTimestamp() ?? PHP_INT_MAX),
            fn ($a, $b) => ($b->current_pct ?? -1) <=> ($a->current_pct ?? -1),
        ])->values();
        $all = CapacityItem::where('enabled', true)->get();
        $horizon = now()->addDays(90);

        return view('antareja::capacity.index', [
            'items' => $items,
            'type' => $type,
            'counts' => [
                'over_crit' => $all->filter(fn ($i) => $i->current_pct !== null && $i->current_pct >= $i->crit_pct)->count(),
                'over_warn' => $all->filter(fn ($i) => $i->current_pct !== null && $i->current_pct >= $i->warn_pct && $i->current_pct < $i->crit_pct)->count(),
                'soon' => $all->filter(fn ($i) => $i->forecast_crit_on && $i->forecast_crit_on->lte($horizon) && ($i->current_pct ?? 0) < $i->crit_pct)->count(),
                'total' => $all->count(),
            ],
            'ports' => Port::with('device')->where('deleted', 0)->whereNotIn('port_id', CapacityItem::where('type', 'port')->pluck('ref_id'))
                ->where('ifOperStatus', 'up')->orderBy('device_id')->limit(500)->get(),
        ]);
    }

    public function show(CapacityItem $item): View
    {
        return view('antareja::capacity.show', [
            'item' => $item->load('device'),
            'rows' => CapacityDaily::where('item_id', $item->id)->where('day', '>=', now()->subDays(120)->toDateString())->orderBy('day')->get(),
        ]);
    }

    public function update(Request $request, CapacityItem $item, CapacityService $capacity): RedirectResponse
    {
        $data = $request->validate([
            'warn_pct' => ['required', 'integer', 'between:1,100'],
            'crit_pct' => ['required', 'integer', 'between:1,100', 'gte:warn_pct'],
        ], ['crit_pct.gte' => 'Ambang kritis harus ≥ ambang peringatan.']);
        $item->update($data + ['enabled' => $request->boolean('enabled')]);
        $capacity->forecast($item);

        return back()->with('antareja_success', 'Ambang disimpan, prakiraan dihitung ulang.');
    }

    public function addPort(Request $request, CapacityService $capacity): RedirectResponse
    {
        $data = $request->validate(['port_id' => ['required', 'integer', Rule::exists('ports', 'port_id')]]);
        $item = $capacity->addPort($data['port_id']);
        $capacity->computeDaily($item);
        $capacity->forecast($item);

        return redirect()->route('antareja.capacity.show', $item)->with('antareja_success', "{$item->label} ditambahkan; histori diambil dari RRD poller.");
    }

    public function refresh(CapacityService $capacity): RedirectResponse
    {
        $new = $capacity->syncItems();
        $r = $capacity->computeAll();

        return back()->with('antareja_success', "Diperbarui: {$new} item baru, {$r['items']} item dihitung.");
    }
}
