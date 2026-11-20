<?php

namespace Antareja\Http\Controllers;

use Antareja\Models\Change;
use Antareja\Models\ConfigVersion;
use Antareja\Models\Site;
use Antareja\Services\ChangeService;
use App\Models\Device;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ChangeController extends Controller
{
    public function __construct(private readonly ChangeService $service)
    {
    }

    public function index(Request $request): View
    {
        $q = Change::with(['site', 'requester'])->withCount('devices')
            ->orderByRaw("FIELD(status, 'submitted', 'approved', 'in_progress', 'draft', 'rejected', 'failed', 'completed', 'cancelled')")
            ->orderBy('planned_start');
        $view = $request->query('view', 'open');
        match ($view) {
            'approval' => $q->where('status', 'submitted'),
            'closed' => $q->whereIn('status', ['completed', 'failed', 'cancelled', 'rejected']),
            'all' => null,
            default => $q->whereIn('status', ['draft', 'submitted', 'approved', 'in_progress']),
        };

        return view('antareja::changes.index', [
            'changes' => $q->paginate(50)->withQueryString(),
            'view' => $view,
            'pending' => Change::where('status', 'submitted')->count(),
        ]);
    }

    public function create(Request $request): View
    {
        $c = new Change(['type' => 'normal', 'risk' => 'medium', 'exclude_from_sla' => true]);
        $selected = $request->filled('device') ? [$request->integer('device')] : [];

        return view('antareja::changes.form', $this->formData($c, $selected));
    }

    public function store(Request $request): RedirectResponse
    {
        [$data, $devices] = $this->validated($request);
        $c = $this->service->create($data, $devices, $request->user()->user_id);
        if ($request->boolean('submit_now')) {
            try {
                $this->service->submit($c, $request->user()->user_id);
            } catch (\Illuminate\Validation\ValidationException $e) {
                // Draft tetap tersimpan; tampilkan apa yang kurang agar tidak terbuat ganda saat dikirim ulang
                return redirect()->route('antareja.changes.show', $c)->with('antareja_error', "{$c->number} disimpan sebagai draft. " . collect($e->errors())->flatten()->first());
            }
        }

        return redirect()->route('antareja.changes.show', $c)->with('antareja_success', "Change request {$c->number} " . ($c->status === 'submitted' ? 'diajukan untuk persetujuan.' : 'disimpan sebagai draft.'));
    }

    public function show(Change $change): View
    {
        return view('antareja::changes.show', [
            'change' => $change->load(['devices', 'site', 'requester', 'approver', 'maintenance', 'logs.user', 'workOrders']),
            'configVersions' => ConfigVersion::with('device')->where('change_id', $change->id)->orderByDesc('collected_at')->get(),
        ]);
    }

    public function edit(Change $change): View
    {
        return view('antareja::changes.form', $this->formData($change, $change->devices()->pluck('devices.device_id')->all()));
    }

    public function update(Request $request, Change $change): RedirectResponse
    {
        [$data, $devices] = $this->validated($request);
        $this->service->update($change, $data, $devices, $request->user()->user_id);

        return redirect()->route('antareja.changes.show', $change)->with('antareja_success', "Change request {$change->number} disimpan.");
    }

    public function action(Request $request, Change $change): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['submit', 'approve', 'reject', 'start', 'finish', 'cancel', 'note'])],
            'note' => ['nullable', 'string', 'max:5000'],
            'result' => ['nullable', Rule::in(array_keys(Change::RESULTS))],
        ]);
        $uid = $request->user()->user_id;
        $note = $data['note'] ?? null;

        switch ($data['action']) {
            case 'submit':
                $this->service->submit($change, $uid);
                break;
            case 'approve':
                abort_unless($request->user()->can('ant-change.approve'), 403);
                $this->service->approve($change, $uid, $note);
                break;
            case 'reject':
                abort_unless($request->user()->can('ant-change.approve'), 403);
                $request->validate(['note' => ['required']], ['note.required' => 'Tuliskan alasan penolakan.']);
                $this->service->reject($change, $uid, $note);
                break;
            case 'start':
                $this->service->start($change, $uid);
                break;
            case 'finish':
                $request->validate(['result' => ['required'], 'note' => ['required']], ['result.required' => 'Pilih hasil.', 'note.required' => 'Tuliskan hasil / review pelaksanaan.']);
                $this->service->finish($change, $data['result'], $note, $uid);
                break;
            case 'cancel':
                $this->service->cancel($change, $uid, $note);
                break;
            case 'note':
                $request->validate(['note' => ['required']]);
                $change->log('note', $note, null, null, $uid);
                break;
        }

        return back()->with('antareja_success', "{$change->number}: " . ($data['action'] === 'note' ? 'catatan ditambahkan.' : $change->fresh()->statusMeta()['label'] . '.'));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys(Change::TYPES))],
            'risk' => ['required', Rule::in(array_keys(Change::RISKS))],
            'description' => ['nullable', 'string', 'max:10000'],
            'impact' => ['nullable', 'string', 'max:5000'],
            'rollback_plan' => ['nullable', 'string', 'max:5000'],
            'test_plan' => ['nullable', 'string', 'max:5000'],
            'site_id' => ['nullable', 'integer', 'exists:ant_sites,id'],
            'planned_start' => ['nullable', 'date'],
            'planned_end' => ['nullable', 'date', 'after:planned_start'],
            'devices' => ['nullable', 'array', 'max:1000'],
            'devices.*' => ['integer', 'exists:devices,device_id'],
        ], ['planned_end.after' => 'Waktu selesai harus setelah waktu mulai.'], ['title' => 'judul', 'planned_start' => 'jadwal mulai', 'planned_end' => 'jadwal selesai']);
        $devices = array_map('intval', $data['devices'] ?? []);
        unset($data['devices']);
        $data['exclude_from_sla'] = $request->boolean('exclude_from_sla');

        return [$data, $devices];
    }

    private function formData(Change $c, array $selected): array
    {
        return [
            'change' => $c,
            'selected' => $selected,
            'devices' => Device::orderBy('hostname')->get(),
            'sites' => Site::orderBy('code')->get(['id', 'code', 'name']),
        ];
    }
}
