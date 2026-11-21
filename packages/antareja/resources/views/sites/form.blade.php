@extends('antareja::layouts.page')

@section('title', $site->exists ? "Edit Site {$site->code}" : 'Tambah Site')
@section('breadcrumb')
    <li><a href="{{ route('antareja.sites.index') }}">Site &amp; Area</a></li>
    <li class="active">{{ $site->exists ? $site->code : 'Tambah' }}</li>
@endsection
@section('heading', $site->exists ? "Edit Site {$site->code}" : 'Tambah Site')

@section('page')
<div class="row">
    <div class="col-lg-7">
        <form method="post" action="{{ $site->exists ? route('antareja.sites.update', $site) : route('antareja.sites.store') }}" class="panel panel-default">
            @csrf
            @if($site->exists) @method('PUT') @endif
            <div class="panel-heading"><strong>Data site</strong></div>
            <div class="panel-body">
                <div class="row">
                    <div class="form-group col-sm-4">
                        <label for="code">Kode site *</label>
                        <input id="code" name="code" class="form-control" required maxlength="32" value="{{ old('code', $site->code) }}" placeholder="PIT-A">
                    </div>
                    <div class="form-group col-sm-8">
                        <label for="name">Nama site *</label>
                        <input id="name" name="name" class="form-control" required maxlength="128" value="{{ old('name', $site->name) }}" placeholder="Pit A Utara">
                    </div>
                </div>
                <div class="row">
                    <div class="form-group col-sm-6">
                        <label for="type">Tipe *</label>
                        <select id="type" name="type" class="form-control" required>
                            @foreach($types as $key => $label)
                                <option value="{{ $key }}" @selected(old('type', $site->type) === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-sm-6">
                        <label for="location_id">Lokasi SNMP (sysLocation)</label>
                        <select id="location_id" name="location_id" class="form-control">
                            <option value="">— tidak dipetakan —</option>
                            @foreach($locations as $location)
                                <option value="{{ $location->id }}" @selected((string) old('location_id', $site->location_id) === (string) $location->id)>{{ $location->location }}</option>
                            @endforeach
                        </select>
                        <p class="help-block tw:text-xs">Device baru dengan sysLocation ini otomatis disarankan masuk site ini.</p>
                    </div>
                </div>
                <div class="form-group">
                    <label for="address">Alamat / keterangan lokasi</label>
                    <input id="address" name="address" class="form-control" maxlength="255" value="{{ old('address', $site->address) }}">
                </div>
                <div class="row">
                    <div class="form-group col-sm-6">
                        <label for="latitude">Latitude</label>
                        <input id="latitude" name="latitude" type="number" step="any" class="form-control" value="{{ old('latitude', $site->latitude) }}" placeholder="-2.123456">
                    </div>
                    <div class="form-group col-sm-6">
                        <label for="longitude">Longitude</label>
                        <input id="longitude" name="longitude" type="number" step="any" class="form-control" value="{{ old('longitude', $site->longitude) }}" placeholder="115.123456">
                    </div>
                </div>
                <div class="row">
                    <div class="form-group col-sm-4">
                        <label for="pic_user_id">PIC (user)</label>
                        <select id="pic_user_id" name="pic_user_id" class="form-control">
                            <option value="">—</option>
                            @foreach($users as $user)
                                <option value="{{ $user->user_id }}" @selected((string) old('pic_user_id', $site->pic_user_id) === (string) $user->user_id)>{{ $user->realname ?: $user->username }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-sm-4">
                        <label for="pic_name">atau nama PIC</label>
                        <input id="pic_name" name="pic_name" class="form-control" maxlength="128" value="{{ old('pic_name', $site->pic_name) }}">
                    </div>
                    <div class="form-group col-sm-4">
                        <label for="pic_phone">Kontak PIC</label>
                        <input id="pic_phone" name="pic_phone" class="form-control" maxlength="64" value="{{ old('pic_phone', $site->pic_phone) }}">
                    </div>
                </div>
                <div class="form-group">
                    <label for="description">Deskripsi</label>
                    <textarea id="description" name="description" class="form-control" rows="3">{{ old('description', $site->description) }}</textarea>
                </div>
                <div class="checkbox">
                    <label><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $site->is_active))> Site aktif</label>
                </div>
            </div>
            <div class="panel-footer tw:flex tw:justify-between">
                <a href="{{ $site->exists ? route('antareja.sites.show', $site) : route('antareja.sites.index') }}" class="btn btn-default">Batal</a>
                <button class="btn btn-primary"><i class="fa fa-save"></i> Simpan</button>
            </div>
        </form>

        @if($site->exists)
            <form method="post" action="{{ route('antareja.sites.destroy', $site) }}" onsubmit="return confirm('Hapus site {{ $site->code }}?')">
                @csrf @method('DELETE')
                <button class="btn btn-link ant-text-crit tw:p-0!"><i class="fa fa-trash"></i> Hapus site</button>
            </form>
        @endif
    </div>

    @if($site->exists)
    <div class="col-lg-5">
        <div class="panel panel-default">
            <div class="panel-heading"><strong>Area di {{ $site->code }}</strong></div>
            <ul class="list-group">
                @forelse($site->areas as $area)
                    <li class="list-group-item tw:flex tw:justify-between tw:items-center">
                        <span><strong>{{ $area->name }}</strong> @if($area->code)<span class="ant-muted">({{ $area->code }})</span>@endif
                            @if($area->description)<br><span class="tw:text-xs ant-muted">{{ $area->description }}</span>@endif</span>
                        <form method="post" action="{{ route('antareja.sites.areas.destroy', [$site, $area]) }}" onsubmit="return confirm('Hapus area {{ $area->name }}?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-xs btn-default" title="Hapus"><i class="fa fa-times"></i></button>
                        </form>
                    </li>
                @empty
                    <li class="list-group-item ant-muted">Belum ada area.</li>
                @endforelse
            </ul>
            <form method="post" action="{{ route('antareja.sites.areas.store', $site) }}" class="panel-body">
                @csrf
                <div class="row">
                    <div class="form-group col-sm-7"><input name="name" class="form-control" required maxlength="128" placeholder="Nama area, mis. Ruang Server"></div>
                    <div class="form-group col-sm-5"><input name="code" class="form-control" maxlength="32" placeholder="Kode (opsional)"></div>
                </div>
                <div class="form-group"><input name="description" class="form-control" maxlength="1000" placeholder="Keterangan (opsional)"></div>
                <button class="btn btn-default"><i class="fa fa-plus"></i> Tambah area</button>
            </form>
        </div>
    </div>
    @endif
</div>
@endsection
