<?php

namespace Antareja\Services;

use Antareja\Models\CapacityDaily;
use Antareja\Models\CapacityItem;
use Antareja\Models\Subnet;
use App\Models\Device;
use App\Models\Port;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Capacity planning.
 *
 * Item: port uplink (dari mapping terverifikasi + yang ditambah manual), storage perangkat (≥ 1 GiB),
 * dan pool IP (subnet IPv4). Data harian diambil dari histori RRD poller:
 *  - 7 hari terakhir: data 5 menit → rata-rata & persentil-95 harian (metode umum untuk kapasitas link);
 *  - lebih lama: RRA harian → rata-rata & maksimum harian.
 * Prakiraan: regresi linear atas puncak harian 60 hari terakhir (minimal 7 hari data).
 */
class CapacityService
{
    private const RECENT_DAYS = 7;

    private const FORECAST_WINDOW = 60;

    private const MIN_POINTS = 7;

    public function __construct(private readonly RrdReader $rrd, private readonly IpamService $ipam)
    {
    }

    /** Tambahkan item otomatis yang belum ada. @return int jumlah item baru */
    public function syncItems(): int
    {
        $new = 0;

        // Port uplink dari mapping terverifikasi
        $portIds = DB::table('ant_device_links')->where('status', 'verified')->whereNotNull('a_port_id')->pluck('a_port_id')
            ->merge(DB::table('ant_device_links')->where('status', 'verified')->whereNotNull('b_port_id')->pluck('b_port_id'))->unique();
        foreach (Port::with('device')->whereIn('port_id', $portIds)->where('deleted', 0)->get() as $p) {
            $new += $this->ensure('port', $p->port_id, $p->device_id, ($p->device?->displayName() ?? '') . ' · ' . $p->getShortLabel(), $this->portSpeed($p));
        }

        // Storage ≥ 1 GiB, tanpa mount sistem/bind file
        DB::table('storage')->where('storage_size', '>=', 1024 ** 3)->get()->each(function ($s) use (&$new): void {
            if (preg_match('#^/(proc|sys|dev|run|etc)(/|$)#', (string) $s->storage_descr)) {
                return;
            }
            $dev = Device::find($s->device_id);
            $new += $this->ensure('storage', $s->storage_id, $s->device_id, ($dev?->displayName() ?? '') . ' · ' . $s->storage_descr, (float) $s->storage_size);
        });

        // Pool IP
        foreach (Subnet::where('family', 4)->get() as $s) {
            $new += $this->ensure('subnet', $s->id, null, "{$s->cidr} · {$s->name}", (float) $s->usableCount());
        }

        return $new;
    }

    public function addPort(int $portId): CapacityItem
    {
        $p = Port::with('device')->findOrFail($portId);
        $this->ensure('port', $p->port_id, $p->device_id, ($p->device?->displayName() ?? '') . ' · ' . $p->getShortLabel(), $this->portSpeed($p), false);

        return CapacityItem::where('type', 'port')->where('ref_id', $portId)->first();
    }

    private function ensure(string $type, int $ref, ?int $deviceId, string $label, ?float $capacity, bool $auto = true): int
    {
        $item = CapacityItem::firstOrNew(['type' => $type, 'ref_id' => $ref]);
        $isNew = ! $item->exists;
        $item->fill(['device_id' => $deviceId, 'label' => mb_substr($label, 0, 255), 'capacity' => $capacity]);
        if ($isNew) {
            $item->fill(['auto' => $auto, 'enabled' => true] + ($type === 'storage' ? ['warn_pct' => 80, 'crit_pct' => 90] : []));
        }
        $item->save();

        return $isNew ? 1 : 0;
    }

    private function portSpeed(Port $p): ?float
    {
        $speed = (float) ($p->ifSpeed ?: 0);
        if ($p->ifHighSpeed && $p->ifHighSpeed * 1e6 > $speed) {
            $speed = $p->ifHighSpeed * 1e6;
        }

        return $speed > 0 ? $speed : null;
    }

    /** Hitung data harian (backfill bila belum ada) lalu prakiraan, untuk semua item aktif. */
    public function computeAll(int $backfillDays = 90): array
    {
        $stats = ['items' => 0, 'days' => 0];
        foreach (CapacityItem::where('enabled', true)->get() as $item) {
            $stats['days'] += $this->computeDaily($item, $backfillDays);
            $this->forecast($item);
            $stats['items']++;
        }

        return $stats;
    }

