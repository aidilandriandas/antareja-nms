@extends('antareja::layouts.page')

@section('title', $subnet->exists ? "Edit {$subnet->cidr}" : 'Tambah Subnet')
@section('breadcrumb')
    <li><a href="{{ route('antareja.ipam.index') }}">IPAM</a></li>
    <li class="active">{{ $subnet->exists ? $subnet->cidr : 'Tambah subnet' }}</li>
@endsection
@section('heading', $subnet->exists ? "Edit subnet {$subnet->cidr}" : 'Tambah subnet')

@section('page')
<form method="post" action="{{ $subnet->exists ? route('antareja.ipam.subnets.update', $subnet) : route('antareja.ipam.subnets.store') }}" class="panel panel-default tw:max-w-3xl">
    @csrf @if($subnet->exists) @method('PUT') @endif
    <div class="panel-body">
        <div class="row">
            <div class="form-group col-sm-5">
                <label for="cidr">CIDR *</label>
                @if($subnet->exists)
                    <input id="cidr" class="form-control tw:font-mono" value="{{ $subnet->cidr }}" disabled>
                @else
                    <input id="cidr" name="cidr" class="form-control tw:font-mono" required value="{{ old('cidr', $subnet->cidr) }}" placeholder="10.10.20.0/24">
                    <p class="help-block tw:text-xs">IPv4 atau IPv6. Alamat akan dinormalkan ke alamat network.</p>
                @endif
            </div>
            <div class="form-group col-sm-7">
                <label for="name">Nama *</label>
                <input id="name" name="name" class="form-control" required maxlength="128" value="{{ old('name', $subnet->name) }}" placeholder="MGMT Pit A">
            </div>
        </div>
        <div class="row">
            <div class="form-group col-sm-4">
                <label for="purpose">Fungsi</label>
                <select id="purpose" name="purpose" class="form-control">
                    <option value="">—</option>
                    @foreach($purposes as $key => $label)<option value="{{ $key }}" @selected(old('purpose', $subnet->purpose) === $key)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="form-group col-sm-4">
                <label for="site_id">Site</label>
                <select id="site_id" name="site_id" class="form-control">
                    <option value="">—</option>
                    @foreach($sites as $site)<option value="{{ $site->id }}" @selected((string) old('site_id', $subnet->site_id) === (string) $site->id)>{{ $site->code }} · {{ $site->name }}</option>@endforeach
                </select>
            </div>
            <div class="form-group col-sm-4">
                <label for="vlan_id">VLAN</label>
                <select id="vlan_id" name="vlan_id" class="form-control">
                    <option value="">—</option>
                    @foreach($vlans as $vlan)<option value="{{ $vlan->id }}" @selected((string) old('vlan_id', $subnet->vlan_id) === (string) $vlan->id)>{{ $vlan->label() }}</option>@endforeach
                </select>
            </div>
        </div>
        <div class="row">
            <div class="form-group col-sm-4">
                <label for="gateway">Gateway</label>
                <input id="gateway" name="gateway" class="form-control tw:font-mono" value="{{ old('gateway', $subnet->gatewayText()) }}">
            </div>
            <div class="form-group col-sm-4">
                <label for="dhcp_start">Pool DHCP: awal</label>
                <input id="dhcp_start" name="dhcp_start" class="form-control tw:font-mono" value="{{ old('dhcp_start', $subnet->dhcpStartText()) }}">
            </div>
            <div class="form-group col-sm-4">
                <label for="dhcp_end">Pool DHCP: akhir</label>
                <input id="dhcp_end" name="dhcp_end" class="form-control tw:font-mono" value="{{ old('dhcp_end', $subnet->dhcpEndText()) }}">
            </div>
        </div>
        <div class="form-group">
            <label for="description">Keterangan</label>
            <textarea id="description" name="description" class="form-control" rows="2">{{ old('description', $subnet->description) }}</textarea>
        </div>
        <div class="checkbox"><label><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $subnet->is_active ?? true))> Aktif</label></div>
    </div>
    <div class="panel-footer tw:flex tw:justify-between">
        <a href="{{ $subnet->exists ? route('antareja.ipam.subnets.show', $subnet) : route('antareja.ipam.index') }}" class="btn btn-default">Batal</a>
        <button class="btn btn-primary"><i class="fa fa-save"></i> Simpan</button>
    </div>
</form>
@if($subnet->exists)
    <form method="post" action="{{ route('antareja.ipam.subnets.destroy', $subnet) }}" onsubmit="return confirm('Hapus subnet {{ $subnet->cidr }}? Record IP tetap disimpan.')">
        @csrf @method('DELETE')<button class="btn btn-link tw:text-red-600! tw:p-0!"><i class="fa fa-trash"></i> Hapus subnet</button>
    </form>
@endif
@endsection
