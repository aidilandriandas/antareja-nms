@extends('antareja::layouts.page')

@section('title', 'IP Address Management')
@section('breadcrumb')<li class="active">IPAM</li>@endsection
@section('heading', 'IP Address Management')
@section('subheading', 'Subnet, VLAN, alokasi IP. IP terpakai disinkronkan otomatis dari interface perangkat, IP manajemen, dan tabel ARP.')

@section('actions')
    <a href="{{ route('antareja.ipam.vlans') }}" class="btn btn-default"><i class="fa fa-layer-group"></i> VLAN</a>
    <a href="{{ route('antareja.ipam.conflicts') }}" class="btn btn-default"><i class="fa fa-exclamation-triangle"></i> Konflik <span class="badge">{{ $conflicts }}</span></a>
    @can('ant-ipam.update')
        <form method="post" action="{{ route('antareja.ipam.sync') }}">@csrf<button class="btn btn-default"><i class="fa fa-sync"></i> Sinkronkan</button></form>
        <a href="{{ route('antareja.ipam.import') }}" class="btn btn-default"><i class="fa fa-file-import"></i> Impor dari perangkat @if($candidates)<span class="badge">{{ $candidates }}</span>@endif</a>
        <a href="{{ route('antareja.ipam.subnets.create') }}" class="btn btn-primary"><i class="fa fa-plus"></i> Tambah subnet</a>
    @endcan
@endsection

@section('page')
    <div class="tw:grid tw:grid-cols-2 tw:md:grid-cols-3 tw:xl:grid-cols-6 tw:gap-3 tw:mb-4">
        @include('antareja::partials.stat', ['label' => 'Total IP (IPv4, usable)', 'value' => number_format($totals['usable'])])
        @include('antareja::partials.stat', ['label' => 'Used', 'value' => number_format($totals['used']), 'color' => 'ant-text-info'])
        @include('antareja::partials.stat', ['label' => 'Reserved', 'value' => number_format($totals['reserved']), 'color' => 'ant-text-warn'])
        @include('antareja::partials.stat', ['label' => 'Pool DHCP', 'value' => number_format($totals['dhcp_pool']), 'color' => 'ant-text-info'])
        @include('antareja::partials.stat', ['label' => 'Available', 'value' => number_format($totals['available']), 'color' => 'ant-text-ok'])
        @include('antareja::partials.stat', ['label' => 'Utilisasi', 'value' => ($totals['usable'] ? number_format(100 * $totals['allocated'] / $totals['usable'], 1) : 0) . '%', 'hint' => $totals['subnets'] . ' subnet'])
    </div>

    @if($conflicts > 0)
        <div class="ant-alert ant-alert--crit"><i class="fa fa-exclamation-triangle"></i> {{ $conflicts }} IP konflik terdeteksi (duplikat / MAC berbeda / reserved tetapi aktif).
            <a href="{{ route('antareja.ipam.conflicts') }}">Periksa &rarr;</a></div>
    @endif
    @if($orphans > 0)
        <div class="ant-alert ant-alert--warn"><i class="fa fa-question-circle"></i> {{ $orphans }} IP perangkat berada di luar subnet IPAM.
            <a href="{{ route('antareja.ipam.conflicts') }}#orphans">Lihat</a> atau <a href="{{ route('antareja.ipam.import') }}">impor subnet</a>.</div>
    @endif

    <form method="get" class="form-inline tw:mb-3 tw:flex tw:flex-wrap tw:gap-2">
        <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Cari IP, CIDR, atau nama">
        <select name="site" class="form-control">
            <option value="">Semua site</option>
            @foreach($sites as $site)<option value="{{ $site->id }}" @selected((string) request('site') === (string) $site->id)>{{ $site->code }}</option>@endforeach
        </select>
        <select name="purpose" class="form-control">
            <option value="">Semua fungsi</option>
            @foreach(\Antareja\Models\Subnet::PURPOSES as $key => $label)<option value="{{ $key }}" @selected(request('purpose') === $key)>{{ $label }}</option>@endforeach
        </select>
        <select name="vlan" class="form-control">
            <option value="">Semua VLAN</option>
            @foreach($vlans as $vlan)<option value="{{ $vlan->id }}" @selected((string) request('vlan') === (string) $vlan->id)>{{ $vlan->label() }}</option>@endforeach
        </select>
        <button class="btn btn-default"><i class="fa fa-search"></i> Cari</button>
    </form>

    <div class="table-responsive">
        <table class="table table-hover table-condensed">
            <thead>
            <tr><th>Subnet</th><th>Nama</th><th>Fungsi</th><th>Site</th><th>VLAN</th><th>Gateway</th>
                <th class="tw:text-right">Usable</th><th class="tw:text-right">Used</th><th class="tw:text-right">Reserved</th><th class="tw:text-right">Available</th><th>Utilisasi</th><th></th></tr>
            </thead>
            <tbody>
            @forelse($subnets as $subnet)
                @php($s = $stats[$subnet->id])
                <tr>
                    <td><a href="{{ route('antareja.ipam.subnets.show', $subnet) }}"><strong class="tw:font-mono">{{ $subnet->cidr }}</strong></a></td>
                    <td>{{ $subnet->name }}</td>
                    <td class="tw:text-xs">{{ $subnet->purposeLabel() }}</td>
                    <td>{{ $subnet->site?->code ?? '—' }}</td>
                    <td>{{ $subnet->vlan?->vlan_number ?? '—' }}</td>
                    <td class="tw:font-mono tw:text-xs">{{ $subnet->gatewayText() ?? '—' }}</td>
                    <td class="tw:text-right tw:tabular-nums">{{ $subnet->family === 4 ? number_format($s['usable']) : 'IPv6' }}</td>
                    <td class="tw:text-right tw:tabular-nums">{{ $s['used'] + $s['static'] + $s['dhcp_records'] }}</td>
                    <td class="tw:text-right tw:tabular-nums">{{ $s['reserved'] }}</td>
                    <td class="tw:text-right tw:tabular-nums">{{ $subnet->family === 4 ? number_format($s['available']) : '—' }}</td>
                    <td>@if($subnet->family === 4)@include('antareja::partials.bar', ['value' => $s['utilization']])@endif</td>
                    <td>@if($s['conflicts'])<span class="label label-danger" title="IP konflik">{{ $s['conflicts'] }} konflik</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="12" class="tw:text-center tw:py-6 ant-muted">
                    Belum ada subnet. @can('ant-ipam.update')<a href="{{ route('antareja.ipam.subnets.create') }}">Tambah subnet</a> atau <a href="{{ route('antareja.ipam.import') }}">impor dari perangkat</a>.@endcan
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <p class="tw:text-xs ant-muted">Utilisasi = (IP tercatat + pool DHCP) ÷ IP usable. Available = IP yang tidak tercatat, bukan gateway, dan di luar pool DHCP.</p>
@endsection