    public function computeDaily(CapacityItem $item, int $backfillDays = 90): int
    {
        $tz = config('app.timezone');
        $today = CarbonImmutable::now($tz)->startOfDay();
        $last = CapacityDaily::where('item_id', $item->id)->max('day');
        // Hitung ulang 2 hari terakhir (hari ini parsial & kemarin), backfill bila belum pernah
        $from = $last ? CarbonImmutable::parse($last, $tz)->subDay()->startOfDay() : $today->subDays($backfillDays);
        $now = time();

        $rows = match ($item->type) {
            'port' => $this->portDays($item, $from, $now),
            'storage' => $this->storageDays($item, $from, $now),
            'subnet' => $this->subnetToday($item, $today),
            default => [],
        };
        foreach ($rows as $day => $r) {
            CapacityDaily::updateOrInsert(['item_id' => $item->id, 'day' => $day], $r);
        }

        return count($rows);
    }

    private function portDays(CapacityItem $item, CarbonImmutable $from, int $now): array
    {
        $port = Port::with('device')->find($item->ref_id);
        $speed = $item->capacity;
        if (! $port || ! $port->device || ! $speed) {
            return [];
        }
        $host = $port->device->hostname;
        $name = ['port-id' . $port->port_id];
        $util = fn (?float $in, ?float $out) => $in === null && $out === null ? null : max($in ?? 0, $out ?? 0) * 8 / $speed * 100;

        return $this->days($host, $name, $from, $now,
            fn (array $r) => ['pct' => $util($r['INOCTETS'] ?? null, $r['OUTOCTETS'] ?? null),
                'in' => isset($r['INOCTETS']) ? $r['INOCTETS'] * 8 : null, 'out' => isset($r['OUTOCTETS']) ? $r['OUTOCTETS'] * 8 : null],
            usePercentile: true);
    }

    private function storageDays(CapacityItem $item, CarbonImmutable $from, int $now): array
    {
        $s = DB::table('storage')->where('storage_id', $item->ref_id)->first();
        $dev = $s ? Device::find($s->device_id) : null;
        if (! $s || ! $dev) {
            return [];
        }
        $pct = fn (array $r) => isset($r['used'], $r['free']) && ($r['used'] + $r['free']) > 0 ? $r['used'] / ($r['used'] + $r['free']) * 100 : null;

        return $this->days($dev->hostname, ['storage', $s->type, $s->storage_descr], $from, $now,
            fn (array $r) => ['pct' => $pct($r), 'in' => null, 'out' => null], usePercentile: false);
    }

    /**
     * Gabungkan data 5 menit (7 hari terakhir) dan harian (lebih lama) menjadi baris per hari.
     *
     * @return array<string, array>
     */
    private function days(string $host, array $name, CarbonImmutable $from, int $now, \Closure $map, bool $usePercentile): array
    {
        $tz = config('app.timezone');
        $recentStart = max($from->getTimestamp(), CarbonImmutable::now($tz)->subDays(self::RECENT_DAYS)->startOfDay()->getTimestamp());
        $out = [];

        // Histori lama: RRA harian (AVERAGE & MAX)
        if ($from->getTimestamp() < $recentStart) {
            // rrdtool memilih RRA terbaik yang mencakup rentang; hasilnya bisa per 2 jam, jadi digabung per hari
            $avg = $this->rrd->fetch($host, $name, 'AVERAGE', 86400, $from->getTimestamp(), $recentStart);
            $max = $this->rrd->fetch($host, $name, 'MAX', 86400, $from->getTimestamp(), $recentStart);
            $byDay = [];
            foreach ($avg as $ts => $r) {
                $a = $map($r);
                if ($a['pct'] === null) {
                    continue;
                }
                $m = isset($max[$ts]) ? $map($max[$ts]) : null;
                // Titik RRA menandai akhir interval → hari interval itu
                $day = CarbonImmutable::createFromTimestamp($ts - 1, $tz)->toDateString();
                $byDay[$day]['avg'][] = $a['pct'];
                $byDay[$day]['peak'][] = $m['pct'] ?? $a['pct'];
                $byDay[$day]['in'][] = $a['in'];
                $byDay[$day]['out'][] = $a['out'];
            }
            $mean = fn (array $v) => ($v = array_filter($v, fn ($x) => $x !== null)) ? array_sum($v) / count($v) : null;
            foreach ($byDay as $day => $d) {
                $out[$day] = ['avg_pct' => round($mean($d['avg']), 3), 'peak_pct' => round(max($d['peak']), 3),
                    'avg_in' => $mean($d['in']), 'avg_out' => $mean($d['out']), 'peak_method' => 'max'];
            }
        }

        // 7 hari terakhir: data 5 menit
        $points = [];
        foreach ($this->rrd->fetch($host, $name, 'AVERAGE', 300, $recentStart, $now) as $ts => $r) {
            $v = $map($r);
            if ($v['pct'] !== null) {
                $points[CarbonImmutable::createFromTimestamp($ts - 1, $tz)->toDateString()][] = $v;
            }
        }
        foreach ($points as $day => $vals) {
            if (count($vals) < 12) {
                continue; // kurang dari 1 jam data
            }
            $pcts = array_column($vals, 'pct');
            sort($pcts);
            $peak = $usePercentile ? $pcts[(int) floor(0.95 * (count($pcts) - 1))] : end($pcts);
            $ins = array_filter(array_column($vals, 'in'), fn ($x) => $x !== null);
            $outs = array_filter(array_column($vals, 'out'), fn ($x) => $x !== null);
            $out[$day] = ['avg_pct' => round(array_sum($pcts) / count($pcts), 3), 'peak_pct' => round($peak, 3),
                'avg_in' => $ins ? array_sum($ins) / count($ins) : null, 'avg_out' => $outs ? array_sum($outs) / count($outs) : null,
                'peak_method' => $usePercentile ? 'p95' : 'max'];
        }
        ksort($out);

        return $out;
    }

