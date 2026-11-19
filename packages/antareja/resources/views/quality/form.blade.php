@extends('antareja::layouts.page')

@section('title', $target->exists ? "Edit {$target->name}" : 'Tambah Target')
@section('breadcrumb')
    <li><a href="{{ route('antareja.quality.index') }}">Network Quality</a></li>
    <li class="active">{{ $target->exists ? $target->name : 'Tambah target' }}</li>
@endsection
@section('heading', $target->exists ? "Edit target {$target->name}" : 'Tambah target kualitas')

@section('page')
<form method="post" action="{{ $target->exists ? route('antareja.quality.update', $target) : route('antareja.quality.store') }}" class="tw:max-w-3xl">
    @csrf @if($target->exists) @method('PUT') @endif
    <div class="panel panel-default">
        <div class="panel-heading"><strong>Target</strong></div>
        <div class="panel-body">
            <div class="form-group">
                <label for="device_id">Perangkat (opsional)</label>
                <select id="device_id" name="device_id" class="form-control">
                    <option value="">— bukan perangkat yang dimonitor —</option>
                    @foreach($devices as $device)
                        <option value="{{ $device->device_id }}" data-host="{{ $device->overwrite_ip ?: $device->ip ?: $device->hostname }}" data-name="{{ $device->displayName() }}"
                            @selected((string) old('device_id', $target->device_id) === (string) $device->device_id)>{{ $device->displayName() }} ({{ $device->overwrite_ip ?: $device->ip ?: $device->hostname }})</option>
                    @endforeach
                </select>
                <p class="help-block tw:text-xs">Bila dipilih, perubahan status kualitas dicatat di event log perangkat.</p>
            </div>
            <div class="row">
                <div class="form-group col-sm-6"><label for="name">Nama *</label><input id="name" name="name" class="form-control" required maxlength="128" value="{{ old('name', $target->name) }}" placeholder="WAN Jakarta – Site Pit A"></div>
                <div class="form-group col-sm-6"><label for="host">Host / IP *</label><input id="host" name="host" class="form-control tw:font-mono" required maxlength="255" value="{{ old('host', $target->host) }}" placeholder="10.10.0.1"></div>
            </div>
            <div class="row">
                <div class="form-group col-sm-6">
                    <label for="group_name">Grup</label>
                    <input id="group_name" name="group_name" class="form-control" maxlength="64" list="groups" value="{{ old('group_name', $target->group_name) }}" placeholder="WAN, PTP Backhaul, Antar-site">
                    <datalist id="groups">@foreach($groups as $g)<option value="{{ $g }}">@endforeach</datalist>
                </div>
                <div class="form-group col-sm-6">
                    <label for="site_id">Site</label>
                    <select id="site_id" name="site_id" class="form-control">
                        <option value="">—</option>
                        @foreach($sites as $site)<option value="{{ $site->id }}" @selected((string) old('site_id', $target->site_id) === (string) $site->id)>{{ $site->code }} · {{ $site->name }}</option>@endforeach
                    </select>
                </div>
            </div>
            <div class="form-group"><label for="description">Keterangan</label><textarea id="description" name="description" class="form-control" rows="2">{{ old('description', $target->description) }}</textarea></div>
        </div>
    </div>
    <div class="panel panel-default">
        <div class="panel-heading"><strong>Pengukuran &amp; ambang</strong></div>
        <div class="panel-body">
            <div class="row">
                <div class="form-group col-sm-3"><label for="pings">Ping / siklus</label><input id="pings" name="pings" type="number" min="5" max="60" class="form-control" value="{{ old('pings', $target->pings) }}"></div>
                <div class="form-group col-sm-3"><label for="latency_warn_ms">Latency menurun (ms)</label><input id="latency_warn_ms" name="latency_warn_ms" type="number" min="1" class="form-control" value="{{ old('latency_warn_ms', $target->latency_warn_ms) }}"></div>
                <div class="form-group col-sm-3"><label for="latency_crit_ms">Latency buruk (ms)</label><input id="latency_crit_ms" name="latency_crit_ms" type="number" min="1" class="form-control" value="{{ old('latency_crit_ms', $target->latency_crit_ms) }}"></div>
                <div class="form-group col-sm-3"><label for="jitter_warn_ms">Jitter menurun (ms)</label><input id="jitter_warn_ms" name="jitter_warn_ms" type="number" min="1" class="form-control" value="{{ old('jitter_warn_ms', $target->jitter_warn_ms) }}"></div>
            </div>
            <div class="row">
                <div class="form-group col-sm-3"><label for="loss_warn_pct">Loss menurun (%)</label><input id="loss_warn_pct" name="loss_warn_pct" type="number" step="0.1" min="0" max="100" class="form-control" value="{{ old('loss_warn_pct', $target->loss_warn_pct) }}"></div>
                <div class="form-group col-sm-3"><label for="loss_crit_pct">Loss buruk (%)</label><input id="loss_crit_pct" name="loss_crit_pct" type="number" step="0.1" min="0" max="100" class="form-control" value="{{ old('loss_crit_pct', $target->loss_crit_pct) }}"></div>
                <div class="form-group col-sm-6 tw:pt-6">
                    <label class="tw:font-normal"><input type="hidden" name="enabled" value="0"><input type="checkbox" name="enabled" value="1" @checked(old('enabled', $target->enabled))> Aktif (diukur setiap 5 menit)</label>
                </div>
            </div>
            <p class="help-block tw:text-xs">Untuk link radio/VSAT, naikkan ambang latency (mis. VSAT 700 / 1200 ms).</p>
        </div>
        <div class="panel-footer tw:flex tw:justify-between">
            <a href="{{ $target->exists ? route('antareja.quality.show', $target) : route('antareja.quality.index') }}" class="btn btn-default">Batal</a>
            <button class="btn btn-primary"><i class="fa fa-save"></i> Simpan</button>
        </div>
    </div>
</form>
@if($target->exists)
    <form method="post" action="{{ route('antareja.quality.destroy', $target) }}" onsubmit="return confirm('Hapus target dan seluruh data pengukurannya?')">@csrf @method('DELETE')
        <button class="btn btn-link ant-text-crit tw:p-0!"><i class="fa fa-trash"></i> Hapus target</button></form>
@endif
<script>
    document.getElementById('device_id').addEventListener('change', function () {
        const opt = this.selectedOptions[0];
        if (!opt || !opt.dataset.host) return;
        const host = document.getElementById('host'), name = document.getElementById('name');
        if (!host.value) host.value = opt.dataset.host;
        if (!name.value) name.value = opt.dataset.name;
    });
</script>
@endsection
