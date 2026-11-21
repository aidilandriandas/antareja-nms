@extends('antareja::layouts.page')

@php($chart = \Antareja\Support\SmokeChart::class)

@section('title', 'Network Quality')
@section('breadcrumb')<li class="active">Network Quality</li>@endsection
@section('heading', 'Network Quality')
@section('subheading', 'Latency, packet loss, dan jitter link kritis (WAN, PTP/PTMP backhaul, antar-site). 20 ping setiap 5 menit dari server ANTAREJA.')

@section('actions')
    @can('ant-quality.update')
        <a href="{{ route('antareja.quality.bulk') }}" class="btn btn-default"><i class="fa fa-tasks"></i> Tambah dari daftar perangkat</a>
        <a href="{{ route('antareja.quality.create') }}" class="btn btn-primary"><i class="fa fa-plus"></i> Tambah target</a>
    @endcan
@endsection

@section('page')
    @if(($counts['stale'] ?? 0) > 0)
        <x-antareja::alert tone="warn">{{ $counts['stale'] }} target belum diukur lebih dari {{ \Antareja\Models\QualityTarget::STALE_MINUTES }} menit (seharusnya tiap 5 menit) —
            statusnya ditampilkan <b>Tidak diperbarui</b>. Cek penjadwal di server: <code>./antareja.sh health</code>.</x-antareja::alert>
    @endif
    <div class="tw:grid tw:grid-cols-2 tw:md:grid-cols-3 tw:xl:grid-cols-6 tw:gap-3 tw:mb-4">
        @foreach(['ok' => 'ant-text-ok', 'warning' => 'ant-text-warn', 'critical' => 'ant-text-crit', 'down' => 'ant-text-crit', 'stale' => 'ant-muted', 'unknown' => 'ant-muted'] as $key => $color)
            @include('antareja::partials.stat', ['label' => \Antareja\Models\QualityTarget::STATUS[$key]['label'], 'value' => $counts[$key] ?? 0, 'color' => $color, 'href' => route('antareja.quality.index', ['status' => $key])])
        @endforeach
    </div>

    <form method="get" class="form-inline tw:mb-3 tw:flex tw:flex-wrap tw:gap-2">
        <select name="site" class="form-control">
            <option value="">Semua site</option>
            @foreach($sites as $site)<option value="{{ $site->id }}" @selected((string) request('site') === (string) $site->id)>{{ $site->code }}</option>@endforeach
        </select>
        <select name="group" class="form-control">
            <option value="">Semua grup</option>
            @foreach($groups as $group)<option value="{{ $group }}" @selected(request('group') === $group)>{{ $group }}</option>@endforeach
        </select>
        <button class="btn btn-default"><i class="fa fa-filter"></i> Filter</button>
        @if(request()->hasAny(['site', 'group', 'status']))<a href="{{ route('antareja.quality.index') }}" class="btn btn-link">Reset</a>@endif
    </form>

    <div class="table-responsive">
        <table class="table table-hover table-condensed">
            <thead>
            <tr><th>Target</th><th>Host</th><th>Site</th><th>Status</th>
                <th class="tw:text-right">Median</th><th class="tw:text-right">Loss</th><th class="tw:text-right">Jitter</th>
                <th>24 jam (median)</th><th class="tw:text-right">Avg loss 24j</th><th class="tw:text-right">Uptime 24j</th><th>Terakhir</th></tr>
            </thead>
            <tbody>
            @php($currentGroup = false)
            @forelse($targets as $target)
                @if($target->group_name !== $currentGroup)
                    @php($currentGroup = $target->group_name)
                    <tr class="active"><td colspan="11" class="tw:font-semibold tw:text-xs tw:uppercase tw:tracking-wide">{{ $currentGroup ?? 'Tanpa grup' }}</td></tr>
                @endif
                @php($s = $target->latestSample)
                @php($d = $day[$target->id] ?? null)
                <tr class="{{ $target->enabled ? '' : 'tw:opacity-50' }}">
                    <td><a href="{{ route('antareja.quality.show', $target) }}"><strong>{{ $target->name }}</strong></a>
                        @unless($target->enabled)<span class="label label-default">nonaktif</span>@endunless</td>
                    <td class="tw:font-mono tw:text-xs">{{ $target->host }}</td>
                    <td>{{ $target->site?->code ?? '—' }}</td>
                    <td><x-antareja::status :status="$target->statusMeta()['tone']">{{ $target->statusMeta()['label'] }}</x-antareja::status></td>
                    <td class="tw:text-right tw:tabular-nums">{{ $s?->median_ms !== null ? number_format($s->median_ms, 1) . ' ms' : '—' }}</td>
                    <td class="tw:text-right tw:tabular-nums" style="color: {{ $s ? $chart::lossColor($s->loss_pct) : 'inherit' }}">{{ $s ? number_format($s->loss_pct, 0) . '%' : '—' }}</td>
                    <td class="tw:text-right tw:tabular-nums">{{ $s?->jitter_ms !== null ? number_format($s->jitter_ms, 1) . ' ms' : '—' }}</td>
                    <td>{!! $chart::sparkline($spark[$target->id] ?? []) !!}</td>
                    <td class="tw:text-right tw:tabular-nums">{{ $d ? number_format($d->loss, 2) . '%' : '—' }}</td>
                    <td class="tw:text-right tw:tabular-nums">{{ $d ? number_format($d->availability, 2) . '%' : '—' }}</td>
                    <td class="tw:text-xs">{{ $target->last_checked_at?->diffForHumans() ?? 'belum' }}</td>
                </tr>
            @empty
                <tr><td colspan="11" class="tw:text-center tw:py-6 ant-muted">
                    Belum ada target. @can('ant-quality.update')<a href="{{ route('antareja.quality.bulk') }}">Tambahkan link kritis dari inventaris</a>.@endcan
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="tw:flex tw:flex-wrap tw:gap-3 tw:text-xs ant-muted tw:mt-2">
        <span>Warna loss:</span>
        @foreach($chart::LOSS_COLORS as [$max, $color, $label])
            <span class="tw:inline-flex tw:items-center tw:gap-1"><span class="tw:inline-block tw:w-3 tw:h-1 tw:rounded" style="background: {{ $color }}"></span>{{ $label }}</span>
        @endforeach
        <span>· Uptime = persentase siklus 5 menit yang tidak loss 100%.</span>
    </div>
@endsection
