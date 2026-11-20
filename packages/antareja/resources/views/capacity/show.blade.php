@extends('antareja::layouts.page')

@php
    $CI = \Antareja\Models\CapacityItem::class;
    $fmtPct = fn ($v) => $v === null ? '—' : number_format($v, 1, ',', '.') . '%';
    $bps = fn ($v) => $v === null ? '—' : \LibreNMS\Util\Number::formatSi($v, 1, 0, 'bps');
@endphp

@section('title', $item->label)
@section('breadcrumb')
    <li><a href="{{ route('antareja.capacity.index') }}">Capacity Planning</a></li>
    <li class="active">{{ $item->label }}</li>
@endsection
@section('heading', $item->label)
@section('subheading', $CI::TYPES[$item->type] . ' · kapasitas ' . $item->capacityLabel() . ($item->computed_at ? ' · dihitung ' . $item->computed_at->diffForHumans() : ''))

@section('page')
    <div class="tw:grid tw:grid-cols-2 tw:md:grid-cols-4 tw:gap-3 tw:mb-5">
        <x-antareja::stat label="Puncak harian terakhir" :value="$fmtPct($item->current_pct)" :tone="$item->tone() === 'unknown' ? 'neutral' : $item->tone()" />
        <x-antareja::stat label="Tren" :value="$item->trend_pct_per_day === null ? '—' : (($item->trend_pct_per_day >= 0 ? '+' : '') . number_format($item->trend_pct_per_day * 30, 1, ',', '.') . ' pt/bln')" hint="dari regresi 60 hari" />
        <x-antareja::stat :label="'Peringatan (' . $item->warn_pct . '%)'" :value="$item->forecast_warn_on ? ($item->forecast_warn_on->isFuture() ? $item->forecast_warn_on->format('d M Y') : 'sudah') : '—'" :tone="$item->forecast_warn_on ? 'warn' : 'neutral'" />
        <x-antareja::stat :label="'Kritis (' . $item->crit_pct . '%)'" :value="$item->forecast_crit_on ? ($item->forecast_crit_on->isFuture() ? $item->forecast_crit_on->format('d M Y') : 'sudah') : '—'" :tone="$item->forecast_crit_on ? 'crit' : 'neutral'" />
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="panel panel-default">
                <div class="panel-heading tw:flex tw:flex-wrap tw:items-center tw:justify-between tw:gap-2">
                    <strong>Utilisasi harian — 120 hari</strong>
                    <span class="tw:flex tw:gap-4 tw:text-xs" aria-hidden="true">
                        <span class="tw:flex tw:items-center tw:gap-1"><svg width="18" height="8"><line x1="0" y1="4" x2="18" y2="4" stroke="var(--ant-info)" stroke-width="2"/></svg> Puncak</span>
                        <span class="tw:flex tw:items-center tw:gap-1"><svg width="18" height="8"><line x1="0" y1="4" x2="18" y2="4" stroke="var(--ant-ink-muted)" stroke-width="2"/></svg> Rata-rata</span>
                        <span class="tw:flex tw:items-center tw:gap-1"><svg width="18" height="8"><line x1="0" y1="4" x2="18" y2="4" stroke="currentColor" stroke-width="1.5" stroke-dasharray="4 3"/></svg> Tren</span>
                    </span>
                </div>
                <div class="panel-body">
                    @if($rows->count() < 2)
                        <p class="ant-muted tw:m-0">Belum cukup data harian.</p>
                    @else
                        @php
                            $w = 800; $h = 240; $pl = 40; $pr = 12; $pt = 10; $pb = 24;
                            $t0 = $rows->first()->day->getTimestamp(); $t1 = max($rows->last()->day->getTimestamp(), $t0 + 86400);
                            $ymax = max(100, ceil(($rows->max('peak_pct') ?? 0) / 10) * 10);
                            $x = fn ($t) => $pl + ($t - $t0) / ($t1 - $t0) * ($w - $pl - $pr);
                            $y = fn ($v) => $pt + (1 - $v / $ymax) * ($h - $pt - $pb);
                            $line = fn ($col) => $rows->map(fn ($r) => round($x($r->day->getTimestamp()), 1) . ',' . round($y($r->$col), 1))->implode(' ');
                            $trend = null;
                            if ($item->trend_pct_per_day !== null) {
                                $recent = $rows->filter(fn ($r) => $r->day->gte(now()->subDays(60)->startOfDay()))->values();
                                if ($recent->count() >= 2) {
                                    $rx = $recent->map(fn ($r) => ($r->day->getTimestamp() - $recent->first()->day->getTimestamp()) / 86400)->all();
                                    [$ia, $ib] = \Antareja\Services\CapacityService::regression($rx, $recent->pluck('peak_pct')->all());
                                    $trend = [[$recent->first()->day->getTimestamp(), $ia], [$recent->last()->day->getTimestamp(), $ia + $ib * end($rx)]];
                                }
                            }
                        @endphp
                        <svg viewBox="0 0 {{ $w }} {{ $h }}" class="tw:w-full tw:h-auto" role="img" aria-label="Grafik utilisasi harian {{ $item->label }}">
                            @foreach([0, 25, 50, 75, 100] as $g)
                                @if($g <= $ymax)<line x1="{{ $pl }}" x2="{{ $w - $pr }}" y1="{{ $y($g) }}" y2="{{ $y($g) }}" stroke="currentColor" stroke-opacity=".08"/><text x="{{ $pl - 6 }}" y="{{ $y($g) + 4 }}" text-anchor="end" font-size="11" fill="currentColor" fill-opacity=".6">{{ $g }}%</text>@endif
                            @endforeach
                            <line x1="{{ $pl }}" x2="{{ $w - $pr }}" y1="{{ $y($item->warn_pct) }}" y2="{{ $y($item->warn_pct) }}" stroke="var(--ant-warn)" stroke-dasharray="6 4"/>
                            <text x="{{ $w - $pr }}" y="{{ $y($item->warn_pct) - 4 }}" text-anchor="end" font-size="11" fill="currentColor">peringatan {{ $item->warn_pct }}%</text>
                            <line x1="{{ $pl }}" x2="{{ $w - $pr }}" y1="{{ $y($item->crit_pct) }}" y2="{{ $y($item->crit_pct) }}" stroke="var(--ant-crit)" stroke-dasharray="6 4"/>
                            <text x="{{ $w - $pr }}" y="{{ $y($item->crit_pct) - 4 }}" text-anchor="end" font-size="11" fill="currentColor">kritis {{ $item->crit_pct }}%</text>
                            <polyline points="{{ $line('avg_pct') }}" fill="none" stroke="var(--ant-ink-muted)" stroke-width="2" stroke-linejoin="round"/>
                            <polyline points="{{ $line('peak_pct') }}" fill="none" stroke="var(--ant-info)" stroke-width="2" stroke-linejoin="round"/>
                            @if($trend)<line x1="{{ $x($trend[0][0]) }}" y1="{{ $y(max(0, $trend[0][1])) }}" x2="{{ $x($trend[1][0]) }}" y2="{{ $y(max(0, $trend[1][1])) }}" stroke="currentColor" stroke-width="1.5" stroke-dasharray="4 3"/>@endif
                            @foreach($rows as $r)
                                <circle cx="{{ $x($r->day->getTimestamp()) }}" cy="{{ $y($r->peak_pct) }}" r="6" fill="transparent"><title>{{ $r->day->format('d M Y') }}: puncak {{ $fmtPct($r->peak_pct) }}, rata-rata {{ $fmtPct($r->avg_pct) }}</title></circle>
                            @endforeach
                            <text x="{{ $pl }}" y="{{ $h - 6 }}" font-size="11" fill="currentColor" fill-opacity=".6">{{ $rows->first()->day->format('d M') }}</text>
                            <text x="{{ $w - $pr }}" y="{{ $h - 6 }}" text-anchor="end" font-size="11" fill="currentColor" fill-opacity=".6">{{ $rows->last()->day->format('d M Y') }}</text>
                        </svg>
                    @endif
                </div>
            </div>
            <details class="tw:mb-5">
                <summary class="tw:cursor-pointer tw:text-sm ant-muted">Tabel harian</summary>
                <div class="table-responsive tw:max-h-96 tw:overflow-y-auto">
                    <table class="table table-condensed tw:mt-2">
                        <thead><tr><th>Hari</th><th class="tw:text-right">Rata-rata</th><th class="tw:text-right">Puncak</th>@if($item->type === 'port')<th class="tw:text-right">Masuk</th><th class="tw:text-right">Keluar</th>@endif<th>Metode</th></tr></thead>
                        <tbody>@foreach($rows->reverse() as $r)<tr><td>{{ $r->day->format('d M Y') }}</td><td class="tw:text-right ant-data">{{ $fmtPct($r->avg_pct) }}</td><td class="tw:text-right ant-data">{{ $fmtPct($r->peak_pct) }}</td>@if($item->type === 'port')<td class="tw:text-right ant-data">{{ $bps($r->avg_in) }}</td><td class="tw:text-right ant-data">{{ $bps($r->avg_out) }}</td>@endif<td class="tw:text-xs">{{ ['p95' => 'p95 5 menit', 'max' => 'maks harian', 'snap' => 'snapshot'][$r->peak_method] ?? $r->peak_method }}</td></tr>@endforeach</tbody>
                    </table>
                </div>
            </details>
        </div>
        <div class="col-lg-4">
            @if($item->device)
                <div class="panel panel-default"><div class="panel-body tw:text-sm">Perangkat: <a href="{{ route('antareja.inventory.show', $item->device_id) }}">{{ $item->device->displayName() }}</a></div></div>
            @endif
            @can('ant-capacity.update')
                <form method="post" action="{{ route('antareja.capacity.update', $item) }}" class="panel panel-default">
                    @csrf @method('PUT')
                    <div class="panel-heading"><strong>Ambang</strong></div>
                    <div class="panel-body">
                        <div class="row">
                            <div class="form-group col-xs-6"><label for="warn_pct">Peringatan (%)</label><input id="warn_pct" name="warn_pct" type="number" min="1" max="100" class="form-control" value="{{ $item->warn_pct }}"></div>
                            <div class="form-group col-xs-6"><label for="crit_pct">Kritis (%)</label><input id="crit_pct" name="crit_pct" type="number" min="1" max="100" class="form-control" value="{{ $item->crit_pct }}"></div>
                        </div>
                        <div class="checkbox"><label><input type="hidden" name="enabled" value="0"><input type="checkbox" name="enabled" value="1" @checked($item->enabled)> Dipantau</label></div>
                        <button class="btn btn-default">Simpan</button>
                    </div>
                </form>
            @endcan
        </div>
    </div>
@endsection
