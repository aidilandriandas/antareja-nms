@extends('antareja::layouts.page')

@php($fmt = fn ($s) => \Antareja\Services\SlaCalculator::fmtDuration($s))
@php($pct = fn ($p, $d = 3) => \Antareja\Services\SlaCalculator::fmtPct($p, $d))

@section('title', $service->name)
@section('breadcrumb')
    <li><a href="{{ route('antareja.sla.index', ['month' => $month->format('Y-m')]) }}">SLA Management</a></li>
    @if($service->parent)<li><a href="{{ route('antareja.sla.show', ['service' => $service->parent, 'month' => $month->format('Y-m')]) }}">{{ $service->parent->name }}</a></li>@endif
    <li class="active">{{ $service->name }}</li>
@endsection
@section('heading')
    {{ $service->name }} @include('antareja::sla._live', ['state' => $live])
    @unless($service->active)<x-antareja::status status="unknown" size="sm">nonaktif</x-antareja::status>@endunless
@endsection
@section('subheading', $service->calcLabel() . ' · target ' . $pct($service->sla_target, 2) . ($service->site ? ' · ' . $service->site->code . ' ' . $service->site->name : ''))

@section('actions')
    @can('ant-sla.update')
        <a href="{{ route('antareja.sla.create', ['parent' => $service->id]) }}" class="btn btn-default"><i class="fa fa-plus"></i> Layanan anak</a>
        <a href="{{ route('antareja.sla.edit', $service) }}" class="btn btn-default"><i class="fa fa-pencil"></i> Edit</a>
        <form method="post" action="{{ route('antareja.sla.destroy', $service) }}" onsubmit="return confirm('Hapus layanan ini? Riwayat SLA bulanannya ikut terhapus.')">@csrf @method('DELETE')<button class="btn btn-default">Hapus</button></form>
    @endcan
@endsection

