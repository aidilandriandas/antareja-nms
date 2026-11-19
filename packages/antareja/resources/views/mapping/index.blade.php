@extends('antareja::layouts.page')

@section('title', 'Device Mapping')
@section('breadcrumb')<li class="active">Device Mapping</li>@endsection
@section('heading', 'Device Mapping')
@section('subheading', 'Relasi perangkat terverifikasi (bukan topologi otomatis). Saran dari LLDP/CDP & tabel MAC wajib diverifikasi engineer.')

@section('actions')
    @can('ant-mapping.update')
        <form method="post" action="{{ route('antareja.mapping.suggest') }}">@csrf
            <button class="btn btn-default"><i class="fa fa-magic"></i> Cari saran sekarang</button>
        </form>
        <a href="{{ route('antareja.mapping.create') }}" class="btn btn-primary"><i class="fa fa-plus"></i> Tambah mapping</a>
    @endcan
@endsection

@section('page')
    <div class="alert alert-info tw:text-sm">
        <i class="fa fa-info-circle"></i>
        Konvensi: <strong>A = upstream / parent</strong> (switch, router, NVR, base station), <strong>B = downstream / child</strong>.
        Mapping terverifikasi bertipe uplink, AP, CCTV, PTMP, dan NVR otomatis menjadi dependensi parent/child,
        sehingga alert perangkat anak ditekan saat parent-nya down.
    </div>

    <ul class="nav nav-tabs tw:mb-3">
        @foreach(['open' => ['Aktif', ($counts['suggested'] ?? 0) + ($counts['verified'] ?? 0)], 'suggested' => ['Saran', $counts['suggested'] ?? 0], 'verified' => ['Terverifikasi', $counts['verified'] ?? 0], 'rejected' => ['Ditolak', $counts['rejected'] ?? 0]] as $key => [$label, $count])
            <li class="{{ $status === $key ? 'active' : '' }}"><a href="{{ route('antareja.mapping.index', array_merge(request()->except('page'), ['status' => $key])) }}">{{ $label }} <span class="badge">{{ $count }}</span></a></li>
        @endforeach
    </ul>

    <form method="get" class="form-inline tw:mb-3">
        <input type="hidden" name="status" value="{{ $status }}">
        <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Cari perangkat">
        <select name="type" class="form-control">
            <option value="">Semua tipe</option>
            @foreach(\Antareja\Support\Catalog::LINK_TYPES as $key => $label)<option value="{{ $key }}" @selected(request('type') === $key)>{{ $label }}</option>@endforeach
        </select>
        <select name="site" class="form-control">
            <option value="">Semua site</option>
            @foreach($sites as $site)<option value="{{ $site->id }}" @selected((string) request('site') === (string) $site->id)>{{ $site->code }}</option>@endforeach
        </select>
        <button class="btn btn-default"><i class="fa fa-filter"></i> Filter</button>
    </form>

    <div class="panel panel-default">
        @include('antareja::mapping.partials.table', ['links' => $links])
    </div>
    {{ $links->links() }}
@endsection
