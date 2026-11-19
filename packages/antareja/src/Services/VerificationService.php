<?php

namespace Antareja\Services;

use Antareja\Models\DeviceDetail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Alur verifikasi engineer: Discovered → Pending → Verified / Rejected, serta Retired.
 */
class VerificationService
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    /** Syarat minimum sebelum device boleh menjadi inventaris resmi. */
    public function assertVerifiable(DeviceDetail $detail): void
    {
        $missing = [];
        if (blank($detail->category)) {
            $missing[] = 'kategori';
        }
        if (blank($detail->site_id)) {
            $missing[] = 'site';
        }
        if (blank($detail->pic_user_id) && blank($detail->pic_name)) {
            $missing[] = 'PIC';
        }

        if ($missing) {
            throw ValidationException::withMessages([
                'verify' => sprintf('%s belum bisa diverifikasi: %s wajib diisi.', $detail->auditName(), implode(', ', $missing)),
            ]);
        }
    }

    public function verify(DeviceDetail $detail): void
    {
        $this->assertVerifiable($detail);
        $old = $detail->verification_status;

        $detail->forceFill([
            'verification_status' => DeviceDetail::VERIFIED,
            'verified_by' => Auth::id(),
            'verified_at' => now(),
            'rejected_reason' => null,
        ])->saveQuietly();

        $this->audit->log('verified', $detail, "{$detail->auditName()} diverifikasi menjadi inventaris resmi",
            ['verification_status' => $old], ['verification_status' => DeviceDetail::VERIFIED]);
    }

    public function reject(DeviceDetail $detail, string $reason, bool $disablePolling): void
    {
        DB::transaction(function () use ($detail, $reason, $disablePolling): void {
            $old = $detail->verification_status;
            $detail->forceFill([
                'verification_status' => DeviceDetail::REJECTED,
                'verified_by' => Auth::id(),
                'verified_at' => now(),
                'rejected_reason' => mb_substr($reason, 0, 255),
            ])->saveQuietly();

            if ($disablePolling && $detail->device) {
                $detail->device->disabled = 1;
                $detail->device->save();
            }

            $this->audit->log('rejected', $detail,
                "{$detail->auditName()} ditolak: {$reason}" . ($disablePolling ? ' (polling dinonaktifkan)' : ''),
                ['verification_status' => $old], ['verification_status' => DeviceDetail::REJECTED, 'disabled' => $disablePolling]);
        });
    }

    public function retire(DeviceDetail $detail, string $reason, bool $disablePolling): void
    {
        DB::transaction(function () use ($detail, $reason, $disablePolling): void {
            $old = $detail->only(['verification_status', 'lifecycle_status']);
            $detail->forceFill([
                'verification_status' => DeviceDetail::RETIRED,
                'lifecycle_status' => 'retired',
                'rejected_reason' => mb_substr($reason, 0, 255),
            ])->saveQuietly();

            if ($disablePolling && $detail->device) {
                $detail->device->disabled = 1;
                $detail->device->save();
            }

            $this->audit->log('retired', $detail, "{$detail->auditName()} dipensiunkan: {$reason}", $old,
                ['verification_status' => DeviceDetail::RETIRED, 'lifecycle_status' => 'retired', 'disabled' => $disablePolling]);
        });
    }

    /** Kembalikan device yang ditolak / dipensiunkan ke antrean verifikasi. */
    public function restore(DeviceDetail $detail): void
    {
        $old = $detail->only(['verification_status', 'lifecycle_status']);
        $detail->forceFill([
            'verification_status' => DeviceDetail::PENDING,
            'lifecycle_status' => $detail->lifecycle_status === 'retired' ? 'operational' : $detail->lifecycle_status,
            'verified_by' => null,
            'verified_at' => null,
            'rejected_reason' => null,
        ])->saveQuietly();

        if ($detail->device && $detail->device->disabled) {
            $detail->device->disabled = 0;
            $detail->device->save();
        }

        $this->audit->log('restored', $detail, "{$detail->auditName()} dikembalikan ke antrean verifikasi", $old,
            ['verification_status' => DeviceDetail::PENDING]);
    }
}
