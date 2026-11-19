<?php

namespace Antareja\Http\Controllers;

use Antareja\Models\Incident;
use Antareja\Models\Site;
use Antareja\Services\AttachmentService;
use Antareja\Services\IncidentService;
use Antareja\Support\Severity;
use App\Models\Device;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IncidentController extends Controller
{
    public function __construct(private readonly IncidentService $incidents)
    {
    }

    public function index(Request $request): View
    {
        $status = $request->input('status', 'active');
        $list = Incident::with(['device', 'site', 'assignee'])->withCount('problems')
            ->when($status === 'active', fn ($q) => $q->active())
            ->when(in_array($status, array_keys(Incident::STATUSES), true), fn ($q) => $q->where('status', $status))
            ->when($request->filled('severity'), fn ($q) => $q->where('severity', $request->input('severity')))
            ->when($request->filled('site'), fn ($q) => $q->where('site_id', (int) $request->input('site')))
            ->when($request->input('mine'), fn ($q) => $q->where('assigned_to', $request->user()->user_id))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($q) => $q->where('title', 'like', '%' . $request->input('q') . '%')
                ->orWhere('number', 'like', '%' . $request->input('q') . '%')))
            ->orderByRaw("FIELD(status, 'open', 'acknowledged', 'in_progress', 'pending', 'resolved', 'closed')")
            ->orderByRaw("FIELD(severity, 'critical', 'high', 'medium', 'low')")
            ->latest('started_at')->paginate(50)->withQueryString();

        return view('antareja::incidents.index', [
            'incidents' => $list,
            'status' => $status,
            'stats' => $this->incidents->stats(now()->subDays(30)),
            'sites' => Site::orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function create(Request $request): View
    {
        return view('antareja::incidents.create', [
            'devices' => Device::hasAccess($request->user())->orderBy('hostname')->get(),
            'sites' => Site::orderBy('code')->get(['id', 'code', 'name']),
            'users' => User::orderBy('username')->get(['user_id', 'username', 'realname']),
            'preselect' => (int) $request->input('device'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, true);
        $incident = $this->incidents->create($data, $request->user()->user_id);
        if (! empty($data['assigned_to']) || ! empty($data['pic_name'])) {
            $this->incidents->assign($incident, $data['assigned_to'] ?? null, $data['pic_name'] ?? null, $request->user()->user_id);
        }

        return redirect()->route('antareja.incidents.show', $incident)->with('antareja_success', "Incident {$incident->number} dibuat.");
    }

    public function show(Incident $incident, AttachmentService $attachments): View
    {
        return view('antareja::incidents.show', [
            'incident' => $incident->load(['device', 'site', 'assignee', 'creator', 'notes.user', 'problems.device']),
            'attachments' => $attachments->for('incident', $incident->id),
            'users' => User::orderBy('username')->get(['user_id', 'username', 'realname']),
            'sites' => Site::orderBy('code')->get(['id', 'code', 'name']),
            'policy' => $incident->escalation_policy_id ? \Antareja\Models\EscalationPolicy::find($incident->escalation_policy_id) : null,
        ]);
    }

    public function update(Request $request, Incident $incident): RedirectResponse
    {
        $incident->update($this->validated($request, false));

        return back()->with('antareja_success', "Incident {$incident->number} disimpan.");
    }

    public function transition(Request $request, Incident $incident): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Incident::STATUSES))],
            'comment' => ['nullable', 'string', 'max:2000'],
            'root_cause' => ['nullable', 'string', 'max:5000'],
            'corrective_action' => ['nullable', 'string', 'max:5000'],
        ]);
        if ($data['status'] === 'closed') {
            abort_unless($request->user()->can('ant-incident.close'), 403);
        }
        $this->incidents->transition($incident, $data['status'], $request->user()->user_id, $data['comment'] ?? null,
            array_intersect_key($data, array_flip(['root_cause', 'corrective_action'])));

        return back()->with('antareja_success', "Status {$incident->number}: " . Incident::STATUSES[$data['status']]['label'] . '.');
    }

    public function note(Request $request, Incident $incident): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']], [], ['body' => 'catatan']);
        $incident->note('note', $data['body'], null, null, $request->user()->user_id);

        return back()->with('antareja_success', 'Work note ditambahkan.');
    }

    public function assign(Request $request, Incident $incident): RedirectResponse
    {
        $data = $request->validate([
            'assigned_to' => ['nullable', 'integer', 'exists:users,user_id'],
            'pic_name' => ['nullable', 'string', 'max:128'],
        ]);
        $this->incidents->assign($incident, $data['assigned_to'] ?? null, $data['pic_name'] ?? null, $request->user()->user_id);

        return back()->with('antareja_success', 'PIC incident diperbarui.');
    }

    public function attach(Request $request, Incident $incident, AttachmentService $attachments): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:' . AttachmentService::ALLOWED_MIMES, 'max:' . AttachmentService::MAX_KB],
            'description' => ['nullable', 'string', 'max:255'],
        ], [
            'file.required' => 'Pilih file evidence yang akan diunggah.',
            'file.mimes' => 'Evidence harus berupa JPG, PNG, WEBP, atau PDF.',
            'file.max' => 'Ukuran evidence maksimal 10 MB.',
        ]);
        $attachments->store('incident', $incident->id, $request->file('file'), $request->input('description'));
        $incident->note('system', 'Evidence diunggah: ' . $request->file('file')->getClientOriginalName(), null, null, $request->user()->user_id);

        return back()->with('antareja_success', 'Evidence diunggah.');
    }

    private function validated(Request $request, bool $creating): array
    {
        return $request->validate([
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'severity' => [$creating ? 'required' : 'sometimes', Rule::in(array_keys(Severity::INCIDENT))],
            'device_id' => ['nullable', 'integer', 'exists:devices,device_id'],
            'site_id' => ['nullable', 'integer', 'exists:ant_sites,id'],
            'started_at' => ['nullable', 'date', 'before_or_equal:now'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,user_id'],
            'pic_name' => ['nullable', 'string', 'max:128'],
            'root_cause' => ['nullable', 'string', 'max:5000'],
            'corrective_action' => ['nullable', 'string', 'max:5000'],
        ], ['started_at.before_or_equal' => 'Waktu mulai tidak boleh di masa depan.'], ['title' => 'judul', 'severity' => 'severity']);
    }
}
