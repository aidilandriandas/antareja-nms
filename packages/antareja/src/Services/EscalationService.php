<?php

namespace Antareja\Services;

use Antareja\Models\EscalationPolicy;
use Antareja\Models\Incident;
use Antareja\Models\Problem;
use Antareja\Support\Severity;
use App\Models\AlertTransport;
use Illuminate\Support\Facades\Log;

/**
 * Eskalasi & notifikasi (antareja:escalate, setiap menit).
 *
 *  - Problem terbuka yang tidak ditekan, belum di-acknowledge, dan belum punya incident → incident otomatis
 *    bila ada kebijakan dengan auto_create_incident dan umur problem ≥ auto_create_after_minutes.
 *  - Incident berstatus Open → langkah eskalasi dikirim sesuai jadwal (after_minutes) lewat transport alert poller
 *    (Telegram, email, Mattermost, …). Berhenti begitu incident di-acknowledge / dikerjakan.
 *  - Incident Resolved → notifikasi pemulihan (bila notify_resolved).
 * Notifikasi dikirim per INCIDENT, bukan per event, sehingga gangguan besar tidak membanjiri grup.
 */
class EscalationService
{
    public function __construct(private readonly IncidentService $incidents)
    {
    }

    /**
     * @return array{auto_incidents:int, notifications:int}
     */
    public function run(): array
    {
        $result = ['auto_incidents' => 0, 'notifications' => 0];

        // 1. Incident otomatis
        $policies = EscalationPolicy::where('active', true)->where('auto_create_incident', true)->get();
        if ($policies->isNotEmpty()) {
            Problem::open()->whereNull('incident_id')->whereNull('suppressed_reason')->whereNull('acknowledged_at')->get()
                ->each(function (Problem $p) use ($policies, &$result): void {
                    $sev = Severity::PROBLEM_TO_INCIDENT[$p->severity] ?? 'medium';
                    $policy = $policies->first(fn (EscalationPolicy $pol) => $pol->matches($sev, $p->site_id));
                    if ($policy && $p->started_at->lte(now()->subMinutes($policy->auto_create_after_minutes))) {
                        $this->incidents->fromProblem($p, null, 'auto');
                        $result['auto_incidents']++;
                    }
                });
        }

        // 2. Eskalasi bertahap untuk incident Open
        Incident::where('status', 'open')->whereNotNull('escalation_policy_id')->get()->each(function (Incident $i) use (&$result): void {
            $policy = EscalationPolicy::find($i->escalation_policy_id);
            if (! $policy || ! $policy->active) {
                return;
            }
            $minutes = $i->started_at->diffInMinutes(now());
            foreach (array_values($policy->steps ?? []) as $n => $step) {
                if ($n <= $i->last_escalation_step || $minutes < (int) ($step['after_minutes'] ?? 0)) {
                    continue;
                }
                $sent = $this->send($step['transport_ids'] ?? [], $this->message($i, $n + 1));
                $i->forceFill(['last_escalation_step' => $n])->save();
                $i->note('escalation', sprintf('Eskalasi tahap %d (menit ke-%d): %s', $n + 1, (int) ($step['after_minutes'] ?? 0), $sent['summary']), null, (string) ($n + 1));
                $result['notifications'] += $sent['ok'];
            }
        });

        // 3. Notifikasi pemulihan
        Incident::where('status', 'resolved')->where('resolved_notified', false)->whereNotNull('escalation_policy_id')
            ->where('last_escalation_step', '>=', 0)->get()->each(function (Incident $i) use (&$result): void {
                $policy = EscalationPolicy::find($i->escalation_policy_id);
                if ($policy?->notify_resolved) {
                    $ids = collect($policy->steps ?? [])->take($i->last_escalation_step + 1)->pluck('transport_ids')->flatten()->unique()->all();
                    $sent = $this->send($ids, $this->message($i, null));
                    $i->note('escalation', 'Notifikasi pemulihan: ' . $sent['summary']);
                    $result['notifications'] += $sent['ok'];
                }
                $i->forceFill(['resolved_notified' => true])->save();
            });

        return $result;
    }

    public function message(Incident $i, ?int $step): string
    {
        $sev = $i->severityMeta()['label'];
        $lines = [
            $step === null ? "[PULIH] {$i->number}" : "[INCIDENT {$sev}] {$i->number}" . ($step > 1 ? " — eskalasi tahap {$step}" : ''),
            $i->title,
            'Perangkat: ' . ($i->device?->displayName() ?? '—') . ' · Site: ' . ($i->site?->code ?? '—'),
            'Mulai: ' . $i->started_at->format('d M Y H:i') . ($step === null && $i->resolved_at ? ' · Pulih: ' . $i->resolved_at->format('d M Y H:i') : ''),
            'PIC: ' . ($i->picLabel() ?? 'belum ditugaskan'),
        ];
        $affected = $i->problems()->count();
        if ($affected > 1) {
            $lines[] = "Problem terkait: {$affected}";
        }
        $lines[] = self::link('antareja/incidents/' . $i->id);

        return implode("\n", $lines);
    }

    /** Tautan absolut ke ANTAREJA untuk pesan notifikasi (cron tidak tahu hostname, jadi pakai ANTAREJA_PUBLIC_URL). */
    public static function link(string $path): string
    {
        $base = rtrim((string) (config('antareja.public_url') ?: url('/')), '/');

        return $base . '/' . ltrim($path, '/');
    }

    /**
     * Kirim lewat transport alert poller.
     *
     * @param  int[]  $transportIds
     * @return array{ok:int, summary:string}
     */
    public function send(array $transportIds, string $message, string $title = 'ANTAREJA NMS'): array
    {
        $ok = 0;
        $parts = [];
        foreach (AlertTransport::whereIn('transport_id', $transportIds)->get() as $t) {
            try {
                $delivered = $t->instance()->deliverAlert([
                    'title' => $title . ' — ' . strtok($message, "\n"),
                    'msg' => $message,
                    'severity' => 'critical',
                    'state' => 1,
                    'hostname' => 'ANTAREJA',
                    'sysName' => 'ANTAREJA',
                    'name' => $title,
                    'timestamp' => now()->toDateTimeString(),
                    'faults' => [],
                    'contacts' => [],
                    'graphs' => [],
                ]);
                $ok += $delivered ? 1 : 0;
                $parts[] = $t->transport_name . ($delivered ? ' ✓' : ' ✗');
            } catch (\Throwable $e) {
                Log::error("ANTAREJA eskalasi via {$t->transport_name} gagal: " . $e->getMessage());
                $parts[] = $t->transport_name . ' ✗ (' . mb_substr($e->getMessage(), 0, 80) . ')';
            }
        }

        return ['ok' => $ok, 'summary' => $parts ? implode(', ', $parts) : 'tidak ada transport yang dikonfigurasi'];
    }
}
