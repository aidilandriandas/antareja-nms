<?php

namespace Antareja\Services;

use Antareja\Models\Change;
use Antareja\Models\MaintenanceWindow;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Alur change request: draft → submitted → approved / rejected → in_progress → completed / failed.
 * Persetujuan membuat jendela maintenance jenis "change" (alert ditekan, opsi pengecualian SLA) dan
 * menjadi dasar sah tidaknya perubahan konfigurasi yang terdeteksi backup.
 */
class ChangeService
{
    public function __construct(private readonly MaintenanceService $maintenance)
    {
    }

    public function create(array $data, array $devices, ?int $userId): Change
    {
        return DB::transaction(function () use ($data, $devices, $userId) {
            $c = new Change($data);
            $c->forceFill(['status' => 'draft', 'requested_by' => $userId])->save();
            $c->forceFill(['number' => sprintf('CHG-%s-%04d', now()->format('ym'), $c->id)])->saveQuietly();
            $c->devices()->sync($devices);
            $c->log('system', 'Change request dibuat.', null, 'draft', $userId);

            return $c;
        });
    }

    public function update(Change $c, array $data, array $devices, ?int $userId): void
    {
        if (! in_array($c->status, ['draft', 'submitted', 'rejected'], true)) {
            throw ValidationException::withMessages(['status' => 'Change yang sudah disetujui tidak bisa diubah. Batalkan lalu ajukan ulang bila rencana berubah.']);
        }
        $c->update($data);
        $c->devices()->sync($devices);
        if ($c->status === 'rejected') {
            $this->move($c, 'draft', $userId, 'Diperbaiki setelah ditolak.');
        }
    }

    public function submit(Change $c, ?int $userId): void
    {
        $missing = [];
        if (! $c->planned_start || ! $c->planned_end) {
            $missing[] = 'jadwal mulai & selesai';
        }
        if ($c->devices()->count() === 0) {
            $missing[] = 'perangkat terdampak';
        }
        if (blank($c->description)) {
            $missing[] = 'rencana implementasi';
        }
        if ($c->type !== 'standard' && blank($c->rollback_plan)) {
            $missing[] = 'rencana rollback';
        }
        if ($missing) {
            throw ValidationException::withMessages(['status' => 'Lengkapi dulu: ' . implode(', ', $missing) . '.']);
        }
        $this->move($c, 'submitted', $userId);
    }

    public function approve(Change $c, ?int $userId, ?string $note): void
    {
        if ($userId !== null && $userId === $c->requested_by) {
            throw ValidationException::withMessages(['status' => 'Persetujuan harus oleh orang lain, bukan pengaju change.']);
        }
        DB::transaction(function () use ($c, $userId, $note): void {
            $c->forceFill(['approved_by' => $userId, 'approved_at' => now(), 'approval_note' => $note]);
            $this->move($c, 'approved', $userId, $note);
            $window = $this->maintenance->save(new MaintenanceWindow(), [
                'title' => "{$c->number}: {$c->title}",
                'type' => 'change',
                'starts_at' => $c->planned_start,
                'ends_at' => $c->planned_end,
                'description' => "Dibuat otomatis dari change request {$c->number}.\n\nRencana:\n{$c->description}\n\nRollback:\n{$c->rollback_plan}",
                'site_id' => $c->site_id,
                'exclude_from_sla' => $c->exclude_from_sla,
            ], $c->devices()->pluck('devices.device_id')->all(), $userId);
            $c->forceFill(['maintenance_window_id' => $window->id])->saveQuietly();
            $c->log('system', "Jendela maintenance dibuat: {$window->title}", null, null, $userId);
        });
    }

    public function reject(Change $c, ?int $userId, string $note): void
    {
        if ($userId !== null && $userId === $c->requested_by) {
            throw ValidationException::withMessages(['status' => 'Penolakan harus oleh orang lain, bukan pengaju change.']);
        }
        $c->forceFill(['approval_note' => $note]);
        $this->move($c, 'rejected', $userId, $note);
    }

    public function start(Change $c, ?int $userId): void
    {
        $c->forceFill(['implemented_at' => now()]);
        $this->move($c, 'in_progress', $userId);
    }

    public function finish(Change $c, string $result, string $review, ?int $userId): void
    {
        $c->forceFill(['result' => $result, 'review_notes' => $review]);
        $this->move($c, in_array($result, ['failed', 'rolled_back'], true) ? 'failed' : 'completed', $userId, Change::RESULTS[$result] . ': ' . $review);
        if ($c->maintenance && ! in_array($c->maintenance->status, ['completed', 'cancelled'], true)) {
            $this->maintenance->complete($c->maintenance, "{$c->number} — " . Change::RESULTS[$result] . "\n{$review}", $result === 'success' ? 'success' : ($result === 'partial' ? 'partial' : 'failed'));
        }
    }

    public function cancel(Change $c, ?int $userId, ?string $note): void
    {
        $this->move($c, 'cancelled', $userId, $note);
        if ($c->maintenance && ! in_array($c->maintenance->status, ['completed', 'cancelled'], true)) {
            $this->maintenance->cancel($c->maintenance);
        }
    }

    private const FLOW = [
        'draft' => ['submitted', 'cancelled'],
        'submitted' => ['approved', 'rejected', 'cancelled', 'draft'],
        'rejected' => ['draft', 'cancelled'],
        'approved' => ['in_progress', 'cancelled'],
        'in_progress' => ['completed', 'failed'],
    ];

    private function move(Change $c, string $to, ?int $userId, ?string $note = null): void
    {
        $from = $c->status;
        if (! in_array($to, self::FLOW[$from] ?? [], true)) {
            throw ValidationException::withMessages(['status' => "Status tidak bisa diubah dari {$from} ke {$to}."]);
        }
        $c->status = $to;
        $c->save();
        $c->log('status', $note, $from, $to, $userId);
    }
}
