<?php

namespace Antareja\Http\Controllers;

use Antareja\Models\Incident;
use Antareja\Models\Problem;
use Antareja\Models\Site;
use Antareja\Services\IncidentService;
use Antareja\Services\ProblemEngine;
use Antareja\Support\Severity;
use App\Models\Device;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class ProblemController extends Controller
{
    public function __construct(private readonly ProblemEngine $engine, private readonly IncidentService $incidents)
    {
    }

    private function scoped(Request $request): Builder
    {
        $allowed = Device::hasAccess($request->user())->select('device_id');

        return Problem::query()->where(fn ($q) => $q->whereNull('device_id')->orWhereIn('device_id', $allowed));
    }

    public function index(Request $request): View
    {
        $status = $request->input('status', 'open');
        $showSuppressed = $request->boolean('suppressed');

        $problems = $this->scoped($request)->with(['device', 'site', 'incident', 'acknowledger'])->withCount(['children' => fn ($q) => $q->open()])
            ->withCount(['sources as active_sources_count' => fn ($q) => $q->where('active', true)])
            ->when($status === 'open', fn ($q) => $q->open())
            ->when($status === 'resolved', fn ($q) => $q->where('status', Problem::RESOLVED))
            ->when($status === 'open' && ! $showSuppressed, fn ($q) => $q->whereNull('suppressed_reason'))
            ->when($request->filled('severity'), fn ($q) => $q->whereIn('severity', array_keys(array_filter(Severity::PROBLEM,
                fn ($m) => $m['rank'] >= Severity::problemRank($request->input('severity'))))))
            ->when($request->filled('site'), fn ($q) => $q->where('site_id', (int) $request->input('site')))
            ->when($request->filled('class'), fn ($q) => $q->where('class', $request->input('class')))
            ->when($request->input('ack') === 'no', fn ($q) => $q->whereNull('acknowledged_at'))
            ->when($request->filled('q'), fn ($q) => $q->where('title', 'like', '%' . $request->input('q') . '%'))
            ->orderByRaw("FIELD(severity, 'disaster', 'high', 'average', 'warning', 'information')")
            ->latest('started_at')->paginate(100)->withQueryString();

        $open = $this->scoped($request)->open();

        return view('antareja::problems.index', [
            'problems' => $problems,
            'status' => $status,
            'showSuppressed' => $showSuppressed,
            'counts' => (clone $open)->whereNull('suppressed_reason')->selectRaw('severity, COUNT(*) AS c')->groupBy('severity')->pluck('c', 'severity'),
            'suppressedCount' => (clone $open)->whereNotNull('suppressed_reason')->count(),
            'unacked' => (clone $open)->whereNull('suppressed_reason')->whereNull('acknowledged_at')->count(),
            'sites' => Site::orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function show(Request $request, Problem $problem): View
    {
        abort_unless($this->scoped($request)->whereKey($problem->id)->exists(), 403);

        return view('antareja::problems.show', [
            'problem' => $problem->load(['device', 'port', 'site', 'sources', 'events.user', 'parent', 'children.device', 'incident', 'acknowledger']),
            'openIncidents' => Incident::active()->latest('started_at')->limit(50)->get(['id', 'number', 'title']),
        ]);
    }

    public function acknowledge(Request $request, Problem $problem): RedirectResponse
    {
        abort_unless($this->scoped($request)->whereKey($problem->id)->exists(), 403);
        $data = $request->validate(['message' => ['nullable', 'string', 'max:255']]);
        $this->engine->acknowledge($problem, $request->user()->user_id, (string) ($data['message'] ?? ''));

        return back()->with('antareja_success', "Problem \"{$problem->title}\" di-acknowledge.");
    }

    public function bulkAcknowledge(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'problems' => ['required', 'array', 'max:500'], 'problems.*' => ['integer'],
            'message' => ['nullable', 'string', 'max:255'],
        ], ['problems.required' => 'Pilih minimal satu problem.']);
        $list = $this->scoped($request)->open()->whereIn('id', $data['problems'])->whereNull('acknowledged_at')->get();
        foreach ($list as $p) {
            $this->engine->acknowledge($p, $request->user()->user_id, (string) ($data['message'] ?? ''));
        }

        return back()->with('antareja_success', "{$list->count()} problem di-acknowledge.");
    }

    public function createIncident(Request $request, Problem $problem): RedirectResponse
    {
        abort_unless($this->scoped($request)->whereKey($problem->id)->exists(), 403);
        $incident = $this->incidents->fromProblem($problem, $request->user()->user_id);

        return redirect()->route('antareja.incidents.show', $incident)->with('antareja_success', "Incident {$incident->number} dibuat dari problem.");
    }

    public function linkIncident(Request $request, Problem $problem): RedirectResponse
    {
        abort_unless($this->scoped($request)->whereKey($problem->id)->exists(), 403);
        $data = $request->validate(['incident_id' => ['required', 'integer', 'exists:ant_incidents,id']], [], ['incident_id' => 'incident']);
        $incident = Incident::findOrFail($data['incident_id']);
        $this->incidents->link($incident, $problem, $request->user()->user_id);

        return back()->with('antareja_success', "Problem ditautkan ke {$incident->number}.");
    }

    public function sync(): RedirectResponse
    {
        $r = $this->engine->sync();

        return back()->with('antareja_success', "Sinkronisasi problem: {$r['opened']} baru, {$r['resolved']} selesai dari {$r['observations']} observasi.");
    }
}
