<?php

namespace Antareja\Http\Controllers;

use Antareja\Models\EscalationPolicy;
use Antareja\Models\Site;
use Antareja\Services\EscalationService;
use Antareja\Support\Severity;
use App\Models\AlertTransport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EscalationController extends Controller
{
    public function index(): View
    {
        return view('antareja::escalation.index', [
            'policies' => EscalationPolicy::with('site')->orderBy('name')->get(),
            'transports' => AlertTransport::orderBy('transport_name')->get()->keyBy('transport_id'),
        ]);
    }

    public function create(): View
    {
        return view('antareja::escalation.form', $this->formData(new EscalationPolicy([
            'min_severity' => 'high', 'active' => true, 'auto_create_incident' => true, 'auto_create_after_minutes' => 5,
            'notify_resolved' => true, 'steps' => [['after_minutes' => 0, 'transport_ids' => []], ['after_minutes' => 30, 'transport_ids' => []]],
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $policy = EscalationPolicy::create($this->validated($request));

        return redirect()->route('antareja.escalation.index')->with('antareja_success', "Kebijakan {$policy->name} dibuat.");
    }

    public function edit(EscalationPolicy $policy): View
    {
        return view('antareja::escalation.form', $this->formData($policy));
    }

    public function update(Request $request, EscalationPolicy $policy): RedirectResponse
    {
        $policy->update($this->validated($request));

        return redirect()->route('antareja.escalation.index')->with('antareja_success', "Kebijakan {$policy->name} disimpan.");
    }

    public function destroy(EscalationPolicy $policy): RedirectResponse
    {
        $policy->delete();

        return redirect()->route('antareja.escalation.index')->with('antareja_success', "Kebijakan {$policy->name} dihapus.");
    }

    public function test(EscalationPolicy $policy, EscalationService $escalation): RedirectResponse
    {
        $ids = collect($policy->steps ?? [])->pluck('transport_ids')->flatten()->unique()->all();
        $sent = $escalation->send($ids, "[UJI] Pesan uji eskalasi ANTAREJA NMS\nKebijakan: {$policy->name}\n" . EscalationService::link('antareja/escalation'));

        return back()->with($sent['ok'] > 0 ? 'antareja_success' : 'antareja_error', 'Uji kirim: ' . $sent['summary']);
    }

    private function validated(Request $request): array
    {
        // Baris tahap tanpa kanal dianggap kosong
        $request->merge(['steps' => array_values(array_filter((array) $request->input('steps', []), fn ($s) => ! empty($s['transport_ids'])))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'min_severity' => ['required', Rule::in(array_keys(Severity::INCIDENT))],
            'site_id' => ['nullable', 'integer', 'exists:ant_sites,id'],
            'auto_create_after_minutes' => ['required', 'integer', 'between:0,1440'],
            'steps' => ['required', 'array', 'min:1', 'max:6'],
            'steps.*.after_minutes' => ['required', 'integer', 'between:0,10080'],
            'steps.*.transport_ids' => ['nullable', 'array'],
            'steps.*.transport_ids.*' => ['integer', 'exists:alert_transports,transport_id'],
        ], [
            'steps.required' => 'Pilih minimal satu kanal notifikasi pada salah satu tahap.',
            'steps.min' => 'Pilih minimal satu kanal notifikasi pada salah satu tahap.',
            'steps.*.after_minutes.required' => 'Isi menit untuk setiap tahap yang punya kanal.',
        ], ['name' => 'nama kebijakan']);

        $steps = collect($data['steps'])->map(fn ($s) => ['after_minutes' => (int) $s['after_minutes'], 'transport_ids' => array_map('intval', $s['transport_ids'] ?? [])])
            ->filter(fn ($s) => $s['transport_ids'] !== [])->sortBy('after_minutes')->values()->all();

        return array_merge($data, [
            'steps' => $steps,
            'active' => $request->boolean('active'),
            'auto_create_incident' => $request->boolean('auto_create_incident'),
            'notify_resolved' => $request->boolean('notify_resolved'),
        ]);
    }

    private function formData(EscalationPolicy $policy): array
    {
        return [
            'policy' => $policy,
            'sites' => Site::orderBy('code')->get(['id', 'code', 'name']),
            'transports' => AlertTransport::orderBy('transport_name')->get(),
        ];
    }
}
