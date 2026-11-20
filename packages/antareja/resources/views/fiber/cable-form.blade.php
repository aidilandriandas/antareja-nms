@extends('antareja::layouts.page')

@php
    $C = \Antareja\Models\FiberCable::class;
    $aEnd = old('a_end', $cable->a_type ? $cable->a_type . ':' . $cable->a_id : '');
    $bEnd = old('b_end', $cable->b_type ? $cable->b_type . ':' . $cable->b_id : '');
@endphp

@section('title', $cable->exists ? "Edit {$cable->code}" : 'Tambah kabel')
@section('breadcrumb')
    <li><a href="{{ route('antareja.fiber.index') }}">Fiber Optic</a></li>
    <li class="active">{{ $cable->exists ? $cable->code : 'Tambah kabel' }}</li>
@endsection
@section('heading', $cable->exists ? "Edit kabel {$cable->code}" : 'Tambah kabel fiber')

@section('page')
<form method="post" action="{{ $cable->exists ? route('antareja.fiber.cables.update', $cable) : route('antareja.fiber.cables.store') }}" class="tw:max-w-4xl">
    @csrf @if($cable->exists) @method('PUT') @endif
    <div class="panel panel-default">
        <div class="panel-body">
            <div class="row">
                <div class="form-group col-sm-4"><label for="code">Kode *</label><input id="code" name="code" class="form-control" required maxlength="64" value="{{ old('code', $cable->code) }}" placeholder="FO-OFC-PITA-01"></div>
                <div class="form-group col-sm-8"><label for="name">Nama / rute singkat *</label><input id="name" name="name" class="form-control" required maxlength="255" value="{{ old('name', $cable->name) }}" placeholder="Kantor → Pit A via hauling road"></div>
            </div>
            <div class="row">
                @foreach(['a' => 'Ujung A', 'b' => 'Ujung B'] as $s => $l)
                    <div class="form-group col-sm-6">
                        <label for="{{ $s }}_end">{{ $l }} *</label>
                        <select id="{{ $s }}_end" name="{{ $s }}_end" class="form-control" required>
                            <option value="">— pilih —</option>
                            <optgroup label="ODF">@foreach($odfs as $o)<option value="odf:{{ $o->id }}" @selected(($s === 'a' ? $aEnd : $bEnd) === "odf:{$o->id}")>{{ $o->label() }}</option>@endforeach</optgroup>
                            <optgroup label="Joint / ODP">@foreach($joints as $j)<option value="joint:{{ $j->id }}" @selected(($s === 'a' ? $aEnd : $bEnd) === "joint:{$j->id}")>{{ $j->label() }}</option>@endforeach</optgroup>
                        </select>
                    </div>
                @endforeach
            </div>
            <div class="row">
                <div class="form-group col-sm-2"><label for="mode">Mode</label><select id="mode" name="mode" class="form-control"><option value="sm" @selected(old('mode', $cable->mode) === 'sm')>Single-mode</option><option value="mm" @selected(old('mode', $cable->mode) === 'mm')>Multi-mode</option></select></div>
                <div class="form-group col-sm-2"><label for="fiber_type">Tipe serat</label><input id="fiber_type" name="fiber_type" class="form-control" maxlength="16" value="{{ old('fiber_type', $cable->fiber_type) }}" placeholder="G.652D"></div>
                <div class="form-group col-sm-2"><label for="core_count">Jumlah core *</label><input id="core_count" name="core_count" type="number" min="1" max="864" class="form-control" required value="{{ old('core_count', $cable->core_count) }}"></div>
                <div class="form-group col-sm-2"><label for="cores_per_tube">Core / tube</label><input id="cores_per_tube" name="cores_per_tube" type="number" min="1" max="24" class="form-control" value="{{ old('cores_per_tube', $cable->cores_per_tube) }}"></div>
                <div class="form-group col-sm-2"><label for="length_m">Panjang (m)</label><input id="length_m" name="length_m" type="number" min="1" max="200000" class="form-control" value="{{ old('length_m', $cable->length_m) }}"></div>
                <div class="form-group col-sm-2"><label for="installation">Instalasi</label><select id="installation" name="installation" class="form-control">@foreach($C::INSTALLATION as $k => $l)<option value="{{ $k }}" @selected(old('installation', $cable->installation) === $k)>{{ $l }}</option>@endforeach</select></div>
            </div>
            <div class="row">
                <div class="form-group col-sm-3"><label for="status">Status</label><select id="status" name="status" class="form-control">@foreach($C::STATUSES as $k => $m)<option value="{{ $k }}" @selected(old('status', $cable->status) === $k)>{{ $m['label'] }}</option>@endforeach</select></div>
                <div class="form-group col-sm-3"><label for="installed_on">Tanggal pasang</label><input id="installed_on" name="installed_on" type="date" class="form-control" value="{{ old('installed_on', $cable->installed_on?->toDateString()) }}"></div>
                <div class="form-group col-sm-6"><label for="vendor">Vendor / kontraktor</label><input id="vendor" name="vendor" class="form-control" maxlength="128" value="{{ old('vendor', $cable->vendor) }}"></div>
            </div>
            <div class="form-group"><label for="route">Rute &amp; catatan</label><textarea id="route" name="route" class="form-control" rows="3" placeholder="Tiang 1–37 sisi kiri hauling road, crossing jalan di KM 2 lewat duct">{{ old('route', $cable->route) }}</textarea></div>
            @unless($cable->exists)<p class="help-block tw:text-xs">Core 1..N dibuat otomatis dengan warna TIA-598 per tube.</p>@endunless
        </div>
    </div>
    <button class="btn btn-primary"><i class="fa fa-save"></i> Simpan</button>
    <a href="{{ $cable->exists ? route('antareja.fiber.cables.show', $cable) : route('antareja.fiber.index') }}" class="btn btn-link">Batal</a>
</form>
@endsection