    private function subnetToday(CapacityItem $item, CarbonImmutable $today): array
    {
        $s = Subnet::find($item->ref_id);
        if (! $s) {
            return [];
        }
        $u = (float) $this->ipam->stats($s)['utilization'];

        return [$today->toDateString() => ['avg_pct' => $u, 'peak_pct' => $u, 'avg_in' => null, 'avg_out' => null, 'peak_method' => 'snap']];
    }

    /** Regresi linear atas puncak harian → tren & tanggal perkiraan menyentuh ambang. */
    public function forecast(CapacityItem $item): void
    {
        $tz = config('app.timezone');
        $rows = CapacityDaily::where('item_id', $item->id)->where('day', '>=', CarbonImmutable::now($tz)->subDays(self::FORECAST_WINDOW)->toDateString())
            ->orderBy('day')->get(['day', 'peak_pct']);
        $update = ['current_pct' => $rows->last()?->peak_pct, 'days_of_data' => $rows->count(), 'computed_at' => now(),
            'trend_pct_per_day' => null, 'forecast_warn_on' => null, 'forecast_crit_on' => null];

        if ($rows->count() >= self::MIN_POINTS) {
            $t0 = $rows->first()->day->getTimestamp();
            $xs = $rows->map(fn ($r) => ($r->day->getTimestamp() - $t0) / 86400)->all();
            $ys = $rows->pluck('peak_pct')->map(fn ($v) => (float) $v)->all();
            [$a, $b] = self::regression($xs, $ys);
            $update['trend_pct_per_day'] = round($b, 4);
            $lastX = end($xs);
            $fitNow = $a + $b * $lastX;
            foreach (['warn' => $item->warn_pct, 'crit' => $item->crit_pct] as $k => $threshold) {
                if ($fitNow >= $threshold || $update['current_pct'] >= $threshold) {
                    $update["forecast_{$k}_on"] = CarbonImmutable::now($tz)->toDateString();
                } elseif ($b > 0.0001) {
                    $days = ($threshold - $fitNow) / $b;
                    if ($days <= 365) {
                        $update["forecast_{$k}_on"] = CarbonImmutable::now($tz)->addDays((int) ceil($days))->toDateString();
                    }
                }
            }
        }
        CapacityItem::whereKey($item->id)->update($update);
        $item->forceFill($update);
    }

    /** @return array{0: float, 1: float} intercept, slope */
    public static function regression(array $xs, array $ys): array
    {
        $n = count($xs);
        $mx = array_sum($xs) / $n;
        $my = array_sum($ys) / $n;
        $num = $den = 0.0;
        foreach ($xs as $i => $x) {
            $num += ($x - $mx) * ($ys[$i] - $my);
            $den += ($x - $mx) ** 2;
        }
        $b = $den > 0 ? $num / $den : 0.0;

        return [$my - $b * $mx, $b];
    }
}
