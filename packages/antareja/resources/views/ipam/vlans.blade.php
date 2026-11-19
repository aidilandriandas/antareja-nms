@extends('antareja::layouts.page')

@section('title', 'VLAN')
@section('breadcrumb')
    <li><a href="{{ route('antareja.ipam.index') }}">IPAM</a></li>
    <li class="active">VLAN</li>
@endsection
@section('heading', 'VLAN')

@section('page')
<div class="row">
    <div class="col-lg-8">
        <div class="panel panel-default">
            <div class="panel-heading"><strong>VLAN plan ({{ $vlans->count() }})</strong></div>
            <div class="table-responsive">
                <table class="table table-condensed table-hover tw:m-0">
                    <thead><tr><th>VLAN</th><th>Nama</th><th>Site</th><th>Fungsi</th><th>Subnet</th><th>Keterangan</th><th></th></tr></thead>
                    <tbody>
                    @forelse($vlans as $vlan)
                        <tr>
                            <td><strong>{{ $vlan->vlan_number }}</strong></td>
                            <td>{{ $vlan->name }}</td>
                            <td>{{ $vlan->site?->code ?? 'Global' }}</td>
                            <td class="tw:text-xs">{{ \Antareja\Models\Subnet::PURPOSES[$vlan->purpose] ?? '—' }}</td>
                            <td><a href="{{ route('antareja.ipam.index', ['vlan' => $vlan->id]) }}">{{ $vlan->subnets_count }}</a></td>
                            <td class="tw:text-xs">{{ $vlan->description }}</td>
                            <td>
                                @can('ant-ipam.update')
                                    <form method="post" action="{{ route('antareja.ipam.vlans.destroy', $vlan) }}" onsubmit="return confirm('Hapus VLAN {{ $vlan->vlan_number }}?')">@csrf @method('DELETE')
                                        <button class="btn btn-xs btn-default"><i class="fa fa-trash"></i></button></form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="tw:text-center tw:py-4 tw:text-gray-500">Belum ada VLAN.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel panel-default">
            <div class="panel-heading"><strong>VLAN yang terlihat di switch</strong> <span class="tw:text-xs tw:text-gray-500">(hasil discovery, sebagai referensi)</span></div>
            <div class="table-responsive">
                <table class="table table-condensed tw:m-0">
                    <thead><tr><th>VLAN</th><th>Nama di switch</th><th>Jumlah switch</th><th>Di VLAN plan</th></tr></thead>
                    <tbody>
                    @forelse($discovered as $d)
                        <tr>
                            <td>{{ $d->vlan_vlan }}</td><td>{{ $d->vlan_name }}</td><td>{{ $d->devices }}</td>
                            <td>{!! $vlans->contains('vlan_number', $d->vlan_vlan) ? '<span class="label label-success">Ya</span>' : '<span class="label label-warning">Belum</span>' !!}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="tw:text-center tw:py-4 tw:text-gray-500">Belum ada VLAN terdeteksi dari switch.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @can('ant-ipam.update')
    <div class="col-lg-4">
        <form method="post" action="{{ route('antareja.ipam.vlans.store') }}" class="panel panel-default">
            @csrf
            <div class="panel-heading"><strong>Tambah VLAN</strong></div>
            <div class="panel-body">
                <div class="row">
                    <div class="form-group col-xs-4"><label for="vlan_number">ID *</label><input id="vlan_number" name="vlan_number" type="number" min="1" max="4094" class="form-control" required value="{{ old('vlan_number') }}"></div>
                    <div class="form-group col-xs-8"><label for="vname">Nama *</label><input id="vname" name="name" class="form-control" required maxlength="64" value="{{ old('name') }}" placeholder="CCTV-PIT-A"></div>
                </div>
                <div class="form-group">
                    <label for="vsite">Site</label>
                    <select id="vsite" name="site_id" class="form-control">
                        <option value="">Global (semua site)</option>
                        @foreach($sites as $site)<option value="{{ $site->id }}">{{ $site->code }} · {{ $site->name }}</option>@endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="vpurpose">Fungsi</label>
                    <select id="vpurpose" name="purpose" class="form-control">
                        <option value="">—</option>
                        @foreach(\Antareja\Models\Subnet::PURPOSES as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div class="form-group"><label for="vdesc">Keterangan</label><input id="vdesc" name="description" class="form-control" maxlength="1000"></div>
                <button class="btn btn-primary btn-block"><i class="fa fa-plus"></i> Tambah</button>
            </div>
        </form>
    </div>
    @endcan
</div>
@endsection
