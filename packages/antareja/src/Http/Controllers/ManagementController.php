<?php

namespace Antareja\Http\Controllers;

use Antareja\Models\Incident;
use Antareja\Models\Problem;
use Antareja\Services\ReportBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Dashboard manajemen: ringkasan bulan berjalan + tren 6 bulan, tanpa detail teknis.
 */
class ManagementController extends Controller
{
    public function __invoke(Request $request): View
    {
        $month = CarbonImmutable::now()->startOfMonth();
        $r = new ReportBuilder($month);

        // Tren bulan-bulan yang sudah lewat jarang berubah → cache 1 jam
        $trend = Cache::remember('antareja:mgmt-trend:' . $month->format('Y-m'), 3600, function () use ($month): array {
            $out = [];
            for ($i = 5; $i >= 1; $i--) {
                $m = $month->subMonths($i);
                $b = new ReportBuilder($m);
                $s = $b->summary();
                $out[] = ['month' => $m->format('Y-m'), 'availability' => $s['device_availability'], 'incidents' => $s['incidents'], 'mttr' => $s['mttr']];
            }

            return $out;
        });
        $summary = $r->summary();
        $trend[] = ['month' => $month->format('Y-m'), 'availability' => $summary['device_availability'], 'incidents' => $summary['incidents'], 'mttr' => $summary['mttr']];

        return view('antareja::pages.management', [
            'month' => $month,
            'summary' => $summary,
            'sites' => $r->sites(),
            'services' => $r->services(),
            'trend' => $trend,
            'worstDevices' => array_slice(array_values(array_filter($r->devices(), fn ($d) => $d['outages'] > 0)), 0, 8),
            'activeIncidents' => Incident::active()->with(['site', 'device'])->orderByRaw("FIELD(severity, 'critical', 'high', 'medium', 'low')")->orderBy('started_at')->limit(8)->get(),
            'openProblems' => Problem::open()->whereNull('suppressed_reason')->count(),
            'quality' => collect($r->quality())->whereNotNull('avg_loss')->sortByDesc('avg_loss')->take(5)->values(),
        ]);
    }
}
