@extends('antareja::layouts.page')

@php($WO = \Antareja\Models\WorkOrder::class)

@section('title', $wo->exists ? "Edit {$wo->number}" : 'Buat work order')
@section('breadcrumb')
    <li><a href="{{ route('antareja.workorders.index') }}">Work Order</a></li>
    <li class="active">{{ $wo->exists ? $wo->number : 'Buat' }}</li>
@endsection
@section('heading', $wo->exists ? "Edit {$wo->number}" : 'Buat work order')

@section('page')
<form method="post" action="{{ $wo->exists ? route('antareja.workorders.update', $wo) : route('antareja.workorders.store') }}" class="tw:max-w-4xl">
    @csrf @if($wo->exists) @method('PUT') @endif
    @foreach(['incident_id', 'maintenance_window_id', 'change_id'] as $f)
        @if(old($f, $wo->$f))<input type="hidden" name="{{ $f }}" value="{{ old($f, $wo->$f) }}">@endif
    @endforeach
    <div class="panel panel-default">
        <div class="panel-body">
            @if($wo->incident_id || $wo->maintenance_window_id || $wo->change_id)
                <p class="tw:text-sm ant-muted">Terkait:
                    @if($wo->incident_id) incident #{{ $wo->incident_id }} @endif
                    @if($wo->maintenance_window_id) maintenance #{{ $wo->maintenance_window_id }} @endif
                    @if($wo->change_id) change #{{ $wo->change_id }} @endif
                </p>
            @endif
            <div class="form-group"><label for="title">Judul pekerjaan *</label><input id="title" name="title" class="form-control" required maxlength="255" value="{{ old('title', $wo->title) }}" placeholder="Ganti patch cord FO uplink switch Pit A"></div>
            <div class="row">
                <div class="form-group col-sm-4">
                    <label for="type">Jenis *</label>
                    <select id="type" name="type" class="form-control">@foreach($WO::TYPES as $k => $l)<option value="{{ $k }}" @selected(old('type', $wo->type) === $k)>{{ $l }}</option>@endforeach</select>
                </div>
                <div class="form-group col-sm-4">
                    <label for="priority">Prioritas *</label>
                    <select id="priority" name="priority" class="form-control">@foreach($WO::PRIORITIES as $k => $m)<option value="{{ $k }}" @selected(old('priority', $wo->priority) === $k)>{{ $m['label'] }} (tenggat {{ $m['hours'] < 24 ? $m['hours'] . ' jam' : ($m['hours'] / 24) . ' hari' }})</option>@endforeach</select>
                </div>
                <div class="form-group col-sm-4">
                    <label for="due_at">Tenggat</label>
                    <input id="due_at" name="due_at" type="datetime-local" class="form-control" value="{{ old('due_at', $wo->due_at?->format('Y-m-d\TH:i')) }}">
                    <p class="help-block tw:text-xs">Kosong = otomatis dari prioritas.</p>
                </div>
            </div>
            <div class="row">
                <div class="form-group col-sm-4">
                    <label for="site_id">Site</label>
                    <select id="site_id" name="site_id" class="form-control"><option value="">—</option>@foreach($sites as $s)<option value="{{ $s->id }}" @selected((string) old('site_id', $wo->site_id) === (string) $s->id)>{{ $s->code }} · {{ $s->name }}</option>@endforeach</select>
                </div>
                <div class="form-group col-sm-4">
                    <label for="area_id">Area</label>
                    <select id="area_id" name="area_id" class="form-control"><option value="">—</option>@foreach($areas as $a)<option value="{{ $a->id }}" data-site="{{ $a->site_id }}" @selected((string) old('area_id', $wo->area_id) === (string) $a->id)>{{ $a->name }}</option>@endforeach</select>
                </div>
                <div class="form-group col-sm-4">
                    <label for="device_id">Perangkat</label>
                    <select id="device_id" name="device_id" class="form-control"><option value="">—</option>@foreach($devices as $d)<option value="{{ $d->device_id }}" @selected((string) old('device_id', $wo->device_id) === (string) $d->device_id)>{{ $d->displayName() }}</option>@endforeach</select>
                </div>
            </div>
            <div class="row">
                <div class="form-group col-sm-6">
                    <label for="assigned_to">Pelaksana</label>
                    <select id="assigned_to" name="assigned_to" class="form-control"><option value="">—</option>@foreach($users as $u)<option value="{{ $u->user_id }}" @selected((string) old('assigned_to', $wo->assigned_to) === (string) $u->user_id)>{{ $u->realname ?: $u->username }}</option>@endforeach</select>
                </div>
                <div class="form-group col-sm-6"><label for="team">atau tim / vendor</label><input id="team" name="team" class="form-control" maxlength="128" value="{{ old('team', $wo->team) }}" placeholder="Tim FO shift B / PT vendor"></div>
            </div>
            <div class="form-group">
                <label for="description">Uraian pekerjaan</label>
                <textarea id="description" name="description" class="form-control" rows="4" placeholder="Lokasi persis, gejala, alat & material yang perlu dibawa, kontak di lokasi">{{ old('description', $wo->description) }}</textarea>
            </div>
            @unless($wo->exists)<p class="help-block tw:text-xs">Checklist standar sesuai jenis pekerjaan dibuat otomatis dan bisa diubah setelah work order dibuat.</p>@endunless
        </div>
    </div>
    <button class="btn btn-primary"><i class="fa fa-save"></i> {{ $wo->exists ? 'Simpan' : 'Buat work order' }}</button>
    <a href="{{ $wo->exists ? route('antareja.workorders.show', $wo) : route('antareja.workorders.index') }}" class="btn btn-link">Batal</a>
</form>
<script>
    (function () {
        var site = document.getElementById('site_id'), area = document.getElementById('area_id');
        function sync() { Array.prototype.forEach.call(area.options, function (o) { if (o.value) { o.hidden = site.value && o.getAttribute('data-site') !== site.value; } }); }
        site.addEventListener('change', function () { if (area.selectedOptions[0] && area.selectedOptions[0].hidden) area.value = ''; sync(); }); sync();
        if (window.jQuery && jQuery.fn.select2) { jQuery('#device_id').select2({ width: '100%', theme: 'bootstrap', placeholder: '—', allowClear: true }); }
    })();
</script>
@endsection
