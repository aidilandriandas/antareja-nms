@extends('antareja::layouts.page')

@section('title', "Site {$site->code}")
@section('breadcrumb')
    <li><a href="{{ route('antareja.sites.index') }}">Site &amp; Area</a></li>
    <li class="active">{{ $site->code }}</li>
@endsection
@section('heading')
    {{ $site->code }} — {{ $site->name }}
@endsection
@section('subheading', $site->typeLabel() . ($site->address ? ' · ' . $site->address : ''))

@section('actions')
    @can('ant-site.update')
        <a href="{{ route('antareja.sites.edit', $site) }}" class="btn btn-default"><i class="fa fa-pencil"></i> Edit</a>
    @endcan
    <a href="{{ route('antareja.inventory.index', ['site' => $site->id]) }}" class="btn btn-default"><i class="fa fa-boxes"></i> Inventaris site</a>
@endsection

@section('page')
<div class="row">
    <div class="col-md-4">
        <div class="panel panel-default">
            <div class="panel-heading"><strong>Info site</strong></div>
            <table class="table table-condensed tw:m-0">
                <tr><th class="tw:w-36">Tipe</th><td>{{ $site->typeLabel() }}</td></tr>
                <tr><th>Status</th><td>{!! $site->is_active ? '<span class="label label-success">Aktif</span>' : '<span class="label label-default">Nonaktif</span>' !!}</td></tr>
                <tr><th>PIC</th><td>{{ $site->picLabel() ?? '—' }} @if($site->pic_phone)<br><span class="tw:text-xs">{{ $site->pic_phone }}</span>@endif</td></tr>
                <tr><th>Lokasi SNMP</th><td>{{ $site->location?->location ?? '—' }}</td></tr>
                <tr><th>Koordinat</th><td>
                    @if($site->latitude !== null && $site->longitude !== null)
                        {{ $site->latitude }}, {{ $site->longitude }}
                    @else — @endif
                </td></tr>
                <tr><th>Area</th><td>
                    @forelse($site->areas as $area)<span class="label label-default tw:mr-1">{{ $area->name }}</span>@empty — @endforelse
                </td></tr>
            </table>
            @if($site->description)<div class="panel-body tw:text-sm">{{ $site->description }}</div>@endif
        </div>
    </div>
    <div class="col-md-8">
        <div class="panel panel-default">
            <div class="panel-heading"><strong>Perangkat di site ini ({{ $devices->count() }})</strong></div>
            <div class="table-responsive">
                <table class="table table-condensed table-hover tw:m-0">
                    <thead><tr><th>Perangkat</th><th>Kategori</th><th>Area</th><th>Status</th><th>Verifikasi</th></tr></thead>
                    <tbody>
                    @forelse($devices as $detail)
                        <tr>
                            <td><a href="{{ route('antareja.inventory.show', $detail->device_id) }}">{{ $detail->device?->displayName() }}</a></td>
                            <td>{{ $detail->categoryLabel() }}</td>
                            <td>{{ $detail->area?->name ?? '—' }}</td>
                            <td>
                                <x-antareja::device-status :device="$detail->device" />
                            </td>
                            <td>@include('antareja::partials.verification-badge', ['status' => $detail->displayStatus()])</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="tw:text-center ant-muted tw:py-4">Belum ada perangkat di site ini.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
