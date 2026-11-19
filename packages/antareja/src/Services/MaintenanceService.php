<?php

namespace Antareja\Services;

use Antareja\Models\DeviceDetail;
use Antareja\Models\MaintenanceWindow;
use App\Models\AlertSchedule;
use Illuminate\Support\Facades\DB;
use LibreNMS\Enum\MaintenanceBehavior;

/**
 * Jendela maintenance ANTAREJA adalah sumber kebenaran. Setiap jendela yang punya perangkat disinkronkan ke
 * jadwal maintenance poller (alert_schedule, perilaku "Mute alerts") sehingga notifikasi alert poller ikut ditekan,
 * dan problem ANTAREJA pada perangkat tersebut berstatus "ditekan: maintenance".
 */
class MaintenanceService
{
    /**
     * @param  int[]  $deviceIds
     */
    public function save(MaintenanceWindow $window, array $data, array $deviceIds, ?int $userId): MaintenanceWindow
    {
        return DB::transaction(function () use ($window, $data, $deviceIds, $userId) {
            $window->fill($data);
            if (! $window->exists) {
                $window->forceFill(['status' => 'planned', 'created_by' => $userId]);
            }
            $window->save();

            // Perangkat = yang dipilih + seluruh perangkat aktif di site (bila site dipilih dan diminta)
            if (! empty($data['include_site_devices']) && $window->site_id) {
                $deviceIds = array_merge($deviceIds, DeviceDetail::active()->where('site_id', $window->site_id)->pluck('device_id')->all());
            }
            $window->devices()->sync(array_values(array_unique(array_map('intval', $deviceIds))));
            $this->syncSchedule($window->fresh('devices'));

            return $window;
        });
    }

    public function syncSchedule(MaintenanceWindow $window): void
    {
        $schedule = $window->alert_schedule_id ? AlertSchedule::find($window->alert_schedule_id) : null;

        if (in_array($window->status, ['cancelled', 'completed'], true) || $window->devices->isEmpty()) {
            if ($schedule && $window->status === 'cancelled') {
                $schedule->delete();
                $window->forceFill(['alert_schedule_id' => null])->saveQuietly();
            } elseif ($schedule && $window->status === 'completed' && $schedule->end > now()) {
                // selesai lebih cepat: akhiri penekanan alert sekarang
                $schedule->end = now();
                $schedule->save();
            }

            return;
        }

        $schedule ??= new AlertSchedule();
        $schedule->fill([
            'title' => mb_substr('ANTAREJA: ' . $window->title, 0, 255),
            'notes' => "Dibuat otomatis dari maintenance ANTAREJA #{$window->id}. Ubah dari menu Maintenance ANTAREJA.",
            'recurring' => 0,
            'behavior' => MaintenanceBehavior::MuteAlerts->value,
        ]);
        $schedule->start = $window->starts_at;
        $schedule->end = $window->ends_at;
        $schedule->save();
        $schedule->devices()->sync($window->devices->pluck('device_id')->all());

        if ($window->alert_schedule_id !== $schedule->schedule_id) {
            $window->forceFill(['alert_schedule_id' => $schedule->schedule_id])->saveQuietly();
        }
    }

    public function complete(MaintenanceWindow $window, string $workDone, string $result): void
    {
        $window->forceFill(['status' => 'completed', 'work_done' => $workDone, 'result' => $result, 'completed_at' => now()])->save();
        $this->syncSchedule($window->fresh('devices'));
    }

    public function cancel(MaintenanceWindow $window): void
    {
        $window->forceFill(['status' => 'cancelled'])->save();
        $this->syncSchedule($window->fresh('devices'));
    }

    /** Status planned → in_progress otomatis saat waktunya tiba (dipanggil scheduler). */
    public function tick(): int
    {
        return MaintenanceWindow::where('status', 'planned')->where('starts_at', '<=', now())->where('ends_at', '>', now())
            ->update(['status' => 'in_progress', 'updated_at' => now()]);
    }
}
