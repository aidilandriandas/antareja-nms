<?php

namespace Antareja\Http\Controllers;

use Antareja\Models\Change;
use Antareja\Models\CctvStream;
use Antareja\Models\Incident;
use Antareja\Models\MaintenanceWindow;
use Antareja\Models\Problem;
use Antareja\Models\QualityTarget;
use Antareja\Models\WorkOrder;
use Antareja\Services\DataQuality;
use Antareja\Support\Severity;
use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Pusat Operasi: halaman pertama setelah login. Menjawab "apa yang sedang bermasalah dan apa yang harus dikerjakan",
 * bukan katalog modul. Setiap blok hanya tampil bila user punya hak atas datanya.
 */
class HomeController extends Controller
{
    public function __invoke(Request $request, DataQuality $quality): View
    {
        $user = $request->user();

        $stats = null;
        $sites = null;
        if (Gate::allows('viewAny', Device::class)) {
            $devices = Device::hasAccess($user);
            $active = fn () => (clone $devices)->where('disabled', 0)->where('ignore', 0);
            $stats = [
                'total' => (clone $devices)->count(),
                'up' => $active()->where('status', 1)->count(),
                'down' => $active()->where('status', 0)->count(),
                'disabled' => (clone $devices)->where(fn ($q) => $q->where('disabled', 1)->orWhere('ignore', 1))->count(),
                'last_polled' => (clone $devices)->max('last_polled'),
            ];
            $monitored = $stats['up'] + $stats['down'];
            $stats['availability'] = $monitored > 0 ? round($stats['up'] / $monitored * 100, 1) : null;

            if ($user->can('ant-site.view') || $user->can('ant-inventory.view')) {
                $access = Device::hasAccess($user)->select('devices.device_id');
                $sites = DB::table('ant_sites')
                    ->join('ant_device_details', 'ant_device_details.site_id', '=', 'ant_sites.id')
                    ->join('devices', 'devices.device_id', '=', 'ant_device_details.device_id')
                    ->whereIn('devices.device_id', $access)
                    ->groupBy('ant_sites.id', 'ant_sites.code', 'ant_sites.name', 'ant_sites.type')
                    ->selectRaw('ant_sites.id, ant_sites.code, ant_sites.name, ant_sites.type, COUNT(*) AS total,
                        SUM(devices.status = 1 AND devices.disabled = 0 AND devices.`ignore` = 0) AS up,
                        SUM(devices.status = 0 AND devices.disabled = 0 AND devices.`ignore` = 0) AS down')
                    ->orderByDesc('down')->orderByDesc('total')->limit(8)->get()
                    ->map(function ($s) {
                        $s->up = (int) $s->up;
                        $s->down = (int) $s->down;
                        $s->off = max(0, (int) $s->total - $s->up - $s->down);

                        return $s;
                    });
                $sites->unassigned = DB::table('devices')->whereIn('devices.device_id', Device::hasAccess($user)->select('devices.device_id'))
                    ->leftJoin('ant_device_details', 'ant_device_details.device_id', '=', 'devices.device_id')
                    ->whereNull('ant_device_details.site_id')->count();
            }
        }

        $ops = null;
        $problems = null;
        $severity = null;
        if ($user->can('ant-problem.view')) {
            $open = Problem::open()->whereNull('suppressed_reason');
            $bySeverity = (clone $open)->selectRaw('severity, COUNT(*) AS n')->groupBy('severity')->pluck('n', 'severity');
            $severity = collect(['disaster', 'high', 'average', 'warning', 'information'])
                ->map(fn ($s) => ['key' => $s, 'n' => (int) ($bySeverity[$s] ?? 0)] + Severity::problem($s));
            $ops = [
                'problems' => (int) $bySeverity->sum(),
                'unacked' => (clone $open)->whereNull('acknowledged_at')->count(),
                'suppressed' => Problem::open()->whereNotNull('suppressed_reason')->count(),
                'incidents' => $user->can('ant-incident.view') ? Incident::active()->count() : null,
                'maintenance' => $user->can('ant-maintenance.view')
                    ? MaintenanceWindow::whereIn('status', ['planned', 'in_progress'])->where('starts_at', '<=', now())->where('ends_at', '>=', now())->count()
                    : null,
            ];
            $problems = (clone $open)->with(['device', 'site'])
                ->orderByRaw("FIELD(severity, 'disaster', 'high', 'average', 'warning', 'information')")
                ->orderByDesc('started_at')->limit(8)->get();
        }

        $work = null;
        $woOn = $user->can('ant-workorder.view') && \Antareja\Support\ModuleRegistry::enabled('workorders');
        $crOn = $user->can('ant-change.view') && \Antareja\Support\ModuleRegistry::enabled('changes');
        if ($woOn || $crOn) {
            $work = ['wo_active' => null, 'wo_overdue' => null, 'cr_pending' => null, 'cr_upcoming' => collect()];
            if ($woOn) {
                $work['wo_active'] = WorkOrder::active()->count();
                $work['wo_overdue'] = WorkOrder::active()->whereNotNull('due_at')->where('due_at', '<', now())->count();
            }
            if ($crOn) {
                $work['cr_pending'] = Change::where('status', 'submitted')->count();
                $work['cr_upcoming'] = Change::whereIn('status', ['approved', 'in_progress'])
                    ->where('planned_end', '>=', now())->where('planned_start', '<=', now()->addDays(7))
                    ->orderBy('planned_start')->limit(4)->get();
            }
        }

        $cctv = null;
        if ($user->can('ant-cctv.view')) {
            $streams = CctvStream::where('enabled', true)->where('health_check', true);
            $cctv = [
                'total' => (clone $streams)->count(),
                'ok' => (clone $streams)->where('health', 'ok')->count(),
                'issues' => (clone $streams)->whereNotNull('health')->where('health', '!=', 'ok')->with('device')
                    ->orderByDesc('health_changed_at')->limit(5)->get(),
            ];
        }

        $qualityProblems = $user->can('ant-quality.view')
            ? QualityTarget::with('site')->where('enabled', true)->whereIn('last_status', ['warning', 'critical', 'down'])
                ->where('last_checked_at', '>=', now()->subMinutes(QualityTarget::STALE_MINUTES))
                ->orderByRaw("FIELD(last_status, 'down', 'critical', 'warning')")->limit(5)->get()
            : null;

        $issues = $user->can('ant-inventory.view') && \Antareja\Support\ModuleRegistry::enabled('device-inventory') ? $quality->counts($user) : null;
        if ($issues !== null && $user->can('ant-ipam.view')) {
            $issues['ip_conflicts'] = \Antareja\Models\IpAddress::where('conflict', true)->count();
        }

        return view('antareja::pages.home', [
            'stats' => $stats,
            'sites' => $sites,
            'ops' => $ops,
            'severity' => $severity,
            'problems' => $problems,
            'work' => $work,
            'cctv' => $cctv,
            'qualityProblems' => $qualityProblems,
            'issues' => $issues,
            'greeting' => $this->greeting(),
        ]);
    }

    private function greeting(): string
    {
        $h = (int) now()->format('G');

        return match (true) {
            $h < 11 => 'Selamat pagi',
            $h < 15 => 'Selamat siang',
            $h < 18 => 'Selamat sore',
            default => 'Selamat malam',
        };
    }
}
