<?php

namespace Antareja\Services;

use Antareja\Models\QualitySample;
use Antareja\Models\QualityTarget;
use Antareja\Models\Trigger;
use Antareja\Models\TriggerSample;
use App\Models\Device;
use App\Models\Port;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Evaluasi trigger expression setiap menit (dipanggil antareja:problems sebelum Problem engine).
 *
 * Nilai item diambil dari data yang sudah dikumpulkan poller / Quality Probe — tidak ada polling tambahan.
 * Untuk sumber selain Quality Probe, nilai saat ini dicatat ke ant_trigger_samples agar fungsi
 * avg/min/max atas jendela waktu bisa dihitung (disimpan 8 hari).
 */
class TriggerEngine
{
    public const RETENTION_DAYS = 8;

    /**
     * @return array{evaluated:int, problem:int, unknown:int}
     */
    public function evaluate(?Collection $triggers = null): array
    {
        $now = now();
        $stats = ['evaluated' => 0, 'problem' => 0, 'unknown' => 0];

        foreach ($triggers ?? Trigger::where('enabled', true)->get() as $t) {
            $value = $this->value($t, $now);
            $state = 'unknown';
            if ($value !== null) {
                $state = Trigger::compare($value, $t->operator, $t->threshold) ? 'problem' : 'ok';
                // Histeresis: problem baru pulih bila kondisi dengan ambang pemulihan juga sudah tidak terpenuhi
                if ($state === 'ok' && $t->state === 'problem' && $t->recovery_threshold !== null
                    && Trigger::compare($value, $t->operator, $t->recovery_threshold)) {
                    $state = 'problem';
                }
            }

            $update = ['last_value' => $value, 'last_evaluated_at' => $now, 'state' => $state];
            if ($state !== $t->state) {
                $update['state_changed_at'] = $now;
            }
            // Lewat query builder agar evaluasi rutin tidak membanjiri audit log
            Trigger::whereKey($t->id)->toBase()->update($update);
            $t->forceFill($update)->syncOriginal();

            $stats['evaluated']++;
            if ($state !== 'ok') {
                $stats[$state]++;
            }
        }

        if ((int) $now->format('i') === 7) {
            TriggerSample::where('measured_at', '<', $now->copy()->subDays(self::RETENTION_DAYS))->delete();
        }

        return $stats;
    }

    /** Nilai fungsi trigger saat ini (null = tidak ada data). */
    public function value(Trigger $t, $now = null): ?float
    {
        $now ??= now();
        $since = $now->copy()->subMinutes(max(1, $t->window_minutes));

        if ($t->source === 'quality') {
            $q = QualitySample::where('target_id', $t->ref_id);
            if ($t->function === 'last') {
                $v = $q->where('measured_at', '>=', $now->copy()->subMinutes(15))->orderByDesc('measured_at')->value($t->metric);
                return $v === null ? null : (float) $v;
            }
            $values = $q->where('measured_at', '>=', $since)->whereNotNull($t->metric)->pluck($t->metric)->map(fn ($v) => (float) $v);

            return $this->aggregate($t->function, $values);
        }

        $current = $this->current($t);
        if ($current !== null) {
            TriggerSample::create(['trigger_id' => $t->id, 'measured_at' => $now, 'value' => $current]);
        }
        if ($t->function === 'last') {
            return $current;
        }
        $values = TriggerSample::where('trigger_id', $t->id)->where('measured_at', '>=', $since)->pluck('value')->map(fn ($v) => (float) $v);

        return $this->aggregate($t->function, $values);
    }

    private function aggregate(string $fn, Collection $values): ?float
    {
        if ($values->isEmpty()) {
            return null;
        }

        return match ($fn) {
            'min' => (float) $values->min(),
            'max' => (float) $values->max(),
            default => round((float) $values->avg(), 4),
        };
    }

