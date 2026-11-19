<?php

namespace Antareja\Services;

use Antareja\Models\DeviceDetail;
use Antareja\Models\EscalationPolicy;
use Antareja\Models\Incident;
use Antareja\Models\Problem;
use Antareja\Support\Severity;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IncidentService
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function create(array $data, ?int $userId, string $source = 'manual'): Incident
    {
        return DB::transaction(function () use ($data, $userId, $source) {
            $incident = new Incident($data);
            if (empty($incident->site_id) && $incident->device_id) {
                $incident->site_id = DeviceDetail::find($incident->device_id)?->site_id;
            }
            $incident->forceFill([
                'status' => 'open', 'source' => $source, 'created_by' => $userId,
                'started_at' => $data['started_at'] ?? now(),
                'escalation_policy_id' => EscalationPolicy::for($incident->severity, $incident->site_id)?->id,
            ])->save();
            $incident->forceFill(['number' => sprintf('INC-%s-%04d', now()->format('ym'), $incident->id)])->saveQuietly();
            $incident->note('system', match ($source) {
                'auto' => 'Incident dibuat otomatis dari problem.',
                'problem' => 'Incident dibuat dari problem.',
                default => 'Incident dibuat manual.',
            }, null, 'open', $userId);

            return $incident;
        });
    }

    /** Buat incident dari satu problem; problem anak (terdampak) ikut ditautkan. */
    public function fromProblem(Problem $problem, ?int $userId, string $source = 'problem'): Incident
    {
        if ($problem->incident_id && $problem->incident) {
            return $problem->incident;
        }
        $incident = $this->create([
            'title' => $problem->title,
            'description' => trim(($problem->detail ?? '') . "\nDibuka otomatis dari problem #{$problem->id}."),
            'severity' => Severity::PROBLEM_TO_INCIDENT[$problem->severity] ?? 'medium',
            'device_id' => $problem->device_id,
            'site_id' => $problem->site_id,
            'started_at' => $problem->started_at,
        ], $userId, $source);
        $this->link($incident, $problem, $userId);
        foreach (Problem::open()->where('parent_problem_id', $problem->id)->whereNull('incident_id')->get() as $child) {
            $this->link($incident, $child, $userId);
        }

        return $incident;
    }

    public function link(Incident $incident, Problem $problem, ?int $userId): void
    {
        $problem->forceFill(['incident_id' => $incident->id])->save();
        $problem->log('incident', "Ditautkan ke incident {$incident->number}", $userId);
        $incident->note('link', "Problem ditautkan: {$problem->title}", null, (string) $problem->id, $userId);
    }

    public function transition(Incident $incident, string $to, ?int $userId, ?string $comment = null, array $fields = []): void
    {
        $from = $incident->status;
        if (! in_array($to, Incident::TRANSITIONS[$from] ?? [], true)) {
            throw ValidationException::withMessages(['status' => "Status tidak bisa diubah dari {$from} ke {$to}."]);
        }
        $fields = array_filter($fields, fn ($v) => $v !== null && $v !== '');
        $incident->fill($fields);

        if ($to === 'closed' && (blank($incident->root_cause) || blank($incident->corrective_action))) {
            throw ValidationException::withMessages(['root_cause' => 'Root cause dan corrective action wajib diisi sebelum incident ditutup.']);
        }

        $now = now();
        $updates = ['status' => $to];
        if ($from === 'open' && ! $incident->acknowledged_at) {
            $updates['acknowledged_at'] = $now;
        }
        if ($to === 'resolved') {
            $updates['resolved_at'] = $now;
            $updates['resolved_notified'] = false;
        }
        if ($to === 'closed') {
            $updates['closed_at'] = $now;
            $updates['resolved_at'] = $incident->resolved_at ?? $now;
        }
        if (in_array($to, ['in_progress', 'pending'], true) && in_array($from, ['resolved', 'closed'], true)) {
            $updates['resolved_at'] = null;
            $updates['closed_at'] = null;
        }
        $incident->forceFill($updates)->save();
        $incident->note('status', $comment ?: null, $from, $to, $userId);
    }

    public function assign(Incident $incident, ?int $assigneeId, ?string $picName, ?int $userId): void
    {
        $old = $incident->picLabel();
        $incident->forceFill(['assigned_to' => $assigneeId, 'pic_name' => $picName])->save();
        $incident->refresh();
        $incident->note('assignment', 'PIC: ' . ($incident->picLabel() ?? '—'), $old ? mb_substr($old, 0, 64) : null, $incident->picLabel() ? mb_substr($incident->picLabel(), 0, 64) : null, $userId);
    }

    /**
     * Statistik operasional periode (untuk Beranda & laporan).
     *
     * @return array{open:int, critical_open:int, mtta:?int, mttr:?int, resolved:int}
     */
    public function stats(\Carbon\CarbonInterface $from): array
    {
        $resolved = Incident::whereNotNull('resolved_at')->where('started_at', '>=', $from)->get();
        $acked = Incident::whereNotNull('acknowledged_at')->where('started_at', '>=', $from)->get();

        return [
            'open' => Incident::active()->count(),
            'critical_open' => Incident::active()->where('severity', 'critical')->count(),
            'resolved' => $resolved->count(),
            'mtta' => $acked->isEmpty() ? null : (int) round($acked->avg(fn ($i) => $i->secondsToAck())),
            'mttr' => $resolved->isEmpty() ? null : (int) round($resolved->avg(fn ($i) => $i->secondsToResolve())),
        ];
    }
}
