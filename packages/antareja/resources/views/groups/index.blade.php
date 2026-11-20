@extends('antareja::layouts.page')

@section('title', 'Grup Perangkat')
@section('breadcrumb')<li class="active">Grup Perangkat</li>@endsection
@section('heading', 'Grup Perangkat')
@section('subheading', 'Grup otomatis mengikuti kategori di inventaris — semua kamera masuk "CCTV", semua AP masuk "Access Point", dan seterusnya.')
@section('actions')
    @can('ant-inventory.update')
        <form method="post" action="{{ route('antareja.groups.sync') }}">@csrf
            <button class="btn btn-default btn-sm"><i class="fa-solid fa-arrows-rotate"></i> Sinkronkan grup</button>
        </form>
    @endcan
    @can('device.create')
        <a href="{{ route('antareja.devices.add') }}" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> Tambah perangkat</a>
    @endcan
@endsection

@section('page')
    @if($needsSync)
        <x-antareja::alert tone="info">Grup otomatis belum dibuat. Klik <b>Sinkronkan grup</b> untuk membuat grup per kategori dan mengisinya dari data inventaris.</x-antareja::alert>
    @endif
    @if($uncategorized > 0)
        <x-antareja::alert tone="warn">{{ $uncategorized }} perangkat belum punya kategori sehingga belum masuk grup mana pun —
            <a href="{{ route('antareja.inventory.index', ['issue' => 'missing_category']) }}">lengkapi kategorinya</a>.</x-antareja::alert>
    @endif

    <div class="ant-groups">
        @foreach($autoGroups as $g)
            <a href="{{ route('antareja.groups.show', $g->group) }}" @class(['ant-group', 'is-empty' => $g->total === 0, 'has-down' => $g->down > 0])>
                <span class="ant-group__icon"><i class="fa-solid fa-{{ $g->icon }}" aria-hidden="true"></i></span>
                <span class="ant-group__name">{{ $g->group->name }}</span>
                <span class="ant-group__count">{{ $g->total }} <small>perangkat</small></span>
                @if($g->total > 0)
                    <span class="ant-stack" role="img" aria-label="{{ $g->up }} online, {{ $g->down }} down, {{ $g->off }} nonaktif">
                        @if($g->up)<span class="ant-fill--ok" style="flex-grow: {{ $g->up }}"></span>@endif
                        @if($g->down)<span class="ant-fill--down" style="flex-grow: {{ $g->down }}"></span>@endif
                        @if($g->off)<span class="ant-fill--unknown" style="flex-grow: {{ $g->off }}"></span>@endif
                    </span>
                    <span class="ant-group__status">
                        @if($g->down)<b class="ant-text-crit">{{ $g->down }} down</b> · @endif{{ $g->up }} online
                    </span>
                @else
                    <span class="ant-group__status ant-muted">Belum ada anggota</span>
                @endif
            </a>
        @endforeach
    </div>

    <div class="tw:flex tw:items-center tw:justify-between tw:mt-8 tw:mb-3">
        <h2 class="tw:text-xl tw:m-0">Grup buatan sendiri</h2>
        @can('create', \App\Models\DeviceGroup::class)
            <a href="{{ url('device-groups/create') }}" class="btn btn-default btn-sm"><i class="fa-solid fa-plus"></i> Buat grup</a>
        @endcan
    </div>
    @if($customGroups->isEmpty())
        <p class="ant-muted">Belum ada. Buat grup sendiri untuk kebutuhan lain, mis. "Perangkat Kritis Pit A" atau "Link Backbone" — bisa statis (pilih perangkat) atau dinamis (aturan otomatis).</p>
    @else
        <div class="ant-groups">
            @foreach($customGroups as $g)
                <a href="{{ route('antareja.groups.show', $g->group) }}" @class(['ant-group', 'is-empty' => $g->total === 0, 'has-down' => $g->down > 0])>
                    <span class="ant-group__icon"><i class="fa-solid fa-layer-group" aria-hidden="true"></i></span>
                    <span class="ant-group__name">{{ $g->group->name }}</span>
                    <span class="ant-group__count">{{ $g->total }} <small>perangkat</small></span>
                    <span class="ant-group__status">@if($g->down)<b class="ant-text-crit">{{ $g->down }} down</b> · @endif{{ $g->up }} online</span>
                </a>
            @endforeach
        </div>
    @endif
@endsection
