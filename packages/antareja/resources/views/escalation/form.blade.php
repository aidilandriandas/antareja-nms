@extends('antareja::layouts.page')

@php($steps = old('steps', $policy->steps ?? []))
@php($steps = array_pad(array_values($steps), max(3, count($steps)), ['after_minutes' => '', 'transport_ids' => []]))

@section('title', $policy->exists ? "Edit {$policy->name}" : 'Kebijakan Eskalasi')
@section('breadcrumb')
    <li><a href="{{ route('antareja.escalation.index') }}">Eskalasi &amp; Notifikasi</a></li>
    <li class="active">{{ $policy->exists ? $policy->name : 'Tambah' }}</li>
@endsection
@section('heading', $policy->exists ? "Edit kebijakan {$policy->name}" : 'Tambah kebijakan eskalasi')

@section('page')
<form method="post" action="{{ $policy->exists ? route('antareja.escalation.update', $policy) : route('antareja.escalation.store') }}" class="tw:max-w-4xl">
    @csrf @if($policy->exists) @method('PUT') @endif
    <div class="panel panel-default">
        <div class="panel-body">
            <div class="row">
                <div class="form-group col-sm-6"><label for="name">Nama *</label><input id="name" name="name" class="form-control" required maxlength="128" value="{{ old('name', $policy->name) }}" placeholder="Gangguan berat — NOC & supervisor"></div>
                <div class="form-group col-sm-3">
                    <label for="min_severity">Severity minimum *</label>
                    <select id="min_severity" name="min_severity" class="form-control">
                        @foreach(\Antareja\Support\Severity::INCIDENT as $k => $m)<option value="{{ $k }}" @selected(old('min_severity', $policy->min_severity) === $k)>{{ $m['label'] }}</option>@endforeach
                    </select>
                </div>
                <div class="form-group col-sm-3">
                    <label for="site_id">Site</label>
                    <select id="site_id" name="site_id" class="form-control">
                        <option value="">Semua site</option>
                        @foreach($sites as $s)<option value="{{ $s->id }}" @selected((string) old('site_id', $policy->site_id) === (string) $s->id)>{{ $s->code }}</option>@endforeach
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="form-group col-sm-6">
                    <label class="tw:font-normal"><input type="hidden" name="auto_create_incident" value="0"><input type="checkbox" name="auto_create_incident" value="1" @checked(old('auto_create_incident', $policy->auto_create_incident))> Buat incident otomatis dari problem</label>
                    <div class="tw:flex tw:items-center tw:gap-2 tw:mt-1"><span class="tw:text-sm">setelah problem berlangsung</span>
                        <input id="auto_create_after_minutes" name="auto_create_after_minutes" type="number" min="0" max="1440" class="form-control tw:w-24" value="{{ old('auto_create_after_minutes', $policy->auto_create_after_minutes) }}" aria-label="Menit">
                        <span class="tw:text-sm">menit</span></div>
                    <p class="help-block tw:text-xs">Menunggu beberapa menit mencegah incident untuk gangguan sesaat (flapping).</p>
                </div>
                <div class="form-group col-sm-6">
                    <label class="tw:font-normal tw:block"><input type="hidden" name="notify_resolved" value="0"><input type="checkbox" name="notify_resolved" value="1" @checked(old('notify_resolved', $policy->notify_resolved))> Kirim notifikasi saat incident resolved</label>
                    <label class="tw:font-normal tw:block"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" @checked(old('active', $policy->active))> Kebijakan aktif</label>
                </div>
            </div>
        </div>
    </div>

    <div class="panel panel-default">
        <div class="panel-heading"><strong>Tahap eskalasi</strong> <span class="tw:text-xs ant-muted">Tahap tanpa kanal diabaikan. Eskalasi berhenti begitu incident di-acknowledge.</span></div>
        <div class="table-responsive">
            <table class="table table-condensed tw:m-0">
                <thead><tr><th class="tw:w-20">Tahap</th><th class="tw:w-44">Menit sejak gangguan</th><th>Kanal notifikasi (transport)</th></tr></thead>
                <tbody>
                @foreach($steps as $n => $step)
                    <tr>
                        <td>{{ $n + 1 }}</td>
                        <td><input name="steps[{{ $n }}][after_minutes]" type="number" min="0" max="10080" class="form-control input-sm" value="{{ $step['after_minutes'] ?? '' }}" aria-label="Menit tahap {{ $n + 1 }}" placeholder="{{ [0, 30, 120][$n] ?? '' }}"></td>
                        <td>
                            @forelse($transports as $t)
                                <label class="tw:font-normal tw:mr-4"><input type="checkbox" name="steps[{{ $n }}][transport_ids][]" value="{{ $t->transport_id }}" @checked(in_array($t->transport_id, $step['transport_ids'] ?? []))> {{ $t->transport_name }} <span class="ant-muted tw:text-xs">({{ $t->transport_type }})</span></label>
                            @empty
                                <span class="ant-muted tw:text-sm">Belum ada transport. Buat di <a href="{{ url('alert-transports') }}">Alert Transports</a>.</span>
                            @endforelse
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="panel-footer tw:flex tw:justify-between">
            <a href="{{ route('antareja.escalation.index') }}" class="btn btn-default">Batal</a>
            <button class="btn btn-primary"><i class="fa fa-save"></i> Simpan</button>
        </div>
    </div>
</form>
@if($policy->exists)
    <form method="post" action="{{ route('antareja.escalation.destroy', $policy) }}" onsubmit="return confirm('Hapus kebijakan ini?')">@csrf @method('DELETE')
        <button class="btn btn-link ant-text-crit tw:p-0!"><i class="fa fa-trash"></i> Hapus kebijakan</button></form>
@endif
@endsection
