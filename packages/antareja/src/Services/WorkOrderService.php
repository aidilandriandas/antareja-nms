<?php

namespace Antareja\Services;

use Antareja\Models\DeviceDetail;
use Antareja\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkOrderService
{
    public function create(array $data, ?int $userId): WorkOrder
    {
        return DB::transaction(function () use ($data, $userId) {
            $wo = new WorkOrder($data);
            if (empty($wo->site_id) && $wo->device_id) {
                $wo->site_id = DeviceDetail::find($wo->device_id)?->site_id;
            }
            if (empty($wo->due_at)) {
                $wo->due_at = now()->addHours($wo->priorityMeta()['hours']);
            }
            if (empty($data['checklist'])) {
                $wo->checklist = array_map(fn ($t) => ['text' => $t, 'done' => false], WorkOrder::CHECKLISTS[$wo->type] ?? []);
            }
            $wo->forceFill(['status' => $wo->assigned_to || $wo->team ? 'assigned' : 'open', 'created_by' => $userId])->save();
            $wo->forceFill(['number' => sprintf('WO-%s-%04d', now()->format('ym'), $wo->id)])->saveQuietly();
            $wo->log('system', 'Work order dibuat' . ($wo->incident_id ? ' dari incident' : ($wo->change_id ? ' dari change request' : ($wo->maintenance_window_id ? ' dari maintenance' : ''))) . '.', null, $wo->status, $userId);

            return $wo;
        });
    }

    public function transition(WorkOrder $wo, string $to, ?int $userId, ?string $comment = null, ?string $resultNotes = null): void
    {
        $from = $wo->status;
        if (! in_array($to, WorkOrder::TRANSITIONS[$from] ?? [], true)) {
            throw ValidationException::withMessages(['status' => "Status tidak bisa diubah dari {$from} ke {$to}."]);
        }
        if ($resultNotes !== null && trim($resultNotes) !== '') {
            $wo->result_notes = $resultNotes;
        }
        if ($to === 'done') {
            $p = $wo->checklistProgress();
            if ($p['done'] < $p['total']) {
                throw ValidationException::withMessages(['status' => "Checklist belum lengkap ({$p['done']}/{$p['total']}). Centang atau hapus butir yang tidak relevan."]);
            }
            if (blank($wo->result_notes)) {
                throw ValidationException::withMessages(['result_notes' => 'Isi hasil pekerjaan sebelum menandai selesai.']);
            }
            $wo->completed_at = now();
        }
        if ($to === 'verified') {
            if ($userId !== null && $userId === $wo->assigned_to) {
                throw ValidationException::withMessages(['status' => 'Verifikasi harus oleh orang lain, bukan pelaksana work order.']);
            }
            $wo->forceFill(['verified_at' => now(), 'verified_by' => $userId]);
        }
        if ($to === 'in_progress' && ! $wo->started_at) {
            $wo->started_at = now();
        }
        if ($to === 'in_progress' && $from === 'done') {
            $wo->completed_at = null;
        }
        $wo->status = $to;
        $wo->save();
        $wo->log('status', $comment, $from, $to, $userId);
    }

    public function assign(WorkOrder $wo, ?int $assignee, ?string $team, ?int $userId): void
    {
        $wo->forceFill(['assigned_to' => $assignee, 'team' => $team])->save();
        if ($wo->status === 'open' && ($assignee || $team)) {
            $wo->forceFill(['status' => 'assigned'])->save();
        }
        $wo->log('assignment', 'Ditugaskan ke ' . ($wo->fresh('assignee')->assigneeLabel() ?? '—'), null, null, $userId);
    }

    /** Ubah checklist: centang/hilangkan centang, tambah butir, hapus butir. */
    public function updateChecklist(WorkOrder $wo, array $done, ?string $newItem, array $remove, ?int $userId): void
    {
        $items = [];
        foreach (array_values($wo->checklist ?? []) as $i => $item) {
            if (in_array((string) $i, $remove, true)) {
                continue;
            }
            $was = ! empty($item['done']);
            $now = in_array((string) $i, $done, true);
            if ($now && ! $was) {
                $item['done_at'] = now()->toDateTimeString();
                $item['done_by'] = $userId;
            }
            $item['done'] = $now;
            $items[] = $item;
        }
        if ($newItem !== null && trim($newItem) !== '') {
            $items[] = ['text' => mb_substr(trim($newItem), 0, 255), 'done' => false];
        }
        $before = $wo->checklistProgress();
        $wo->checklist = $items;
        $wo->save();
        $after = $wo->checklistProgress();
        $wo->log('checklist', "Checklist {$after['done']}/{$after['total']}", "{$before['done']}/{$before['total']}", "{$after['done']}/{$after['total']}", $userId);
    }
}
