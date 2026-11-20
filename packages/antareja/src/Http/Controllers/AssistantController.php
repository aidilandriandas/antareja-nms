<?php

namespace Antareja\Http\Controllers;

use Antareja\Models\AiReport;
use Antareja\Models\Anomaly;
use Antareja\Models\Incident;
use Antareja\Services\AiAssistant;
use Antareja\Services\AiContextBuilder;
use Antareja\Services\AiUnavailable;
use Antareja\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AssistantController extends Controller
{
    private const SHIFT_TASK = 'Tulis laporan serah terima shift NOC dari data di atas dengan bagian: 1) Ringkasan kondisi (2-3 kalimat), 2) Gangguan yang masih aktif & yang perlu diawasi shift berikutnya, 3) Yang sudah selesai, 4) Pekerjaan lapangan & change yang perlu ditindaklanjuti, 5) Catatan risiko (anomali, perubahan tanpa change request, work order lewat tenggat). Bila tidak ada kejadian, katakan dengan jelas.';

    private const INCIDENT_TASK = 'Tulis draf untuk engineer dengan bagian: 1) Ringkasan kejadian (kronologi singkat dengan waktu), 2) Dugaan penyebab — urutkan dari yang paling didukung data, sebut buktinya, 3) Langkah pengecekan berikutnya yang konkret (fisik, kabel/fiber, catu daya, konfigurasi, perangkat induk), 4) Hal yang tidak diketahui dari data. Jangan menetapkan root cause final.';

    public function __construct(private readonly AiAssistant $ai, private readonly AiContextBuilder $context, private readonly AuditLogger $audit)
    {
    }

    public function index(): View
    {
        return view('antareja::assistant.index', [
            'enabled' => $this->ai->enabled(),
            'reason' => $this->ai->unavailableReason(),
            'model' => $this->ai->model(),
            'reports' => AiReport::with('creator')->latest('id')->limit(25)->get(),
            'anomalies' => Anomaly::where('status', 'open')->count(),
        ]);
    }

    public function shift(Request $request): View|RedirectResponse
    {
        $data = $request->validate(['hours' => ['required', 'integer', Rule::in([8, 12, 24])], 'preview' => ['nullable', 'boolean']]);
        $ctx = $this->context->shift((int) $data['hours']);
        if ($request->boolean('preview')) {
            return view('antareja::assistant.preview', ['title' => "Data serah terima {$data['hours']} jam", 'context' => $ctx, 'enabled' => $this->ai->enabled()]);
        }

        $r = $this->generate($request, 'shift', null, "Serah terima shift {$data['hours']} jam · " . now()->format('d M H:i'), $ctx, self::SHIFT_TASK);

        return $r instanceof AiReport
            ? redirect()->route('antareja.assistant.show', $r)->with('antareja_success', 'Draf dibuat. Ini draf AI — periksa sebelum dibagikan.')
            : $r;
    }

    public function incident(Request $request, Incident $incident): View|RedirectResponse
    {
        $ctx = $this->context->incident($incident);
        if ($request->boolean('preview')) {
            return view('antareja::assistant.preview', ['title' => "Data incident {$incident->number}", 'context' => $ctx, 'enabled' => $this->ai->enabled()]);
        }
        $r = $this->generate($request, 'incident', $incident->id, "Draf analisis {$incident->number}", $ctx, self::INCIDENT_TASK);
        if (! $r instanceof AiReport) {
            return $r;
        }
        $incident->note('ai', 'Draf analisis AI dibuat — perlu diverifikasi engineer sebelum dipakai.', null, (string) $r->id, $request->user()->user_id);

        return redirect()->route('antareja.incidents.show', $incident)->with('antareja_success', 'Draf analisis AI dibuat. Periksa sebelum memakai isinya.');
    }

    public function show(AiReport $report): View
    {
        return view('antareja::assistant.show', ['report' => $report->load('creator')]);
    }

    private function generate(Request $request, string $kind, ?int $subjectId, string $title, string $ctx, string $task): AiReport|RedirectResponse
    {
        try {
            $out = $this->ai->write(AiContextBuilder::SYSTEM, $ctx, $task);
        } catch (AiUnavailable $e) {
            return back()->with('antareja_error', $e->getMessage());
        }
        $report = AiReport::create([
            'kind' => $kind, 'subject_id' => $subjectId, 'title' => $title, 'content' => $out['text'], 'context' => $ctx,
            'model' => $out['model'], 'fallback' => $out['fallback'], 'input_tokens' => $out['input_tokens'],
            'output_tokens' => $out['output_tokens'], 'created_by' => $request->user()->user_id,
        ]);
        $this->audit->log('created', 'ai_report', "Draf AI dibuat: {$title} (model {$out['model']})");

        return $report;
    }
}
