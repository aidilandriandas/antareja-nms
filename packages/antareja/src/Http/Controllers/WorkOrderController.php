<?php

namespace Antareja\Http\Controllers;

use Antareja\Models\Area;
use Antareja\Models\Change;
use Antareja\Models\Incident;
use Antareja\Models\MaintenanceWindow;
use Antareja\Models\Site;
use Antareja\Models\WorkOrder;
use Antareja\Services\AttachmentService;
use Antareja\Services\WorkOrderService;
use App\Models\Device;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WorkOrderController extends Controller
{
    public function __construct(private readonly WorkOrderService $service)
    {
    }

    public function index(Request $request): View
    {
        $q = WorkOrder::with(['site', 'assignee', 'device'])
            ->orderByRaw("FIELD(status, 'open', 'assigned', 'in_progress', 'on_hold', 'done', 'verified', 'cancelled')")
            ->orderByRaw("FIELD(priority, 'urgent', 'high', 'normal', 'low')")->orderBy('due_at');
        $view = $request->query('view', 'active');
        match ($view) {
            'mine' => $q->active()->where('assigned_to', $request->user()->user_id),
            'overdue' => $q->active()->where('due_at', '<', now()),
            'done' => $q->whereIn('status', ['done', 'verified']),
            'all' => null,
            default => $q->active(),
        };
        foreach (['type' => 'type', 'site' => 'site_id', 'priority' => 'priority'] as $param => $col) {
            if ($request->filled($param)) {
                $q->where($col, $request->query($param));
            }
        }

        return view('antareja::workorders.index', [
            'orders' => $q->paginate(50)->withQueryString(),
            'view' => $view,
            'sites' => Site::orderBy('code')->get(['id', 'code', 'name']),
            'counts' => [
                'active' => WorkOrder::active()->count(),
                'mine' => WorkOrder::active()->where('assigned_to', $request->user()->user_id)->count(),
                'overdue' => WorkOrder::active()->where('due_at', '<', now())->count(),
                'verify' => WorkOrder::where('status', 'done')->count(),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $wo = new WorkOrder(['type' => 'corrective', 'priority' => 'normal']);
        if ($request->filled('incident') && ($i = Incident::find($request->integer('incident')))) {
            $wo->fill(['incident_id' => $i->id, 'title' => "Perbaikan: {$i->title}", 'device_id' => $i->device_id, 'site_id' => $i->site_id,
                'priority' => ['critical' => 'urgent', 'high' => 'high', 'medium' => 'normal', 'low' => 'low'][$i->severity] ?? 'normal',
                'description' => "Terkait incident {$i->number}.\n{$i->description}"]);
        }
        if ($request->filled('maintenance') && ($m = MaintenanceWindow::find($request->integer('maintenance')))) {
            $wo->fill(['maintenance_window_id' => $m->id, 'title' => $m->title, 'site_id' => $m->site_id,
                'type' => $m->type === 'preventive' ? 'preventive' : 'corrective', 'due_at' => $m->ends_at, 'description' => $m->description]);
        }
        if ($request->filled('change') && ($c = Change::find($request->integer('change')))) {
            $wo->fill(['change_id' => $c->id, 'title' => "{$c->number}: {$c->title}", 'site_id' => $c->site_id, 'type' => 'installation',
                'due_at' => $c->planned_end, 'description' => $c->description]);
        }
        if ($request->filled('device') && ($d = Device::find($request->integer('device')))) {
            $wo->device_id = $d->device_id;
            $wo->site_id ??= \Antareja\Models\DeviceDetail::find($d->device_id)?->site_id;
        }

        return view('antareja::workorders.form', $this->formData($wo));
    }

    public function store(Request $request): RedirectResponse
    {
        $wo = $this->service->create($this->validated($request), $request->user()->user_id);

        return redirect()->route('antareja.workorders.show', $wo)->with('antareja_success', "Work order {$wo->number} dibuat.");
    }

    public function show(WorkOrder $workorder, AttachmentService $attachments): View
    {
        return view('antareja::workorders.show', [
            'wo' => $workorder->load(['site', 'area', 'device', 'incident', 'maintenance', 'change', 'assignee', 'verifier', 'materials', 'logs.user']),
            'attachments' => $attachments->for('work_order', $workorder->id),
            'users' => User::orderBy('username')->get(['user_id', 'username', 'realname']),
        ]);
    }

    public function edit(WorkOrder $workorder): View
    {
        return view('antareja::workorders.form', $this->formData($workorder));
    }

    public function update(Request $request, WorkOrder $workorder): RedirectResponse
    {
        $data = $this->validated($request);
        unset($data['checklist']);
        $workorder->update($data);

        return redirect()->route('antareja.workorders.show', $workorder)->with('antareja_success', "Work order {$workorder->number} disimpan.");
    }

    public function transition(Request $request, WorkOrder $workorder): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(WorkOrder::STATUSES))],
            'comment' => ['nullable', 'string', 'max:2000'],
            'result_notes' => ['nullable', 'string', 'max:5000'],
        ]);
        abort_if($data['status'] === 'verified' && ! $request->user()->can('ant-workorder.verify'), 403);
        $this->service->transition($workorder, $data['status'], $request->user()->user_id, $data['comment'] ?? null, $data['result_notes'] ?? null);

        return back()->with('antareja_success', "Status {$workorder->number}: " . $workorder->statusMeta()['label'] . '.');
    }

    public function assign(Request $request, WorkOrder $workorder): RedirectResponse
    {
        $data = $request->validate([
            'assigned_to' => ['nullable', 'integer', 'exists:users,user_id'],
            'team' => ['nullable', 'string', 'max:128'],
        ]);
        $this->service->assign($workorder, $data['assigned_to'] ?? null, $data['team'] ?? null, $request->user()->user_id);

        return back()->with('antareja_success', 'Penugasan disimpan.');
    }

    public function checklist(Request $request, WorkOrder $workorder): RedirectResponse
    {
        $data = $request->validate([
            'done' => ['nullable', 'array'], 'done.*' => ['string'],
            'remove' => ['nullable', 'array'], 'remove.*' => ['string'],
            'new_item' => ['nullable', 'string', 'max:255'],
        ]);
        $this->service->updateChecklist($workorder, $data['done'] ?? [], $data['new_item'] ?? null, $data['remove'] ?? [], $request->user()->user_id);

        return back()->with('antareja_success', 'Checklist disimpan.');
    }

    public function material(Request $request, WorkOrder $workorder): RedirectResponse
    {
        $data = $request->validate([
            'item' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'unit' => ['required', 'string', 'max:16'],
            'notes' => ['nullable', 'string', 'max:255'],
        ], [], ['item' => 'nama material', 'quantity' => 'jumlah']);
        $m = $workorder->materials()->create($data);
        $workorder->log('material', "{$m->item}: {$m->quantity} {$m->unit}", null, null, $request->user()->user_id);

        return back()->with('antareja_success', 'Material dicatat.');
    }

    public function removeMaterial(Request $request, WorkOrder $workorder, int $material): RedirectResponse
    {
        $m = $workorder->materials()->findOrFail($material);
        $m->delete();
        $workorder->log('material', "Dihapus: {$m->item}", null, null, $request->user()->user_id);

        return back()->with('antareja_success', 'Material dihapus.');
    }

    public function note(Request $request, WorkOrder $workorder): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $workorder->log('note', $data['body'], null, null, $request->user()->user_id);

        return back()->with('antareja_success', 'Catatan ditambahkan.');
    }

    public function attach(Request $request, WorkOrder $workorder, AttachmentService $attachments): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:' . AttachmentService::ALLOWED_MIMES, 'max:' . AttachmentService::MAX_KB],
            'description' => ['nullable', 'string', 'max:255'],
        ], ['file.mimes' => 'Evidence harus berupa JPG, PNG, WEBP, atau PDF.', 'file.max' => 'Ukuran evidence maksimal 10 MB.']);
        $attachments->store('work_order', $workorder->id, $request->file('file'), $request->input('description'));

        return back()->with('antareja_success', 'Evidence diunggah.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys(WorkOrder::TYPES))],
            'priority' => ['required', Rule::in(array_keys(WorkOrder::PRIORITIES))],
            'description' => ['nullable', 'string', 'max:10000'],
            'site_id' => ['nullable', 'integer', 'exists:ant_sites,id'],
            'area_id' => ['nullable', 'integer', 'exists:ant_areas,id'],
            'device_id' => ['nullable', 'integer', 'exists:devices,device_id'],
            'incident_id' => ['nullable', 'integer', 'exists:ant_incidents,id'],
            'maintenance_window_id' => ['nullable', 'integer', 'exists:ant_maintenance_windows,id'],
            'change_id' => ['nullable', 'integer', 'exists:ant_changes,id'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,user_id'],
            'team' => ['nullable', 'string', 'max:128'],
            'due_at' => ['nullable', 'date'],
        ], [], ['title' => 'judul', 'type' => 'jenis', 'priority' => 'prioritas', 'due_at' => 'tenggat']);
        if (! empty($data['area_id']) && ! empty($data['site_id']) && ! Area::where('id', $data['area_id'])->where('site_id', $data['site_id'])->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['area_id' => 'Area tidak berada di site yang dipilih.']);
        }

        return $data;
    }

    private function formData(WorkOrder $wo): array
    {
        return [
            'wo' => $wo,
            'sites' => Site::orderBy('code')->get(['id', 'code', 'name']),
            'areas' => Area::orderBy('name')->get(['id', 'site_id', 'name']),
            'devices' => Device::orderBy('hostname')->get(),
            'users' => User::orderBy('username')->get(['user_id', 'username', 'realname']),
        ];
    }
}
