@extends('antareja::layouts.page')

@php($chart = \Antareja\Support\SmokeChart::class)
@php($labels = ['3h' => '3 jam terakhir', '30h' => '30 jam terakhir', '10d' => '10 hari terakhir (rata-rata per jam)', '30d' => '30 hari terakhir (rata-rata per 3 jam)'])

@section('title', $target->name)
@section('breadcrumb')
    <li><a href="{{ route('antareja.quality.index') }}">Network Quality</a></li>
    <li class="active">{{ $target->name }}</li>
@endsection
@section('heading')
    {{ $target->name }} <span class="label {{ $target->statusMeta()['class'] }}">{{ $target->statusMeta()['label'] }}</span>
@endsection
@section('subheading', implode(' · ', array_filter([$target->host, $target->group_name, $target->site?->code, $target->pings . ' ping / 5 menit'])))

@section('actions')
    @if($target->device)<a href="{{ route('antareja.inventory.show', $target->device_id) }}" class="btn btn-default"><i class="fa fa-boxes"></i> Perangkat</a>@endif
    @can('ant-quality.update')
        <form method="post" action="{{ route('antareja.quality.measure', $target) }}">@csrf
            <button class="btn btn-default" title="Butuh ±{{ $target->pings }} detik"><i class="fa fa-play"></i> Ukur sekarang</button></form>
        <a href="{{ route('antareja.quality.edit', $target) }}" class="btn btn-primary"><i class="fa fa-pencil"></i> Edit</a>
    @endcan
@endsection

