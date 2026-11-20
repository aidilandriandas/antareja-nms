@extends('antareja::layouts.page')

@section('title', 'Grup ' . $group->name)
@section('breadcrumb')
    <li><a href="{{ route('antareja.groups.index') }}">Grup Perangkat</a></li>
    <li class="active">{{ $group->name }}</li>
@endsection
@section('heading', $group->name)
@section('subheading', $auto ? 'Grup otomatis — anggota mengikuti kategori di inventaris.' : ($group->desc ?: 'Grup buatan sendiri.'))
@section('actions')
    <a href="{{ route('devices', ['filter' => ['groups.id' => ['eq' => $group->id]]]) }}" class="btn btn-default btn-sm"><i class="fa-solid fa-table-list"></i> Buka di daftar perangkat</a>
    @if(! $auto)
        @can('update', $group)<a href="{{ url('device-groups/' . $group->id . '/edit') }}" class="btn btn-default btn-sm"><i class="fa-solid fa-pen"></i> Edit grup</a>@endcan
    @endif
@endsection

@section('page')
    @php($down = $devices->filter(fn ($d) => ! $d->status && ! $d->disabled && ! $d->ignore)->count())
    <div class="ant-kpis">
        <div class="ant-kpi"><span class="ant-kpi__label">Anggota</span><span class="ant-kpi__value">{{ $devices->count() }}</span></div>
        <div @class(['ant-kpi', 'ant-kpi--crit' => $down > 0])><span class="ant-kpi__label">Down</span><span class="ant-kpi__value">{{ $down }}</span></div>
        <div class="ant-kpi"><span class="ant-kpi__label">Online</span><span class="ant-kpi__value">{{ $devices->filter(fn ($d) => $d->status && ! $d->disabled && ! $d->ignore)->count() }}</span></div>
    </div>

    <div class="panel panel-default">
        @if($devices->isEmpty())
            <div class="panel-body ant-muted">Belum ada perangkat di grup ini.@if($auto) Set kategori perangkat di Inventaris Perangkat agar masuk grup ini.@endif</div>
        @else
            <table class="table table-hover tw:m-0">
                <thead><tr><th>Status</th><th>Nama</th><th>IP / hostname</th><th>Site</th><th>Uptime</th><th></th></tr></thead>
                <tbody>
                @foreach($devices as $d)
                    @php($st = $d->disabled || $d->ignore ? ['unknown', 'Nonaktif'] : ($d->status ? ['ok', 'Online'] : ['down', 'Down']))
                    <tr>
                        <td><x-antareja::status :status="$st[0]" size="sm">{{ $st[1] }}</x-antareja::status></td>
                        <td><a href="{{ url('device/' . $d->device_id) }}"><b>{{ $d->displayName() }}</b></a></td>
                        <td class="ant-data">{{ $d->hostname }}</td>
                        <td>{{ $details[$d->device_id]->site_code ?? '—' }}</td>
                        <td class="ant-data">{{ $d->status && $d->uptime ? \LibreNMS\Util\Time::formatInterval($d->uptime, true) : '—' }}</td>
                        <td class="text-right"><a href="{{ route('antareja.inventory.show', $d->device_id) }}" class="btn btn-default btn-xs">Inventaris</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
