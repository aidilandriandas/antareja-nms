@extends('antareja::layouts.page')

@php($fmt = fn ($s) => \Antareja\Services\SlaCalculator::fmtDuration($s))
@php($pct = fn ($p, $d = 2) => \Antareja\Services\SlaCalculator::fmtPct($p, $d))
@php($mon = fn ($ym) => \Carbon\CarbonImmutable::createFromFormat('Y-m-d', $ym . '-01')->locale('id')->translatedFormat('M y'))

@section('title', 'Dashboard Manajemen')
@section('breadcrumb')<li class="active">Dashboard Manajemen</li>@endsection
@section('heading', 'Dashboard Manajemen')
@section('subheading', 'Ringkasan layanan ICT ' . $month->locale('id')->translatedFormat('F Y') . ' sampai ' . now()->format('d M H:i') . '.')

@section('actions')
    @can('ant-report.view')<a href="{{ route('antareja.reports.monthly') }}" class="btn btn-default"><i class="fa fa-file-alt"></i> Laporan bulan ini</a>@endcan
@endsection

@section('page')
    @php($s = $summary)
    <div class="tw:grid tw:grid-cols-2 tw:md:grid-cols-3 tw:xl:grid-cols-6 tw:gap-3 tw:mb-5">
        <x-antareja::stat label="Availability jaringan" :value="$pct($s['device_availability'])" :tone="$s['device_availability'] === null ? 'neutral' : ($s['device_availability'] >= 99.5 ? 'ok' : ($s['device_availability'] >= 98 ? 'warn' : 'crit'))" :hint="'rata-rata ' . $s['devices'] . ' perangkat'" />
        <x-antareja::stat label="Layanan sesuai SLA" :value="$s['services'] ? $s['services_met'] . ' / ' . $s['services'] : '—'" :tone="$s['services'] && $s['services_met'] < $s['services'] ? 'crit' : 'ok'" :href="auth()->user()->can('ant-sla.view') ? route('antareja.sla.index') : null" />
        <x-antareja::stat label="Incident bulan ini" :value="$s['incidents']" :hint="$s['incidents_open'] . ' masih aktif'" :tone="$s['incidents_open'] > 0 ? 'warn' : 'neutral'" />
        <x-antareja::stat label="MTTA" :value="$fmt($s['mtta'])" hint="rata-rata waktu respons" />
        <x-antareja::stat label="MTTR" :value="$fmt($s['mttr'])" hint="rata-rata waktu pemulihan" />
        <x-antareja::stat label="Gangguan aktif" :value="$openProblems" :tone="$openProblems > 0 ? 'crit' : 'ok'" :hint="$s['devices_down_now'] . ' perangkat down'" />
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Availability jaringan — 6 bulan</strong></div>
                <div class="panel-body">
                    @include('antareja::partials.bar-chart', ['name' => 'Availability rata-rata perangkat per bulan', 'min' => max(0, floor(min(array_filter(array_column($trend, 'availability')) ?: [99]) - 1)),
                        'bars' => array_map(fn ($t) => ['label' => $mon($t['month']), 'value' => $t['availability'], 'text' => $pct($t['availability'])], $trend)])
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Incident — 6 bulan</strong></div>
                <div class="panel-body">
                    @include('antareja::partials.bar-chart', ['name' => 'Jumlah incident per bulan',
                        'bars' => array_map(fn ($t) => ['label' => $mon($t['month']), 'value' => $t['incidents'], 'text' => $t['incidents'] . ' incident'], $trend)])
                </div>
            </div>
        </div>
    </div>
    <details class="tw:mb-5 tw:text-sm">
        <summary class="tw:cursor-pointer ant-muted">Tabel tren</summary>
        <table class="table table-condensed tw:mt-2 tw:max-w-xl">
            <thead><tr><th>Bulan</th><th class="tw:text-right">Availability</th><th class="tw:text-right">Incident</th><th class="tw:text-right">MTTR</th></tr></thead>
            <tbody>@foreach($trend as $t)<tr><td>{{ $mon($t['month']) }}</td><td class="tw:text-right ant-data">{{ $pct($t['availability'], 3) }}</td><td class="tw:text-right ant-data">{{ $t['incidents'] }}</td><td class="tw:text-right ant-data">{{ $fmt($t['mttr']) }}</td></tr>@endforeach</tbody>
        </table>
    </details>

    <div class="row">
        <div class="col-lg-6">
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Availability per site</strong></div>
                <div class="table-responsive">
                    <table class="table table-condensed tw:m-0">
                        <thead><tr><th>Site</th><th class="tw:text-right">Perangkat</th><th class="tw:w-56">Availability</th><th class="tw:text-right">Downtime</th><th class="tw:text-right">Incident</th></tr></thead>
                        <tbody>
                        @forelse($sites as $row)
                            <tr>
                                <td><strong>{{ $row['code'] }}</strong> <span class="tw:text-xs ant-muted">{{ $row['name'] }}</span></td>
                                <td class="tw:text-right ant-data">{{ $row['devices'] }}</td>
                                <td>@if($row['availability'] !== null)<x-antareja::progress :value="$row['availability']" inverted :warn-at="99.5" :crit-at="98" :label="'Availability ' . $row['code']" />@else<span class="ant-muted">—</span>@endif</td>
                                <td class="tw:text-right ant-data">{{ $fmt($row['downtime']) }}</td>
                                <td class="tw:text-right ant-data">{{ $row['incidents'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="ant-muted tw:text-center">Belum ada site.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="panel panel-default">
                <div class="panel-heading"><strong>SLA layanan</strong></div>
                <div class="table-responsive">
                    <table class="table table-condensed tw:m-0">
                        <thead><tr><th>Layanan</th><th class="tw:text-right">Target</th><th class="tw:text-right">Availability</th><th></th></tr></thead>
                        <tbody>
                        @forelse($services as $sv)
                            <tr>
                                <td>{{ $sv['name'] }}</td>
                                <td class="tw:text-right ant-data">{{ $pct($sv['target']) }}</td>
                                <td class="tw:text-right">@include('antareja::sla._avail', ['s' => $sv, 'target' => $sv['target']])</td>
                                <td>@if($sv['met'] !== null)<x-antareja::status :status="$sv['met'] ? 'ok' : 'crit'" size="sm">{{ $sv['met'] ? 'tercapai' : 'di bawah target' }}</x-antareja::status>@endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="ant-muted tw:text-center">Belum ada layanan SLA.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Incident aktif</strong></div>
                <div class="list-group">
                    @forelse($activeIncidents as $i)
                        @php($sev = $i->severityMeta())
                        <a href="{{ auth()->user()->can('ant-incident.view') ? route('antareja.incidents.show', $i) : '#' }}" class="list-group-item tw:flex tw:items-center tw:gap-2">
                            <x-antareja::status :status="$sev['tone']" size="sm">{{ $sev['label'] }}</x-antareja::status>
                            <span class="tw:flex-1 tw:truncate">{{ $i->title }}</span>
                            <span class="tw:text-xs ant-muted tw:whitespace-nowrap">{{ $i->site?->code }} · {{ $i->started_at->diffForHumans(null, true) }}</span>
                        </a>
                    @empty
                        <div class="list-group-item"><x-antareja::status status="ok" size="sm">Tidak ada incident aktif</x-antareja::status></div>
                    @endforelse
                </div>
            </div>
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Perangkat paling sering terganggu</strong></div>
                <div class="table-responsive">
                    <table class="table table-condensed tw:m-0">
                        <thead><tr><th>Perangkat</th><th>Site</th><th class="tw:text-right">Gangguan</th><th class="tw:text-right">Downtime</th><th class="tw:text-right">Availability</th></tr></thead>
                        <tbody>
                        @forelse($worstDevices as $d)
                            <tr><td>{{ $d['name'] }}</td><td>{{ $d['site'] ?? '—' }}</td><td class="tw:text-right ant-data">{{ $d['outages'] }}</td><td class="tw:text-right ant-data">{{ $fmt($d['downtime']) }}</td><td class="tw:text-right">@include('antareja::sla._avail', ['s' => $d, 'target' => null])</td></tr>
                        @empty
                            <tr><td colspan="5" class="ant-muted tw:text-center">Tidak ada perangkat yang down bulan ini.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($quality->isNotEmpty())
                <div class="panel panel-default">
                    <div class="panel-heading"><strong>Link dengan packet loss tertinggi</strong></div>
                    <div class="table-responsive">
                        <table class="table table-condensed tw:m-0">
                            <thead><tr><th>Link</th><th class="tw:text-right">Loss rata-rata</th><th class="tw:text-right">Latency</th><th class="tw:text-right">Ketersediaan</th></tr></thead>
                            <tbody>
                            @foreach($quality as $q)
                                <tr><td>{{ $q['name'] }}</td><td class="tw:text-right ant-data">{{ number_format($q['avg_loss'], 2, ',', '.') }}%</td><td class="tw:text-right ant-data">{{ $q['avg_latency'] !== null ? number_format($q['avg_latency'], 1, ',', '.') . ' ms' : '—' }}</td><td class="tw:text-right ant-data">{{ $pct($q['availability']) }}</td></tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