@section('page')
    <div class="tw:grid tw:grid-cols-1 tw:md:grid-cols-3 tw:gap-3 tw:mb-4">
        @foreach($summaries as $period => $sum)
            <div class="tw:rounded-lg tw:border tw:border-gray-200 tw:dark:border-dark-gray-200 tw:bg-white tw:dark:bg-dark-gray-300 tw:p-3">
                <div class="tw:text-xs tw:font-semibold tw:uppercase tw:text-gray-500 tw:mb-2">{{ $period }}</div>
                @if($sum['samples'] === 0)
                    <div class="tw:text-sm tw:text-gray-500">Belum ada data.</div>
                @else
                    <div class="tw:grid tw:grid-cols-3 tw:gap-2 tw:text-sm">
                        <div><div class="tw:text-xs tw:text-gray-500">Median rata-rata</div><div class="tw:font-semibold tw:tabular-nums">{{ $sum['avg_median'] ?? '—' }} ms</div></div>
                        <div><div class="tw:text-xs tw:text-gray-500">P95 median</div><div class="tw:font-semibold tw:tabular-nums">{{ $sum['p95_median'] ?? '—' }} ms</div></div>
                        <div><div class="tw:text-xs tw:text-gray-500">Jitter</div><div class="tw:font-semibold tw:tabular-nums">{{ $sum['avg_jitter'] ?? '—' }} ms</div></div>
                        <div><div class="tw:text-xs tw:text-gray-500">Avg loss</div><div class="tw:font-semibold tw:tabular-nums" style="color: {{ $chart::lossColor($sum['avg_loss']) }}">{{ $sum['avg_loss'] }}%</div></div>
                        <div><div class="tw:text-xs tw:text-gray-500">Uptime jalur</div><div class="tw:font-semibold tw:tabular-nums">{{ $sum['availability'] }}%</div></div>
                        <div><div class="tw:text-xs tw:text-gray-500">Sampel</div><div class="tw:font-semibold tw:tabular-nums">{{ $sum['samples'] }}</div></div>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    @foreach($charts as $range => $series)
        <div class="panel panel-default">
            <div class="panel-heading tw:flex tw:justify-between tw:items-center">
                <strong>{{ $labels[$range] }}</strong>
                <a href="{{ route('antareja.quality.show', [$target, 'range' => $range]) }}" class="tw:text-xs">perbesar</a>
            </div>
            <div class="panel-body tw:text-gray-700 tw:dark:text-gray-200">{!! $chart::render($series, (float) $target->latency_warn_ms) !!}</div>
        </div>
    @endforeach

    <div class="tw:flex tw:flex-wrap tw:gap-4 tw:text-xs tw:text-gray-500 tw:mb-4">
        <span class="tw:inline-flex tw:items-center tw:gap-1"><span class="tw:inline-block tw:w-4 tw:h-3 tw:bg-gray-400/40"></span>p10–p90</span>
        <span class="tw:inline-flex tw:items-center tw:gap-1"><span class="tw:inline-block tw:w-4 tw:h-3 tw:bg-gray-500/70"></span>p25–p75</span>
        <span>Median (warna = loss):</span>
        @foreach($chart::LOSS_COLORS as [$max, $color, $label])
            <span class="tw:inline-flex tw:items-center tw:gap-1"><span class="tw:inline-block tw:w-3 tw:h-1 tw:rounded" style="background: {{ $color }}"></span>{{ $label }}</span>
        @endforeach
        <span class="tw:inline-flex tw:items-center tw:gap-1"><span class="tw:inline-block tw:w-3 tw:h-3" style="background: rgba(220,38,38,.25)"></span>Putus</span>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Sampel terakhir</strong></div>
                <div class="table-responsive">
                    <table class="table table-condensed tw:m-0 tw:text-sm">
                        <thead><tr><th>Waktu</th><th class="tw:text-right">Loss</th><th class="tw:text-right">Min</th><th class="tw:text-right">Median</th><th class="tw:text-right">Avg</th><th class="tw:text-right">P90</th><th class="tw:text-right">Max</th><th class="tw:text-right">Jitter</th></tr></thead>
                        <tbody>
                        @forelse($recent as $s)
                            <tr>
                                <td class="tw:whitespace-nowrap">{{ $s->measured_at->format('d M H:i') }}</td>
                                <td class="tw:text-right" style="color: {{ $chart::lossColor($s->loss_pct) }}">{{ number_format($s->loss_pct, 0) }}% <span class="tw:text-xs tw:text-gray-500">({{ $s->received }}/{{ $s->sent }})</span></td>
                                @foreach(['min_ms', 'median_ms', 'avg_ms', 'p90_ms', 'max_ms', 'jitter_ms'] as $f)
                                    <td class="tw:text-right tw:tabular-nums">{{ $s->$f !== null ? number_format($s->$f, 2) : '—' }}</td>
                                @endforeach
                            </tr>
                        @empty
                            <tr><td colspan="8" class="tw:text-center tw:text-gray-500 tw:py-4">Belum ada sampel.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Ambang status</strong></div>
                <table class="table table-condensed tw:m-0 tw:text-sm">
                    <tr><th></th><th>Menurun</th><th>Buruk</th></tr>
                    <tr><td>Median latency</td><td>≥ {{ $target->latency_warn_ms }} ms</td><td>≥ {{ $target->latency_crit_ms }} ms</td></tr>
                    <tr><td>Packet loss</td><td>≥ {{ rtrim(rtrim(number_format($target->loss_warn_pct, 2), '0'), '.') }}%</td><td>≥ {{ rtrim(rtrim(number_format($target->loss_crit_pct, 2), '0'), '.') }}%</td></tr>
                    <tr><td>Jitter</td><td>≥ {{ $target->jitter_warn_ms }} ms</td><td>—</td></tr>
                </table>
                <div class="panel-body tw:text-xs tw:text-gray-500">Status "Putus" bila seluruh ping dalam satu siklus hilang. Perubahan status dicatat ke event log perangkat terkait.</div>
            </div>
            @if($target->description)<div class="panel panel-default"><div class="panel-body tw:text-sm">{{ $target->description }}</div></div>@endif
        </div>
    </div>
@endsection
