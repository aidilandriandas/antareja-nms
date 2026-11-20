@extends('antareja::layouts.page')

@section('title', $service->exists ? "Edit {$service->name}" : 'Tambah layanan')
@section('breadcrumb')
    <li><a href="{{ route('antareja.sla.index') }}">SLA Management</a></li>
    <li class="active">{{ $service->exists ? $service->name : 'Tambah layanan' }}</li>
@endsection
@section('heading', $service->exists ? "Edit layanan {$service->name}" : 'Tambah layanan')

@section('page')
<form method="post" action="{{ $service->exists ? route('antareja.sla.update', $service) : route('antareja.sla.store') }}" class="tw:max-w-4xl">
    @csrf @if($service->exists) @method('PUT') @endif
    <div class="panel panel-default">
        <div class="panel-body">
            <div class="row">
                <div class="form-group col-sm-6"><label for="name">Nama layanan *</label><input id="name" name="name" class="form-control" required maxlength="128" value="{{ old('name', $service->name) }}" placeholder="Jaringan Pit A"></div>
                <div class="form-group col-sm-3">
                    <label for="parent_id">Layanan induk</label>
                    <select id="parent_id" name="parent_id" class="form-control">
                        <option value="">— (layanan utama)</option>
                        @foreach($parents as $p)<option value="{{ $p->id }}" @selected((string) old('parent_id', $service->parent_id) === (string) $p->id)>{{ $p->name }}</option>@endforeach
                    </select>
                </div>
                <div class="form-group col-sm-3">
                    <label for="site_id">Site</label>
                    <select id="site_id" name="site_id" class="form-control">
                        <option value="">—</option>
                        @foreach($sites as $s)<option value="{{ $s->id }}" @selected((string) old('site_id', $service->site_id) === (string) $s->id)>{{ $s->code }} · {{ $s->name }}</option>@endforeach
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="form-group col-sm-6">
                    <label for="calc">Aturan status *</label>
                    <select id="calc" name="calc" class="form-control">
                        @foreach(\Antareja\Models\Service::CALC as $k => $l)<option value="{{ $k }}" @selected(old('calc', $service->calc) === $k)>{{ $l }}</option>@endforeach
                    </select>
                </div>
                <div class="form-group col-sm-2"><label for="min_up">Minimal up (N)</label><input id="min_up" name="min_up" type="number" min="1" max="1000" class="form-control" value="{{ old('min_up', $service->min_up) }}"></div>
                <div class="form-group col-sm-2"><label for="sla_target">Target SLA (%) *</label><input id="sla_target" name="sla_target" type="number" step="0.001" min="50" max="100" class="form-control" required value="{{ old('sla_target', $service->sla_target) }}"></div>
                <div class="form-group col-sm-2"><label for="sort">Urutan</label><input id="sort" name="sort" type="number" min="0" max="9999" class="form-control" value="{{ old('sort', $service->sort ?? 0) }}"></div>
            </div>
            <div class="form-group">
                <label for="devices">Perangkat anggota</label>
                <select id="devices" name="devices[]" class="form-control" multiple size="10">
                    @foreach($devices as $d)<option value="{{ $d->device_id }}" @selected(in_array($d->device_id, old('devices', $selected)))>{{ $d->displayName() }} ({{ $d->overwrite_ip ?: $d->ip ?: $d->hostname }})</option>@endforeach
                </select>
                <p class="help-block tw:text-xs">Ctrl/Cmd + klik untuk memilih beberapa. Layanan anak juga dihitung sebagai anggota.</p>
            </div>
            <div class="form-group">
                <label for="description">Deskripsi</label>
                <textarea id="description" name="description" class="form-control" rows="3" placeholder="Pengguna layanan, jam operasional, catatan kontrak">{{ old('description', $service->description) }}</textarea>
            </div>
            <div class="checkbox"><label><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" @checked(old('active', $service->active))> Aktif (dihitung di SLA & laporan)</label></div>
        </div>
    </div>
    <button class="btn btn-primary"><i class="fa fa-save"></i> Simpan</button>
    <a href="{{ $service->exists ? route('antareja.sla.show', $service) : route('antareja.sla.index') }}" class="btn btn-link">Batal</a>
</form>
<script>
    if (window.jQuery && jQuery.fn.select2) { jQuery('#devices').select2({ width: '100%', theme: 'bootstrap', placeholder: 'Pilih perangkat' }); }
</script>
@endsection
