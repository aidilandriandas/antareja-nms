<?php

namespace Antareja\Services;

use App\Models\Device;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Ping cepat tiap menit ke semua perangkat aktif (fping, ICMP read-only — sama seperti LibreNMS). Tidak mengubah status
 * perangkat di LibreNMS; dipakai Layar NOC untuk:
 *  - deteksi "tidak menjawab ping" ±1–2 menit (polling LibreNMS hanya tiap 5 menit),
 *  - panel latensi & loss tertinggi + sparkline 1 jam,
 *  - linimasa kejadian: down/up beserta lamanya, flapping, latensi tinggi; ditambah reboot & status dari eventlog LibreNMS.
 */
class FastPing
{
    public const DOWN_AFTER = 2;         // gagal berturut-turut (menit) sebelum dianggap down
    public const HIGH_RTT_MS = 150;      // latensi tinggi
    public const FLAP_COUNT = 3;         // ≥ 3 kali down dalam
    public const FLAP_WINDOW_MIN = 15;   // … 15 menit = flapping

    public function __construct(private readonly QualityProbe $probe)
    {
    }

    /** @return array{devices:int, down:int, events:int} */
    public function run(): array
    {
        $devices = Device::where('disabled', 0)->where('ignore', 0)
            ->get(['device_id', 'hostname', 'ip', 'overwrite_ip', 'display', 'sysName']);
        $targets = [];
        foreach ($devices as $d) {
            $host = $d->overwrite_ip ?: ($d->ip ?: $d->hostname);
            if ($host) {
                $targets[$host][] = $d;
            }
        }
        $now = now()->startOfMinute();
        $events = 0;
        $rtts = [];
        try {
            foreach (array_chunk(array_keys($targets), 200) as $chunk) {
                $rtts += $this->probe->ping($chunk, 3);
            }
        } catch (\Throwable $e) {
            Log::warning('ANTAREJA fast-ping: ' . $e->getMessage());

            return ['devices' => 0, 'down' => 0, 'events' => 0]; // fping gagal: jangan tandai semua perangkat down
        }

        $state = DB::table('ant_device_pings')->get()->keyBy('device_id');
        $samples = [];
        $down = 0;
        foreach ($targets as $host => $list) {
            $values = $rtts[$host] ?? [null, null, null];
            $ok = array_values(array_filter($values, fn ($v) => $v !== null));
            $loss = (int) round((1 - count($ok) / max(1, count($values))) * 100);
            $rtt = $ok ? round(array_sum($ok) / count($ok), 2) : null;
            foreach ($list as $d) {
                $prev = $state[$d->device_id] ?? null;
                $streak = $rtt === null ? ($prev->fail_streak ?? 0) + 1 : 0;
                $prevState = $prev->state ?? 'up';
                $newState = $streak >= self::DOWN_AFTER ? 'down' : ($rtt !== null ? 'up' : $prevState);
                $name = $d->display ?: ($d->sysName ?: $d->hostname);
                $row = [
                    'device_id' => $d->device_id, 'state' => $newState, 'rtt_ms' => $rtt, 'loss' => $loss, 'fail_streak' => $streak,
                    'last_ok_at' => $rtt !== null ? $now : ($prev->last_ok_at ?? null), 'last_checked_at' => $now,
                    'state_changed_at' => $newState !== $prevState ? $now : ($prev->state_changed_at ?? $now),
                ];
                DB::table('ant_device_pings')->upsert([$row], ['device_id'], array_keys($row));
                $samples[] = ['device_id' => $d->device_id, 'at' => $now, 'rtt_ms' => $rtt, 'loss' => $loss];
                $down += $newState === 'down' ? 1 : 0;

                if ($newState !== $prevState) {
                    if ($newState === 'down') {
                        $events += $this->event($d->device_id, 'down', "{$name} tidak menjawab ping ({$streak} menit)", null, $now);
                        $recent = DB::table('ant_noc_events')->where('device_id', $d->device_id)->where('kind', 'down')
                            ->where('at', '>=', $now->copy()->subMinutes(self::FLAP_WINDOW_MIN))->count();
                        if ($recent >= self::FLAP_COUNT) {
                            $events += $this->event($d->device_id, 'flapping', "{$name} naik-turun {$recent}× dalam " . self::FLAP_WINDOW_MIN . ' menit', null, $now);
                        }
                    } else {
                        $dur = $prev && $prev->state_changed_at ? max(0, $now->getTimestamp() - strtotime($prev->state_changed_at)) : null;
                        $events += $this->event($d->device_id, 'up', "{$name} kembali menjawab ping" . ($dur ? ' setelah ' . $this->human($dur) : ''), $dur, $now);
                    }
                } elseif ($rtt !== null && $rtt >= self::HIGH_RTT_MS && (($prev->rtt_ms ?? 0) < self::HIGH_RTT_MS)) {
                    $events += $this->event($d->device_id, 'latency', "{$name} latensi tinggi {$rtt} ms", null, $now);
                }
            }
        }
        foreach (array_chunk($samples, 500) as $chunk) {
            DB::table('ant_device_ping_samples')->insert($chunk);
        }
        $events += $this->importEventlog();

        return ['devices' => count($samples), 'down' => $down, 'events' => $events];
    }

    /** Reboot & perubahan status dari eventlog LibreNMS (sekali per baris, tanpa dobel). */
    public function importEventlog(): int
    {
        $rows = DB::table('eventlog')->whereIn('type', ['reboot', 'down', 'up'])
            ->where('datetime', '>=', now()->subHours(2))->orderBy('event_id')->limit(500)
            ->get(['event_id', 'device_id', 'datetime', 'message', 'type']);
        $n = 0;
        foreach ($rows as $r) {
            $kind = $r->type === 'reboot' ? 'reboot' : 'poll-' . $r->type;
            $n += DB::table('ant_noc_events')->insertOrIgnore([
                'device_id' => $r->device_id, 'kind' => mb_substr($kind, 0, 16), 'message' => mb_substr((string) $r->message, 0, 255),
                'source_ref' => 'eventlog:' . $r->event_id, 'at' => $r->datetime,
            ]);
        }

        return $n;
    }

    public function prune(): void
    {
        DB::table('ant_device_ping_samples')->where('at', '<', now()->subHours(24))->delete();
        DB::table('ant_noc_events')->where('at', '<', now()->subDays(30))->delete();
    }

    private function event(int $deviceId, string $kind, string $message, ?int $duration, $at): int
    {
        DB::table('ant_noc_events')->insert(['device_id' => $deviceId, 'kind' => $kind, 'message' => mb_substr($message, 0, 255),
            'duration_s' => $duration, 'at' => $at]);

        return 1;
    }

    private function human(int $s): string
    {
        return $s < 60 ? "{$s} detik" : ($s < 3600 ? floor($s / 60) . ' menit' : floor($s / 3600) . ' jam ' . floor($s % 3600 / 60) . ' menit');
    }
}
