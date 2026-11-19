@extends('antareja::layouts.page')

@section('title', 'CCTV Infrastructure')
@section('breadcrumb')<li class="active">CCTV Infrastructure</li>@endsection
@section('heading', 'CCTV Infrastructure')
@section('subheading', 'Kamera dan NVR: status, port switch, NVR perekam, lokasi, PIC, dan riwayat gangguan 30 hari.')

@section('page')
    <div class="tw:grid tw:grid-cols-2 tw:md:grid-cols-3 tw:xl:grid-cols-6 tw:gap-3 tw:mb-4">
        @include('antareja::partials.stat', ['label' => 'Kamera', 'value' => $totals['cameras'], 'href' => route('antareja.cctv.index')])
        @include('antareja::partials.stat', ['label' => 'Online', 'value' => $totals['online'], 'color' => 'ant-text-ok'])
        @include('antareja::partials.stat', ['label' => 'Offline', 'value' => $totals['offline'], 'color' => $totals['offline'] ? 'ant-text-crit' : null, 'href' => route('antareja.cctv.index', ['state' => 'down'])])
        @include('antareja::partials.stat', ['label' => 'NVR', 'value' => $totals['nvrs']])
        @include('antareja::partials.stat', ['label' => 'Tanpa port switch', 'value' => $totals['unmapped'], 'color' => $totals['unmapped'] ? 'ant-text-warn' : null, 'href' => route('antareja.cctv.index', ['state' => 'unmapped'])])
        @include('antareja::partials.stat', ['label' => 'Tanpa NVR', 'value' => $totals['no_nvr'], 'color' => $totals['no_nvr'] ? 'ant-text-warn' : null])
    </div>

    <form method="get" class="form-inline tw:mb-3 tw:flex tw:flex-wrap tw:gap-2">
        <select name="site" class="form-control">
            <option value="">Semua site</option>
            @foreach($sites as $site)<option value="{{ $site->id }}" @selected((string) request('site') === (string) $site->id)>{{ $site->code }}</option>@endforeach
        </select>
        <select name="nvr" class="form-control">
            <option value="">Semua NVR</option>
            @foreach($nvrs as $nvr)<option value="{{ $nvr->device_id }}" @selected((string) request('nvr') === (string) $nvr->device_id)>{{ $nvr->device->displayName() }}</option>@endforeach
        </select>
        <select name="state" class="form-control">
            <option value="">Semua kamera</option>
            <option value="down" @selected(request('state') === 'down')>Offline</option>
            <option value="unmapped" @selected(request('state') === 'unmapped')>Belum dipetakan ke port switch</option>
        </select>
        <button class="btn btn-default"><i class="fa fa-filter"></i> Filter</button>
        @can('ant-mapping.update')<a href="{{ route('antareja.mapping.create') }}" class="btn btn-link"><i class="fa fa-plus"></i> Petakan kamera</a>@endcan
    </form>

    @if($nvrs->isNotEmpty())
    <div class="panel panel-default">
        <div class="panel-heading"><strong>NVR ({{ $nvrs->count() }})</strong></div>
        <div class="table-responsive">
            <table class="table table-condensed tw:m-0">
                <thead><tr><th></th><th>NVR</th><th>IP</th><th>Model</th><th>Site</th><th>Kamera terhubung</th><th>Kamera offline</th><th>PIC</th></tr></thead>
                <tbody>
                @foreach($nvrs as $nvr)
                    @php($linked = $camerasPerNvr[$nvr->device_id] ?? collect())
                    @php($offline = $linked->filter(fn ($l) => ($cameraStatus[$l->b_device_id] ?? true) === false)->count())
                    <tr>
                        <td><x-antareja::device-status :device="$nvr->device" /></td>
                        <td><a href="{{ route('antareja.inventory.show', $nvr->device_id) }}"><strong>{{ $nvr->device->displayName() }}</strong></a></td>
                        <td class="tw:font-mono tw:text-xs">{{ $nvr->device->overwrite_ip ?: $nvr->device->ip }}</td>
                        <td class="tw:text-xs">{{ $nvr->effectiveModel() ?? '—' }}</td>
                        <td>{{ $nvr->site?->code ?? '—' }}</td>
                        <td><a href="{{ route('antareja.cctv.index', ['nvr' => $nvr->device_id]) }}">{{ $linked->count() }}</a></td>
                        <td class="{{ $offline ? 'ant-text-crit tw:font-semibold' : '' }}">{{ $offline }}</td>
                        <td>{{ $nvr->picLabel() ?? '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <div class="panel panel-default">
        <div class="panel-heading"><strong>Kamera ({{ $cameras->count() }})</strong></div>
        <div class="table-responsive">
            <table class="table table-condensed table-hover tw:m-0">
                <thead><tr><th></th><th>Kamera</th><th>IP</th><th>MAC</th><th>Vendor / model</th><th>Site / lokasi</th><th>Port switch</th><th>NVR</th><th>PIC</th><th>Gangguan 30 hari</th></tr></thead>
                <tbody>
                @forelse($cameras as $cam)
                    @php($dev = $cam->device)
                    @php($sw = $switchPort[$cam->device_id] ?? null)
                    @php($nvr = $nvrOf[$cam->device_id] ?? null)
                    @php($out = $outages[$cam->device_id] ?? null)
                    <tr>
                        <td><x-antareja::device-status :device="$dev" /></td>
                        <td><a href="{{ route('antareja.inventory.show', $cam->device_id) }}"><strong>{{ $dev->displayName() }}</strong></a>
                            @if(! $dev->status && ! $dev->disabled && $dev->last_polled)<br><span class="tw:text-xs ant-text-crit">offline</span>@endif</td>
                        <td class="tw:font-mono tw:text-xs">{{ $dev->overwrite_ip ?: $dev->ip }}</td>
                        <td class="tw:font-mono tw:text-xs">{{ $cam->mac_address ?: ($ipamMac[$cam->device_id] ?? '—') }}</td>
                        <td class="tw:text-xs">{{ $dev->os }}<br>{{ \Illuminate\Support\Str::limit($cam->effectiveModel(), 30) }}</td>
                        <td>{{ $cam->site?->code ?? '—' }}<br><span class="tw:text-xs ant-muted">{{ $cam->area?->name }} {{ \Illuminate\Support\Str::limit($cam->installation_location, 30) }}</span></td>
                        <td class="tw:text-xs">@if($sw){{ $sw->aDevice?->displayName() }}<br><span class="tw:font-mono">{{ $sw->aPort?->getShortLabel() ?? 'port ?' }}</span>@else<span class="ant-text-warn">belum dipetakan</span>@endif</td>
                        <td class="tw:text-xs">{{ $nvr?->aDevice?->displayName() ?? '—' }}</td>
                        <td class="tw:text-xs">{{ $cam->picLabel() ?? '—' }}</td>
                        <td class="tw:text-xs">
                            @if($out)
                                {{ $out->n }}× · {{ \LibreNMS\Util\Time::formatInterval((int) $out->seconds, true) }}
                                <br><span class="ant-muted">terakhir {{ \Carbon\Carbon::createFromTimestamp($out->last_down)->diffForHumans() }}</span>
                            @else — @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="tw:text-center tw:py-6 ant-muted">Belum ada kamera. Isi kategori "CCTV Camera" di Device Inventory. Kamera tanpa SNMP bisa ditambahkan sebagai perangkat ping-only.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
