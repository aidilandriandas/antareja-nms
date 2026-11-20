<?php

namespace Antareja\Services;

use Antareja\Models\MaintenanceWindow;
use Antareja\Models\Service;
use Antareja\Models\SlaMonthly;
use Antareja\Support\Intervals;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Perhitungan availability / SLA untuk satu periode.
 *
 * - Downtime perangkat = outage poller LibreNMS (device_outages), outage yang masih terbuka dihitung sampai sekarang.
 * - Downtime di dalam jendela maintenance yang "dikecualikan dari SLA" tidak dihitung (dicatat sebagai excluded).
 * - Layanan: interval down anggota (perangkat & layanan anak) digabung menurut aturan calc (seri / redundan / minimal N up).
 * - Availability = (periode − downtime) ÷ periode. Periode berjalan dihitung sampai sekarang.
 */
class SlaCalculator
{
    public readonly int $from;

    public readonly int $to;

    /** @var array<int, array> perangkat → interval down mentah */
    private array $raw = [];

    /** @var array<int, array> perangkat → interval down setelah pengecualian maintenance */
    private array $effective = [];

    /** @var array<int, array{raw:array, eff:array}> */
    private array $serviceMemo = [];

    private ?\Illuminate\Support\Collection $services = null;

    public function __construct(CarbonInterface $from, CarbonInterface $to)
    {
        $this->from = $from->getTimestamp();
        $this->to = min($to->getTimestamp(), time());
        $this->load();
    }

    public static function forMonth(CarbonInterface $month): self
    {
        $m = CarbonImmutable::parse($month)->startOfMonth();

        return new self($m, $m->addMonth());
    }

    public function periodSeconds(): int
    {
        return max(0, $this->to - $this->from);
    }

    private function load(): void
    {
        if ($this->periodSeconds() === 0) {
            return;
        }
        DB::table('device_outages')->where('going_down', '<', $this->to)
            ->where(fn ($q) => $q->whereNull('up_again')->orWhere('up_again', '>', $this->from))
            ->orderBy('going_down')->get(['device_id', 'going_down', 'up_again'])
            ->each(function ($o): void {
                $this->raw[$o->device_id][] = [(int) $o->going_down, $o->up_again === null ? time() : (int) $o->up_again];
            });

        $maint = [];
        MaintenanceWindow::with('devices:device_id')->where('exclude_from_sla', true)->where('status', '!=', 'cancelled')
            ->where('starts_at', '<', CarbonImmutable::createFromTimestamp($this->to))
            ->where('ends_at', '>', CarbonImmutable::createFromTimestamp($this->from))
            ->get()->each(function (MaintenanceWindow $w) use (&$maint): void {
                $end = $w->ends_at;
                if ($w->status === 'completed' && $w->completed_at && $w->completed_at->lt($end)) {
                    $end = $w->completed_at;
                }
                foreach ($w->devices as $d) {
                    $maint[$d->device_id][] = [$w->starts_at->getTimestamp(), $end->getTimestamp()];
                }
            });

        foreach ($this->raw as $id => $list) {
            $this->raw[$id] = Intervals::clip($list, $this->from, $this->to);
            $this->effective[$id] = Intervals::subtract($this->raw[$id], $maint[$id] ?? []);
        }
    }

    /** @return array{raw:array, eff:array} */
    public function deviceIntervals(int $deviceId): array
    {
        return ['raw' => $this->raw[$deviceId] ?? [], 'eff' => $this->effective[$deviceId] ?? []];
    }

    /**
     * @param  int|null  $since  waktu perangkat mulai dipantau (devices.inserted); periode sebelum itu tidak dihitung
     */
    public function device(int $deviceId, ?int $since = null): array
    {
        $i = $this->deviceIntervals($deviceId);
        $start = max($this->from, $since ?? 0);
        if ($start > $this->from) {
            $i = ['raw' => Intervals::clip($i['raw'], $start, $this->to), 'eff' => Intervals::clip($i['eff'], $start, $this->to)];
        }

        return $this->stats($i['eff'], Intervals::total($i['raw']) - Intervals::total($i['eff']), max(0, $this->to - $start));
    }

    /** @return array{raw:array, eff:array} */
    public function serviceIntervals(Service $service, int $depth = 0): array
    {
        if (isset($this->serviceMemo[$service->id])) {
            return $this->serviceMemo[$service->id];
        }
        $this->services ??= Service::with('devices:device_id')->where('active', true)->get()->keyBy('id');
        $node = $this->services[$service->id] ?? $service->loadMissing('devices:device_id');

        $raw = $eff = [];
        foreach ($node->devices as $d) {
            $i = $this->deviceIntervals($d->device_id);
            $raw[] = $i['raw'];
            $eff[] = $i['eff'];
        }
        if ($depth < 10) {
            foreach ($this->services->where('parent_id', $service->id) as $child) {
                $i = $this->serviceIntervals($child, $depth + 1);
                $raw[] = $i['raw'];
                $eff[] = $i['eff'];
            }
        }

        $n = count($raw);
        $required = match ($node->calc) {
            'any' => $n,
            'min' => $n - (int) $node->min_up + 1,
            default => 1,
        };

        return $this->serviceMemo[$service->id] = [
            'raw' => Intervals::combine($raw, $required, $this->from, $this->to),
            'eff' => Intervals::combine($eff, $required, $this->from, $this->to),
        ];
    }

    public function service(Service $service): array
    {
        $i = $this->serviceIntervals($service);
        $stats = $this->stats($i['eff'], Intervals::total($i['raw']) - Intervals::total($i['eff']));
        $stats['target'] = (float) $service->sla_target;
        $stats['met'] = $stats['availability'] === null ? null : $stats['availability'] >= $service->sla_target;

        return $stats;
    }

