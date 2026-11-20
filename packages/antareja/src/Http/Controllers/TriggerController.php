<?php

namespace Antareja\Http\Controllers;

use Antareja\Models\QualitySample;
use Antareja\Models\QualityTarget;
use Antareja\Models\Trigger;
use Antareja\Models\TriggerSample;
use Antareja\Services\TriggerEngine;
use Antareja\Support\Severity;
use App\Models\Device;
use App\Models\Port;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TriggerController extends Controller
{
    public function index(Request $request): View
    {
        $q = Trigger::with('device')->orderByRaw("FIELD(state, 'problem', 'unknown', 'ok')")->orderBy('name');
        if ($request->filled('state')) {
            $q->where('state', $request->query('state'));
        }
        $triggers = $q->get();

        return view('antareja::triggers.index', [
            'triggers' => $triggers,
            'labels' => $triggers->mapWithKeys(fn (Trigger $t) => [$t->id => TriggerEngine::itemLabel($t)]),
            'units' => $triggers->mapWithKeys(fn (Trigger $t) => [$t->id => TriggerEngine::sensorUnit($t)]),
            'counts' => Trigger::where('enabled', true)->selectRaw('state, COUNT(*) AS n')->groupBy('state')->pluck('n', 'state'),
        ]);
    }

    public function show(Trigger $trigger): View
    {
        $since = now()->subDay();
        $points = $trigger->source === 'quality'
            ? QualitySample::where('target_id', $trigger->ref_id)->where('measured_at', '>=', $since)->whereNotNull($trigger->metric)
                ->orderBy('measured_at')->get(['measured_at', $trigger->metric])->map(fn ($s) => [$s->measured_at->getTimestamp(), (float) $s->{$trigger->metric}])
            : TriggerSample::where('trigger_id', $trigger->id)->where('measured_at', '>=', $since)->orderBy('measured_at')
                ->get(['measured_at', 'value'])->map(fn ($s) => [$s->measured_at->getTimestamp(), $s->value]);

        return view('antareja::triggers.show', [
            'trigger' => $trigger->load('device'),
            'item' => TriggerEngine::itemLabel($trigger),
            'unit' => TriggerEngine::sensorUnit($trigger),
            'points' => $points->values()->all(),
            'problems' => \Antareja\Models\Problem::whereHas('sources', fn ($q) => $q->where('source', 'trigger')->where('source_ref', (string) $trigger->id))
                ->latest('started_at')->limit(10)->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $t = new Trigger(['function' => 'avg', 'window_minutes' => 15, 'operator' => '>', 'severity' => 'average', 'enabled' => true]);
        if ($request->filled('quality')) {
            $t->forceFill(['source' => 'quality', 'ref_id' => $request->integer('quality'), 'metric' => 'loss_pct']);
        }

        return view('antareja::triggers.form', $this->formData($t, $request->integer('device') ?: null));
    }

    public function store(Request $request, TriggerEngine $engine): RedirectResponse
    {
        $trigger = Trigger::create($this->validated($request) + ['created_by' => $request->user()->user_id]);
        $engine->evaluate(collect([$trigger]));

        return redirect()->route('antareja.triggers.show', $trigger)->with('antareja_success', "Trigger {$trigger->name} dibuat. Nilai saat ini: " . Trigger::fmt($trigger->last_value, TriggerEngine::sensorUnit($trigger)) . '.');
    }

    public function edit(Request $request, Trigger $trigger): View
    {
        return view('antareja::triggers.form', $this->formData($trigger, $request->integer('device') ?: $trigger->device_id));
    }

    public function update(Request $request, Trigger $trigger, TriggerEngine $engine): RedirectResponse
    {
        $trigger->update($this->validated($request));
        $engine->evaluate(collect([$trigger]));

        return redirect()->route('antareja.triggers.show', $trigger)->with('antareja_success', "Trigger {$trigger->name} disimpan.");
    }

    public function destroy(Trigger $trigger): RedirectResponse
    {
        $trigger->delete();

        return redirect()->route('antareja.triggers.index')->with('antareja_success', "Trigger {$trigger->name} dihapus. Problem terkait pulih pada siklus berikutnya.");
    }

    public function evaluate(Trigger $trigger, TriggerEngine $engine): RedirectResponse
    {
        $engine->evaluate(collect([$trigger]));

        return back()->with('antareja_success', 'Dievaluasi: nilai ' . Trigger::fmt($trigger->last_value, TriggerEngine::sensorUnit($trigger)) . ', status ' . $trigger->stateMeta()['label'] . '.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'description' => ['nullable', 'string', 'max:2000'],
            'item' => ['required', 'regex:/^(quality|port|sensor|wireless|device):\d+$/'],
            'metric' => ['required', 'string', 'max:32'],
            'function' => ['required', Rule::in(array_keys(Trigger::FUNCTIONS))],
            'window_minutes' => ['required', 'integer', 'between:1,10080'],
            'operator' => ['required', Rule::in(array_keys(Trigger::OPERATORS))],
            'threshold' => ['required', 'numeric'],
            'recovery_threshold' => ['nullable', 'numeric'],
            'severity' => ['required', Rule::in(array_keys(Severity::PROBLEM))],
        ], [], ['name' => 'nama', 'item' => 'item', 'threshold' => 'ambang', 'recovery_threshold' => 'ambang pemulihan', 'window_minutes' => 'jendela waktu']);

        [$source, $ref] = explode(':', $data['item']);
        $ref = (int) $ref;
        unset($data['item']);
        if (! isset(Trigger::METRICS[$source][$data['metric']])) {
            throw ValidationException::withMessages(['metric' => 'Metrik tidak tersedia untuk item ' . Trigger::SOURCES[$source] . '.']);
        }
        $exists = match ($source) {
            'quality' => QualityTarget::whereKey($ref)->exists(),
            'port' => Port::whereKey($ref)->exists(),
            'sensor' => DB::table('sensors')->where('sensor_id', $ref)->exists(),
            'wireless' => DB::table('wireless_sensors')->where('sensor_id', $ref)->exists(),
            'device' => Device::whereKey($ref)->exists(),
        };
        if (! $exists) {
            throw ValidationException::withMessages(['item' => 'Item tidak ditemukan.']);
        }
        $data['threshold'] = (float) $data['threshold'];
        $data['recovery_threshold'] = isset($data['recovery_threshold']) ? (float) $data['recovery_threshold'] : null;
        if ($data['recovery_threshold'] !== null) {
            // Ambang pemulihan harus "lebih aman" dari ambang problem, mis. problem > 80, pulih bila ≤ 70
            $safer = in_array($data['operator'], ['>', '>='], true) ? $data['recovery_threshold'] <= $data['threshold']
                : (in_array($data['operator'], ['<', '<='], true) ? $data['recovery_threshold'] >= $data['threshold'] : false);
            if (! $safer) {
                throw ValidationException::withMessages(['recovery_threshold' => 'Ambang pemulihan harus di sisi aman ambang problem (mis. problem > 80, pemulihan 70), dan hanya untuk operator >, ≥, <, ≤.']);
            }
        }

        return $data + ['source' => $source, 'ref_id' => $ref, 'device_id' => TriggerEngine::deviceFor($source, $ref), 'enabled' => $request->boolean('enabled')];
    }

    private function formData(Trigger $trigger, ?int $deviceId): array
    {
        $device = $deviceId ? Device::find($deviceId) : null;

        return [
            'trigger' => $trigger,
            'device' => $device,
            'devices' => Device::orderBy('hostname')->get(['device_id', 'hostname', 'sysName', 'display', 'ip']),
            'qualityTargets' => QualityTarget::orderBy('name')->get(['id', 'name', 'host']),
            'ports' => $device ? Port::where('device_id', $device->device_id)->where('deleted', 0)->orderBy('ifIndex')->get() : collect(),
            'sensors' => $device ? DB::table('sensors')->where('device_id', $device->device_id)->orderBy('sensor_class')->orderBy('sensor_descr')->get() : collect(),
            'wireless' => $device ? DB::table('wireless_sensors')->where('device_id', $device->device_id)->orderBy('sensor_class')->get() : collect(),
            'selectedItem' => old('item', $trigger->source ? $trigger->source . ':' . $trigger->ref_id : null),
        ];
    }
}