@section('page')
    <div class="tw:flex tw:flex-wrap tw:items-center tw:justify-between tw:gap-3 tw:mb-4">
        @include('antareja::partials.month-nav', ['route' => 'antareja.sla.show', 'params' => ['service' => $service->id]])
    </div>

    <div class="tw:grid tw:grid-cols-2 tw:md:grid-cols-3 tw:lg:grid-cols-6 tw:gap-3 tw:mb-5">
        <x-antareja::stat label="Availability" :value="$pct($stats['availability'])" :tone="$stats['met'] === null ? 'neutral' : ($stats['met'] ? 'ok' : 'crit')" :hint="'target ' . $pct($stats['target'], 2)" />
        <x-antareja::stat label="Downtime" :value="$fmt($stats['downtime'])" :tone="$stats['downtime'] > 0 ? 'warn' : 'ok'" />
        <x-antareja::stat label="Gangguan" :value="$stats['outages']" />
        <x-antareja::stat label="MTTR" :value="$fmt($stats['mttr'])" hint="rata-rata lama pulih" />
        <x-antareja::stat label="MTBF" :value="$fmt($stats['mtbf'])" hint="rata-rata jarak antar gangguan" />
        <x-antareja::stat label="Dikecualikan" :value="$stats['excluded'] ? $fmt($stats['excluded']) : '—'" hint="downtime saat maintenance" />
    </div>

    @if($service->description)<p class="tw:text-sm tw:whitespace-pre-line">{{ $service->description }}</p>@endif

    <div class="row">
        <div class="col-lg-7">
            @if($service->children->isNotEmpty())
                <div class="panel panel-default">
                    <div class="panel-heading"><strong>Layanan anak ({{ $service->children->count() }})</strong></div>
                    <div class="table-responsive">
                        <table class="table table-condensed tw:m-0">
                            <thead><tr><th>Layanan</th><th>Status</th><th class="tw:text-right">Availability</th><th class="tw:text-right">Downtime</th></tr></thead>
                            <tbody>
                            @foreach($service->children as $c)
                                <tr>
                                    <td><a href="{{ route('antareja.sla.show', ['service' => $c, 'month' => $month->format('Y-m')]) }}">{{ $c->name }}</a> <span class="tw:text-xs ant-muted">{{ $c->calcLabel() }}</span></td>
                                    <td>@include('antareja::sla._live', ['state' => $childLive[$c->id]])</td>
                                    <td class="tw:text-right">@include('antareja::sla._avail', ['s' => $childStats[$c->id], 'target' => $c->sla_target])</td>
                                    <td class="tw:text-right ant-data">{{ $fmt($childStats[$c->id]['downtime']) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <div class="panel panel-default">
                <div class="panel-heading"><strong>Perangkat anggota ({{ $service->devices->count() }})</strong></div>
                <div class="table-responsive">
                    <table class="table table-condensed tw:m-0">
                        <thead><tr><th>Perangkat</th><th>Status</th><th class="tw:text-right">Availability</th><th class="tw:text-right">Downtime</th><th class="tw:text-right">Gangguan</th></tr></thead>
                        <tbody>
                        @forelse($service->devices as $d)
                            @php($ds = $deviceStats[$d->device_id])
                            <tr>
                                <td><a href="{{ route('antareja.inventory.show', $d->device_id) }}">{{ $d->displayName() }}</a></td>
                                <td><x-antareja::device-status :device="$d" /></td>
                                <td class="tw:text-right">@include('antareja::sla._avail', ['s' => $ds, 'target' => $service->sla_target])</td>
                                <td class="tw:text-right ant-data">{{ $fmt($ds['downtime']) }}</td>
                                <td class="tw:text-right ant-data">{{ $ds['outages'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="ant-muted tw:text-center">Belum ada perangkat.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Gangguan layanan bulan ini</strong></div>
                <div class="table-responsive">
                    <table class="table table-condensed tw:m-0">
                        <thead><tr><th>Mulai</th><th>Selesai</th><th class="tw:text-right">Durasi</th></tr></thead>
                        <tbody>
                        @forelse(array_reverse($stats['intervals']) as [$s, $e])
                            <tr>
                                <td class="ant-data tw:text-xs">{{ \Carbon\Carbon::createFromTimestamp($s, config('app.timezone'))->format('d M H:i') }}</td>
                                <td class="ant-data tw:text-xs">{{ $e >= time() - 60 ? 'berlangsung' : \Carbon\Carbon::createFromTimestamp($e, config('app.timezone'))->format('d M H:i') }}</td>
                                <td class="tw:text-right ant-data">{{ $fmt($e - $s) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="ant-muted tw:text-center">Tidak ada gangguan.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Riwayat bulanan</strong></div>
                <div class="table-responsive">
                    <table class="table table-condensed tw:m-0">
                        <thead><tr><th>Bulan</th><th class="tw:text-right">Availability</th><th class="tw:text-right">Downtime</th><th>Target</th></tr></thead>
                        <tbody>
                        <tr>
                            <td>{{ $month->locale('id')->translatedFormat('M Y') }} @if($month->isSameMonth(now()))<span class="tw:text-xs ant-muted">(berjalan)</span>@endif</td>
                            <td class="tw:text-right">@include('antareja::sla._avail', ['s' => $stats, 'target' => $service->sla_target])</td>
                            <td class="tw:text-right ant-data">{{ $fmt($stats['downtime']) }}</td>
                            <td>@if($stats['met'] !== null)<x-antareja::status :status="$stats['met'] ? 'ok' : 'crit'" size="sm">{{ $stats['met'] ? 'tercapai' : 'tidak' }}</x-antareja::status>@endif</td>
                        </tr>
                        @foreach($history->reverse() as $h)
                            <tr>
                                <td>{{ $h->month->locale('id')->translatedFormat('M Y') }}</td>
                                <td class="tw:text-right">@include('antareja::sla._avail', ['s' => ['availability' => $h->availability], 'target' => $h->sla_target])</td>
                                <td class="tw:text-right ant-data">{{ $fmt($h->downtime_seconds) }}</td>
                                <td><x-antareja::status :status="$h->met ? 'ok' : 'crit'" size="sm">{{ $h->met ? 'tercapai' : 'tidak' }}</x-antareja::status></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
