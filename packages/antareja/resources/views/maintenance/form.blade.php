@extends('antareja::layouts.page')

@section('title', $window->exists ? "Edit {$window->title}" : 'Jadwalkan Maintenance')
@section('breadcrumb')
    <li><a href="{{ route('antareja.maintenance.index') }}">Maintenance Management</a></li>
    <li class="active">{{ $window->exists ? $window->title : 'Jadwalkan' }}</li>
@endsection
@section('heading', $window->exists ? 'Edit maintenance' : 'Jadwalkan maintenance')

@section('page')
<form method="post" action="{{ $window->exists ? route('antareja.maintenance.update', $window) : route('antareja.maintenance.store') }}" class="tw:max-w-4xl">
    @csrf @if($window->exists) @method('PUT') @endif
    <div class="panel panel-default">
        <div class="panel-body">
            <div class="form-group">
                <label for="title">Judul *</label>
                <input id="title" name="title" class="form-control" required maxlength="255" value="{{ old('title', $window->title) }}" placeholder="mis. Penggantian UPS ruang server Pit A">
            </div>
            <div class="row">
                <div class="form-group col-sm-4">
                    <label for="type">Jenis *</label>
                    <select id="type" name="type" class="form-control">
                        @foreach(\Antareja\Models\MaintenanceWindow::TYPES as $k => $l)<option value="{{ $k }}" @selected(old('type', $window->type) === $k)>{{ $l }}</option>@endforeach
                    </select>
                </div>
                <div class="form-group col-sm-4">
                    <label for="starts_at">Mulai *</label>
                    <input id="starts_at" name="starts_at" type="datetime-local" class="form-control" required value="{{ old('starts_at', $window->starts_at?->format('Y-m-d\TH:i')) }}">
                </div>
                <div class="form-group col-sm-4">
                    <label for="ends_at">Selesai *</label>
                    <input id="ends_at" name="ends_at" type="datetime-local" class="form-control" required value="{{ old('ends_at', $window->ends_at?->format('Y-m-d\TH:i')) }}">
                </div>
            </div>
            <div class="row">
                <div class="form-group col-sm-4">
                    <label for="site_id">Site</label>
                    <select id="site_id" name="site_id" class="form-control">
                        <option value="">—</option>
                        @foreach($sites as $s)<option value="{{ $s->id }}" @selected((string) old('site_id', $window->site_id) === (string) $s->id)>{{ $s->code }} · {{ $s->name }}</option>@endforeach
                    </select>
                    <label class="tw:font-normal tw:text-sm tw:mt-1"><input type="checkbox" name="include_site_devices" value="1" @checked(old('include_site_devices'))> semua perangkat di site ini</label>
                </div>
                <div class="form-group col-sm-4">
                    <label for="pic_user_id">PIC</label>
                    <select id="pic_user_id" name="pic_user_id" class="form-control">
                        <option value="">—</option>
                        @foreach($users as $u)<option value="{{ $u->user_id }}" @selected((string) old('pic_user_id', $window->pic_user_id) === (string) $u->user_id)>{{ $u->realname ?: $u->username }}</option>@endforeach
                    </select>
                </div>
                <div class="form-group col-sm-4">
                    <label for="pic_name">atau nama PIC / vendor</label>
                    <input id="pic_name" name="pic_name" class="form-control" maxlength="128" value="{{ old('pic_name', $window->pic_name) }}">
                </div>
            </div>
            <div class="form-group">
                <label for="devices">Perangkat terdampak</label>
                <select id="devices" name="devices[]" class="form-control" multiple size="8">
                    @foreach($devices as $d)<option value="{{ $d->device_id }}" @selected(in_array($d->device_id, old('devices', $selected)))>{{ $d->displayName() }} ({{ $d->overwrite_ip ?: $d->ip ?: $d->hostname }})</option>@endforeach
                </select>
                <p class="help-block tw:text-xs">Ctrl/Cmd + klik untuk memilih beberapa. Alert perangkat ini ditekan selama jendela berlangsung.</p>
            </div>
            <div class="form-group">
                <label for="description">Rencana kerja</label>
                <textarea id="description" name="description" class="form-control" rows="4" placeholder="Langkah kerja, dampak ke operasional, rencana rollback">{{ old('description', $window->description) }}</textarea>
            </div>
            <div class="checkbox"><label><input type="hidden" name="exclude_from_sla" value="0"><input type="checkbox" name="exclude_from_sla" value="1" @checked(old('exclude_from_sla', $window->exclude_from_sla))> Kecualikan waktu maintenance dari perhitungan SLA (maintenance terencana)</label></div>
        </div>
        <div class="panel-footer tw:flex tw:justify-between">
            <a href="{{ $window->exists ? route('antareja.maintenance.show', $window) : route('antareja.maintenance.index') }}" class="btn btn-default">Batal</a>
            <button class="btn btn-primary"><i class="fa fa-save"></i> Simpan</button>
        </div>
    </div>
</form>
<script>
    if (window.jQuery && jQuery.fn.select2) { jQuery('#devices').select2({ width: '100%', theme: 'bootstrap', placeholder: 'Pilih perangkat' }); }
</script>
@endsection