    /**
     * @return array{period:int, downtime:int, excluded:int, outages:int, availability:?float, mttr:?int, mtbf:?int, intervals:array}
     */
    public function stats(array $down, int $excluded = 0, ?int $period = null): array
    {
        $period ??= $this->periodSeconds();
        $downtime = Intervals::total($down);
        $outages = count($down);

        return [
            'period' => $period,
            'downtime' => $downtime,
            'excluded' => max(0, $excluded),
            'outages' => $outages,
            'availability' => $period > 0 ? round(($period - $downtime) / $period * 100, 5) : null,
            'mttr' => $outages > 0 ? intdiv($downtime, $outages) : null,
            'mtbf' => $outages > 0 ? intdiv($period - $downtime, $outages) : null,
            'intervals' => $down,
        ];
    }

    /**
     * SLA bulanan semua layanan aktif. Bulan yang sudah lewat dibekukan di ant_sla_monthly
     * (dihitung sekali, lalu dibaca ulang) agar laporan tetap konsisten.
     *
     * @return array<int, array> service_id → stats
     */
    public static function monthlyForServices(CarbonInterface $month, bool $refresh = false): array
    {
        $m = CarbonImmutable::parse($month)->startOfMonth();
        $closed = $m->addMonth()->isPast();
        $services = Service::where('active', true)->get();
        $out = [];

        if ($closed && ! $refresh) {
            $stored = SlaMonthly::where('month', $m->toDateString())->get()->keyBy('service_id');
            if ($stored->count() >= $services->count() && $services->count() > 0) {
                foreach ($stored as $sid => $r) {
                    $out[$sid] = [
                        'period' => $r->period_seconds, 'downtime' => $r->downtime_seconds, 'excluded' => $r->excluded_seconds,
                        'outages' => $r->outages, 'availability' => $r->availability, 'target' => $r->sla_target, 'met' => $r->met,
                        'mttr' => $r->outages ? intdiv($r->downtime_seconds, $r->outages) : null,
                        'mtbf' => $r->outages ? intdiv($r->period_seconds - $r->downtime_seconds, $r->outages) : null,
                        'intervals' => [], 'frozen' => true,
                    ];
                }

                return $out;
            }
        }

        $calc = self::forMonth($m);
        foreach ($services as $s) {
            $out[$s->id] = $calc->service($s) + ['frozen' => false];
            if ($closed && $out[$s->id]['availability'] !== null) {
                SlaMonthly::updateOrCreate(['service_id' => $s->id, 'month' => $m->toDateString()], [
                    'period_seconds' => $out[$s->id]['period'], 'downtime_seconds' => $out[$s->id]['downtime'],
                    'excluded_seconds' => $out[$s->id]['excluded'], 'outages' => $out[$s->id]['outages'],
                    'availability' => $out[$s->id]['availability'], 'sla_target' => $s->sla_target,
                    'met' => $out[$s->id]['met'], 'computed_at' => now(),
                ]);
            }
        }

        return $out;
    }

    /**
     * Status layanan saat ini dari status perangkat: up, degraded, down, maintenance, empty.
     *
     * @param  array<int, string>  $deviceStates  device_id → up|down|maintenance
     */
    public static function liveState(Service $service, array $deviceStates, \Illuminate\Support\Collection $all, int $depth = 0): string
    {
        $states = [];
        foreach ($service->devices as $d) {
            $states[] = $deviceStates[$d->device_id] ?? 'up';
        }
        if ($depth < 10) {
            foreach ($all->where('parent_id', $service->id) as $child) {
                $s = self::liveState($child, $deviceStates, $all, $depth + 1);
                if ($s !== 'empty') {
                    $states[] = $s === 'degraded' ? 'up' : $s;
                }
            }
        }
        $n = count($states);
        if ($n === 0) {
            return 'empty';
        }
        $down = count(array_filter($states, fn ($s) => $s === 'down'));
        $maint = count(array_filter($states, fn ($s) => $s === 'maintenance'));
        if ($maint === $n) {
            return 'maintenance';
        }
        $required = match ($service->calc) {
            'any' => $n,
            'min' => $n - (int) $service->min_up + 1,
            default => 1,
        };

        return $down >= max(1, $required) ? 'down' : ($down > 0 ? 'degraded' : 'up');
    }

    /** @return array<int, string> */
    public static function deviceStates(): array
    {
        $out = [];
        \App\Models\Device::where('disabled', 0)->where('ignore', 0)->get(['device_id', 'status'])
            ->each(function ($d) use (&$out): void {
                // cek maintenance hanya untuk perangkat yang down (hemat query)
                $out[$d->device_id] = $d->status ? 'up' : ($d->isUnderMaintenance() ? 'maintenance' : 'down');
            });

        return $out;
    }

    public static function fmtDuration(?int $seconds): string
    {
        if ($seconds === null) {
            return '—';
        }
        if ($seconds < 60) {
            return $seconds . ' dtk';
        }
        $d = intdiv($seconds, 86400);
        $h = intdiv($seconds % 86400, 3600);
        $m = intdiv($seconds % 3600, 60);

        return trim(($d ? "{$d} hr " : '') . ($h ? "{$h} j " : '') . ($m && ! $d ? "{$m} m" : ''));
    }

    public static function fmtPct(?float $pct, int $dec = 3): string
    {
        return $pct === null ? '—' : number_format($pct, $dec, ',', '.') . '%';
    }
}
