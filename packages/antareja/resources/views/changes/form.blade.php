@extends('antareja::layouts.page')

@php($C = \Antareja\Models\Change::class)

@section('title', $change->exists ? "Edit {$change->number}" : 'Ajukan perubahan')
@section('breadcrumb')
    <li><a href="{{ route('antareja.changes.index') }}">Change Request</a></li>
    <li class="active">{{ $change->exists ? $change->number : 'Ajukan' }}</li>
@endsection
@section('heading', $change->exists ? "Edit {$change->number}" : 'Ajukan perubahan')

@section('page')
<form method="post" action="{{ $change->exists ? route('antareja.changes.update', $change) : route('antareja.changes.store') }}" class="tw:max-w-4xl">
    @csrf @if($change->exists) @method('PUT') @endif
    <div class="panel panel-default">
        <div class="panel-body">
            <div class="form-group"><label for="title">Judul perubahan *</label><input id="title" name="title" class="form-control" required maxlength="255" value="{{ old('title', $change->title) }}" placeholder="Pindah uplink switch Pit A dari radio ke FO"></div>
            <div class="row">
                <div class="form-group col-sm-4">
                    <label for="type">Jenis *</label>
                    <select id="type" name="type" class="form-control">@foreach($C::TYPES as $k => $l)<option value="{{ $k }}" @selected(old('type', $change->type) === $k)>{{ $l }}</option>@endforeach</select>
                </div>
                <div class="form-group col-sm-4">
                    <label for="risk">Risiko *</label>
                    <select id="risk" name="risk" class="form-control">@foreach($C::RISKS as $k => $m)<option value="{{ $k }}" @selected(old('risk', $change->risk) === $k)>{{ $m['label'] }}</option>@endforeach</select>
                </div>
                <div class="form-group col-sm-4">
                    <label for="site_id">Site</label>
                    <select id="site_id" name="site_id" class="form-control"><option value="">—</option>@foreach($sites as $s)<option value="{{ $s->id }}" @selected((string) old('site_id', $change->site_id) === (string) $s->id)>{{ $s->code }} · {{ $s->name }}</option>@endforeach</select>
                </div>
            </div>
            <div class="row">
                <div class="form-group col-sm-6"><label for="planned_start">Jadwal mulai</label><input id="planned_start" name="planned_start" type="datetime-local" class="form-control" value="{{ old('planned_start', $change->planned_start?->format('Y-m-d\TH:i')) }}"></div>
                <div class="form-group col-sm-6"><label for="planned_end">Jadwal selesai</label><input id="planned_end" name="planned_end" type="datetime-local" class="form-control" value="{{ old('planned_end', $change->planned_end?->format('Y-m-d\TH:i')) }}"></div>
            </div>
            <div class="form-group">
                <label for="devices">Perangkat terdampak</label>
                <select id="devices" name="devices[]" class="form-control" multiple size="8">
                    @foreach($devices as $d)<option value="{{ $d->device_id }}" @selected(in_array($d->device_id, old('devices', $selected)))>{{ $d->displayName() }} ({{ $d->overwrite_ip ?: $d->ip ?: $d->hostname }})</option>@endforeach
                </select>
                <p class="help-block tw:text-xs">Perubahan konfigurasi perangkat ini selama jendela change dianggap sah oleh backup konfigurasi.</p>
            </div>
            <div class="form-group"><label for="description">Alasan &amp; rencana implementasi</label><textarea id="description" name="description" class="form-control" rows="5" placeholder="Langkah demi langkah">{{ old('description', $change->description) }}</textarea></div>
            <div class="row">
                <div class="form-group col-sm-6"><label for="impact">Dampak ke operasional</label><textarea id="impact" name="impact" class="form-control" rows="3" placeholder="Layanan/area yang terganggu, perkiraan downtime">{{ old('impact', $change->impact) }}</textarea></div>
                <div class="form-group col-sm-6"><label for="rollback_plan">Rencana rollback</label><textarea id="rollback_plan" name="rollback_plan" class="form-control" rows="3" placeholder="Cara kembali ke kondisi awal bila gagal">{{ old('rollback_plan', $change->rollback_plan) }}</textarea></div>
            </div>
            <div class="form-group"><label for="test_plan">Rencana uji</label><textarea id="test_plan" name="test_plan" class="form-control" rows="2" placeholder="Cara memastikan perubahan berhasil">{{ old('test_plan', $change->test_plan) }}</textarea></div>
            <div class="checkbox"><label><input type="hidden" name="exclude_from_sla" value="0"><input type="checkbox" name="exclude_from_sla" value="1" @checked(old('exclude_from_sla', $change->exclude_from_sla))> Kecualikan jendela change dari perhitungan SLA</label></div>
        </div>
    </div>
    @if($change->exists)
        <button class="btn btn-primary"><i class="fa fa-save"></i> Simpan</button>
    @else
        <button name="submit_now" value="1" class="btn btn-primary"><i class="fa fa-paper-plane"></i> Ajukan untuk persetujuan</button>
        <button class="btn btn-default">Simpan sebagai draft</button>
    @endif
    <a href="{{ $change->exists ? route('antareja.changes.show', $change) : route('antareja.changes.index') }}" class="btn btn-link">Batal</a>
</form>
<script>
    if (window.jQuery && jQuery.fn.select2) { jQuery('#devices').select2({ width: '100%', theme: 'bootstrap', placeholder: 'Pilih perangkat' }); }
</script>
@endsection
