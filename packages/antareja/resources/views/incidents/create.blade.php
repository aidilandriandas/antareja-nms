@extends('antareja::layouts.page')

@section('title', 'Buat Incident')
@section('breadcrumb')
    <li><a href="{{ route('antareja.incidents.index') }}">Incident Management</a></li>
    <li class="active">Buat</li>
@endsection
@section('heading', 'Buat incident')
@section('subheading', 'Untuk gangguan yang dilaporkan pengguna atau tidak terdeteksi otomatis. Gangguan dari monitoring lebih baik dibuat dari halaman problem.')

@section('page')
<form method="post" action="{{ route('antareja.incidents.store') }}" class="panel panel-default tw:max-w-3xl">
    @csrf
    <div class="panel-body">
        <div class="form-group">
            <label for="title">Judul *</label>
            <input id="title" name="title" class="form-control" required maxlength="255" value="{{ old('title') }}" placeholder="mis. CCTV gerbang Pit A tidak tampil di NVR">
        </div>
        <div class="row">
            <div class="form-group col-sm-4">
                <label for="severity">Severity *</label>
                <select id="severity" name="severity" class="form-control" required>
                    @foreach(\Antareja\Support\Severity::INCIDENT as $k => $m)<option value="{{ $k }}" @selected(old('severity', 'medium') === $k)>{{ $m['label'] }}</option>@endforeach
                </select>
            </div>
            <div class="form-group col-sm-4">
                <label for="started_at">Mulai gangguan</label>
                <input id="started_at" name="started_at" type="datetime-local" class="form-control" value="{{ old('started_at', now()->format('Y-m-d\TH:i')) }}">
            </div>
            <div class="form-group col-sm-4">
                <label for="site_id">Site</label>
                <select id="site_id" name="site_id" class="form-control">
                    <option value="">— ikut perangkat —</option>
                    @foreach($sites as $s)<option value="{{ $s->id }}" @selected((string) old('site_id') === (string) $s->id)>{{ $s->code }} · {{ $s->name }}</option>@endforeach
                </select>
            </div>
        </div>
        <div class="form-group">
            <label for="device_id">Perangkat terdampak</label>
            <select id="device_id" name="device_id" class="form-control">
                <option value="">—</option>
                @foreach($devices as $d)<option value="{{ $d->device_id }}" @selected((string) old('device_id', $preselect) === (string) $d->device_id)>{{ $d->displayName() }} ({{ $d->overwrite_ip ?: $d->ip ?: $d->hostname }})</option>@endforeach
            </select>
        </div>
        <div class="row">
            <div class="form-group col-sm-6">
                <label for="assigned_to">PIC (user)</label>
                <select id="assigned_to" name="assigned_to" class="form-control">
                    <option value="">—</option>
                    @foreach($users as $u)<option value="{{ $u->user_id }}" @selected((string) old('assigned_to') === (string) $u->user_id)>{{ $u->realname ?: $u->username }}</option>@endforeach
                </select>
            </div>
            <div class="form-group col-sm-6">
                <label for="pic_name">atau nama PIC (vendor / pihak lain)</label>
                <input id="pic_name" name="pic_name" class="form-control" maxlength="128" value="{{ old('pic_name') }}">
            </div>
        </div>
        <div class="form-group">
            <label for="description">Deskripsi</label>
            <textarea id="description" name="description" class="form-control" rows="4" placeholder="Gejala, siapa yang melapor, dampak ke operasional">{{ old('description') }}</textarea>
        </div>
    </div>
    <div class="panel-footer tw:flex tw:justify-between">
        <a href="{{ route('antareja.incidents.index') }}" class="btn btn-default">Batal</a>
        <button class="btn btn-primary"><i class="fa fa-save"></i> Buat incident</button>
    </div>
</form>
@endsection
