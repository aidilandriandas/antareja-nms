@extends('antareja::layouts.page')

@php($W = \Antareja\Http\Controllers\WirelessController::class)

@section('title', 'Wireless Monitoring')
@section('breadcrumb')<li class="active">Wireless Monitoring</li>@endsection
@section('heading', 'Wireless Monitoring')
@section('subheading', 'Access point, controller, PTP, dan PTMP. Metrik dibaca dari sensor wireless SNMP; tidak semua vendor menyediakan metrik yang sama.')

@section('page')
    <div class="tw:grid tw:grid-cols-2 tw:md:grid-cols-3 tw:xl:grid-cols-6 tw:gap-3 tw:mb-4">
        @include('antareja::partials.stat', ['label' => 'Perangkat wireless', 'value' => $totals['devices']])
        @include('antareja::partials.stat', ['label' => 'Online', 'value' => $totals['online'], 'color' => 'ant-text-ok'])
        @include('antareja::partials.stat', ['label' => 'Offline', 'value' => $totals['offline'], 'color' => $totals['offline'] ? 'ant-text-crit' : null, 'href' => route('antareja.wireless.index', ['state' => 'down'])])
        @include('antareja::partials.stat', ['label' => 'Total client', 'value' => number_format($totals['clients'])])
        @include('antareja::partials.stat', ['label' => 'Sinyal lemah', 'value' => $totals['weak'], 'color' => $totals['weak'] ? 'ant-text-warn' : null, 'hint' => 'RSSI < -75 dBm / SNR < 20 dB'])
        @include('antareja::partials.stat', ['label' => 'Tanpa metrik wireless', 'value' => $totals['no_metrics'], 'hint' => 'hanya status ICMP/SNMP dasar'])
    </div>

    <form method="get" class="form-inline tw:mb-3 tw:flex tw:flex-wrap tw:gap-2">
        <select name="site" class="form-control">
            <option value="">Semua site</option>
            @foreach($sites as $site)<option value="{{ $site->id }}" @selected((string) request('site') === (string) $site->id)>{{ $site->code }}</option>@endforeach
        </select>
        <select name="category" class="form-control">
            <option value="">Semua kategori wireless</option>
            @foreach($W::WIRELESS_CATEGORIES as $cat)<option value="{{ $cat }}" @selected(request('category') === $cat)>{{ \Antareja\Support\Catalog::DEVICE_CATEGORIES[$cat] }}</option>@endforeach
        </select>
        <select name="state" class="form-control">
            <option value="">Semua status</option><option value="down" @selected(request('state') === 'down')>Offline</option>
        </select>
        <button class="btn btn-default"><i class="fa fa-filter"></i> Filter</button>
    </form>

    <div class="table-responsive">
        <table class="table table-hover table-condensed">
            <thead>
            <tr>
                <th></th><th>Perangkat</th><th>Kategori</th><th>Site</th><th>Link partner</th>
                @foreach($W::METRICS as $class => [$label])<th class="tw:text-right">{{ $label }}</th>@endforeach
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse($rows as $row)
                @php($d = $row['detail'])
                <tr>
                    <td><x-antareja::device-status :device="$d->device" /></td>
                    <td><a href="{{ route('antareja.inventory.show', $d->device_id) }}"><strong>{{ $d->device->displayName() }}</strong></a>
                        <br><span class="tw:text-xs ant-muted">{{ $d->device->os }} {{ \Illuminate\Support\Str::limit($d->device->hardware, 28) }}</span></td>
                    <td class="tw:text-xs">{{ $d->categoryLabel() }}</td>
                    <td>{{ $d->site?->code ?? '—' }}</td>
                    <td class="tw:text-xs">
                        @forelse($partner[$d->device_id] ?? [] as $p)
                            <a href="{{ route('antareja.inventory.show', $p->device_id) }}">{{ $p->displayName() }}</a>@if($p->status) <span class="ant-text-ok">●</span>@else <span class="ant-text-crit">●</span>@endif<br>
                        @empty — @endforelse
                    </td>
                    @foreach($W::METRICS as $class => $meta)
                        @php($v = $row['values'][$class])
                        <td class="tw:text-right tw:tabular-nums tw:whitespace-nowrap {{ $W::weak($class, $v) ? 'ant-text-warn tw:font-semibold' : '' }}">{{ $W::format($class, $v) }}</td>
                    @endforeach
                    <td>@if($row['sensor_count'])<a href="{{ url('device/' . $d->device_id . '/wireless') }}" class="btn btn-xs btn-default" title="Grafik wireless"><i class="fa fa-chart-line"></i></a>@endif</td>
                </tr>
            @empty
                <tr><td colspan="{{ 6 + count($W::METRICS) }}" class="tw:text-center tw:py-6 ant-muted">
                    Belum ada perangkat wireless. Isi kategori Access Point / PTP / PTMP / Controller di Device Inventory, atau tambahkan perangkat wireless yang mendukung SNMP.
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <p class="tw:text-xs ant-muted">Client = jumlah dari semua radio; RSSI/SNR/CCQ/kapasitas = nilai terburuk antar radio; frekuensi & channel = radio pertama.
        Pasangan link diambil dari Device Mapping bertipe PTP/PTMP yang terverifikasi.</p>
@endsection
