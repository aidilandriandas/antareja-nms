@extends('antareja::layouts.page')

@section('title', 'Site & Area')
@section('breadcrumb')<li class="active">Site &amp; Area</li>@endsection
@section('heading', 'Site & Area')
@section('subheading', 'Lokasi operasional tambang: office, workshop, plant, pit, ROM, jetty, warehouse, mess, site remote.')

@section('actions')
    @can('ant-site.update')
        <a href="{{ route('antareja.sites.create') }}" class="btn btn-primary"><i class="fa fa-plus"></i> Tambah Site</a>
    @endcan
@endsection

@section('page')
    @if($unassigned > 0)
        <div class="ant-alert ant-alert--warn">
            <i class="fa fa-exclamation-triangle"></i>
            {{ $unassigned }} perangkat belum punya site.
            <a href="{{ route('antareja.inventory.index', ['site' => 'none']) }}">Lihat perangkat &rarr;</a>
        </div>
    @endif

    <form method="get" class="form-inline tw:mb-3">
        <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Cari kode / nama">
        <select name="type" class="form-control">
            <option value="">Semua tipe</option>
            @foreach($types as $key => $label)
                <option value="{{ $key }}" @selected(request('type') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="btn btn-default"><i class="fa fa-search"></i> Filter</button>
    </form>

    <div class="table-responsive">
        <table class="table table-hover table-condensed">
            <thead>
            <tr>
                <th>Kode</th><th>Nama</th><th>Tipe</th><th>Area</th><th>Perangkat</th><th>Online / Offline</th><th>PIC</th><th>Status</th>
            </tr>
            </thead>
            <tbody>
            @forelse($sites as $site)
                @php($st = $status[$site->id] ?? null)
                <tr>
                    <td><a href="{{ route('antareja.sites.show', $site) }}"><strong>{{ $site->code }}</strong></a></td>
                    <td>{{ $site->name }}</td>
                    <td>{{ $site->typeLabel() }}</td>
                    <td>{{ $site->areas_count }}</td>
                    <td><a href="{{ route('antareja.inventory.index', ['site' => $site->id]) }}">{{ $site->devices_count }}</a></td>
                    <td>
                        <span class="ant-text-ok">{{ (int) ($st->up ?? 0) }}</span> /
                        <span class="{{ ($st->down ?? 0) > 0 ? 'ant-text-crit tw:font-semibold' : '' }}">{{ (int) ($st->down ?? 0) }}</span>
                    </td>
                    <td>{{ $site->picLabel() ?? '—' }}</td>
                    <td>{!! $site->is_active ? '<span class="label label-success">Aktif</span>' : '<span class="label label-default">Nonaktif</span>' !!}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="tw:text-center tw:py-6 ant-muted">
                    Belum ada site. @can('ant-site.update')<a href="{{ route('antareja.sites.create') }}">Tambah site pertama</a>.@endcan
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $sites->links() }}
@endsection