    /** Nilai mentah item saat ini dari tabel poller. */
    public function current(Trigger $t): ?float
    {
        switch ($t->source) {
            case 'port':
                $p = Port::where('port_id', $t->ref_id)->where('deleted', 0)->first();
                if (! $p) {
                    return null;
                }
                $speed = (float) ($p->ifSpeed ?: 0);

                return match ($t->metric) {
                    'in_util_pct' => $speed > 0 && $p->ifInOctets_rate !== null ? round($p->ifInOctets_rate * 8 / $speed * 100, 3) : null,
                    'out_util_pct' => $speed > 0 && $p->ifOutOctets_rate !== null ? round($p->ifOutOctets_rate * 8 / $speed * 100, 3) : null,
                    'in_mbps' => $p->ifInOctets_rate !== null ? round($p->ifInOctets_rate * 8 / 1e6, 3) : null,
                    'out_mbps' => $p->ifOutOctets_rate !== null ? round($p->ifOutOctets_rate * 8 / 1e6, 3) : null,
                    'in_errors' => $p->ifInErrors_rate !== null ? (float) $p->ifInErrors_rate : null,
                    'out_errors' => $p->ifOutErrors_rate !== null ? (float) $p->ifOutErrors_rate : null,
                    default => null,
                };
            case 'sensor':
                $v = DB::table('sensors')->where('sensor_id', $t->ref_id)->value('sensor_current');

                return $v === null ? null : (float) $v;
            case 'wireless':
                $v = DB::table('wireless_sensors')->where('sensor_id', $t->ref_id)->value('sensor_current');

                return $v === null ? null : (float) $v;
            case 'device':
                $d = Device::find($t->ref_id);
                if (! $d || $d->disabled) {
                    return null;
                }

                return match ($t->metric) {
                    'poll_s' => $d->last_polled_timetaken !== null ? (float) $d->last_polled_timetaken : null,
                    'uptime_s' => $d->status && $d->uptime ? (float) $d->uptime : null,
                    default => null,
                };
        }

        return null;
    }

    /** Label item yang dapat dibaca, mis. "sw-core-pit-a · Gi0/1". */
    public static function itemLabel(Trigger $t): string
    {
        switch ($t->source) {
            case 'quality':
                return QualityTarget::find($t->ref_id)?->name ?? "target #{$t->ref_id}";
            case 'port':
                $p = Port::with('device')->find($t->ref_id);

                return $p ? ($p->device?->displayName() . ' · ' . $p->getShortLabel()) : "port #{$t->ref_id}";
            case 'sensor':
            case 'wireless':
                $s = DB::table($t->source === 'sensor' ? 'sensors' : 'wireless_sensors')->where('sensor_id', $t->ref_id)->first();
                $d = $s ? Device::find($s->device_id) : null;

                return $s ? (($d?->displayName() ?? '') . ' · ' . $s->sensor_descr) : "sensor #{$t->ref_id}";
            case 'device':
                return Device::find($t->ref_id)?->displayName() ?? "device #{$t->ref_id}";
        }

        return '#' . $t->ref_id;
    }

    /** Satuan item sensor (karena metrik "value" bergantung kelas sensor). */
    public static function sensorUnit(Trigger $t): string
    {
        if (! in_array($t->source, ['sensor', 'wireless'], true)) {
            return $t->unit();
        }
        $class = DB::table($t->source === 'sensor' ? 'sensors' : 'wireless_sensors')->where('sensor_id', $t->ref_id)->value('sensor_class');

        return [
            'temperature' => '°C', 'voltage' => 'V', 'current' => 'A', 'power' => 'W', 'fanspeed' => 'rpm', 'humidity' => '%',
            'dbm' => 'dBm', 'signal' => 'dBm', 'rssi' => 'dBm', 'snr' => 'dB', 'noise-floor' => 'dBm', 'ccq' => '%',
            'clients' => 'client', 'frequency' => 'MHz', 'rate' => 'bps', 'distance' => 'km', 'load' => '%', 'percent' => '%',
            'quality' => '%', 'utilization' => '%', 'runtime' => 'menit', 'charge' => '%',
        ][$class] ?? '';
    }

    /** Device pemilik item (untuk problem & korelasi). */
    public static function deviceFor(string $source, int $ref): ?int
    {
        return match ($source) {
            'quality' => QualityTarget::find($ref)?->device_id,
            'port' => Port::where('port_id', $ref)->value('device_id'),
            'sensor' => DB::table('sensors')->where('sensor_id', $ref)->value('device_id'),
            'wireless' => DB::table('wireless_sensors')->where('sensor_id', $ref)->value('device_id'),
            'device' => $ref,
            default => null,
        };
    }
}
