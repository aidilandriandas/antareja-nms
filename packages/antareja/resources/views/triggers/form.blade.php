@extends('antareja::layouts.page')

@php($T = \Antareja\Models\Trigger::class)

@section('title', $trigger->exists ? "Edit {$trigger->name}" : 'Tambah trigger')
@section('breadcrumb')
    <li><a href="{{ route('antareja.triggers.index') }}">Trigger</a></li>
    <li class="active">{{ $trigger->exists ? $trigger->name : 'Tambah' }}</li>
@endsection
@section('heading', $trigger->exists ? "Edit trigger {$trigger->name}" : 'Tambah trigger')

@section('page')
<div class="tw:max-w-4xl">
    {{-- Langkah 1: pilih perangkat untuk menampilkan port & sensornya --}}
    <form method="get" class="panel panel-default">
        <div class="panel-body tw:flex tw:flex-wrap tw:items-end tw:gap-3">
            <div class="tw:flex-1 tw:min-w-64">
                <label for="device">Perangkat (untuk item port / sensor / perangkat)</label>
                <select id="device" name="device" class="form-control" onchange="this.form.submit()">
                    <option value="">— hanya target Quality Probe —</option>
                    @foreach($devices as $d)<option value="{{ $d->device_id }}" @selected($device?->device_id === $d->device_id)>{{ $d->displayName() }} ({{ $d->ip ?: $d->hostname }})</option>@endforeach
                </select>
            </div>
            <noscript><button class="btn btn-default">Tampilkan item</button></noscript>
        </div>
    </form>

    <form method="post" action="{{ $trigger->exists ? route('antareja.triggers.update', $trigger) : route('antareja.triggers.store') }}">
        @csrf @if($trigger->exists) @method('PUT') @endif
        <div class="panel panel-default">
            <div class="panel-body">
                <div class="row">
                    <div class="form-group col-sm-8"><label for="name">Nama trigger *</label><input id="name" name="name" class="form-control" required maxlength="128" value="{{ old('name', $trigger->name) }}" placeholder="Packet loss tinggi PTP Pit A"></div>
                    <div class="form-group col-sm-4">
                        <label for="severity">Severity *</label>
                        <select id="severity" name="severity" class="form-control">
                            @foreach(\Antareja\Support\Severity::PROBLEM as $k => $m)<option value="{{ $k }}" @selected(old('severity', $trigger->severity) === $k)>{{ $m['label'] }}</option>@endforeach
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="item">Item *</label>
                    <select id="item" name="item" class="form-control" required>
                        <option value="">— pilih —</option>
                        <optgroup label="Quality Probe">
                            @foreach($qualityTargets as $q)<option value="quality:{{ $q->id }}" data-source="quality" @selected($selectedItem === "quality:{$q->id}")>{{ $q->name }} ({{ $q->host }})</option>@endforeach
                        </optgroup>
                        @if($device)
                            <optgroup label="Perangkat {{ $device->displayName() }}">
                                <option value="device:{{ $device->device_id }}" data-source="device" @selected($selectedItem === "device:{$device->device_id}")>{{ $device->displayName() }} (uptime, durasi polling)</option>
                            </optgroup>
                            @if($ports->isNotEmpty())
                                <optgroup label="Port">
                                    @foreach($ports as $p)<option value="port:{{ $p->port_id }}" data-source="port" @selected($selectedItem === "port:{$p->port_id}")>{{ $p->getShortLabel() }}{{ $p->ifAlias && $p->ifAlias !== $p->ifDescr ? ' — ' . $p->ifAlias : '' }} ({{ $p->ifOperStatus }}{{ $p->ifSpeed ? ', ' . \LibreNMS\Util\Number::formatSi($p->ifSpeed, 0, 0, 'bps') : '' }})</option>@endforeach
                                </optgroup>
                            @endif
                            @if($sensors->isNotEmpty())
                                <optgroup label="Sensor hardware">
                                    @foreach($sensors as $s)<option value="sensor:{{ $s->sensor_id }}" data-source="sensor" @selected($selectedItem === "sensor:{$s->sensor_id}")>{{ $s->sensor_class }} · {{ $s->sensor_descr }} (kini {{ $s->sensor_current }})</option>@endforeach
                                </optgroup>
                            @endif
                            @if($wireless->isNotEmpty())
                                <optgroup label="Sensor wireless">
                                    @foreach($wireless as $s)<option value="wireless:{{ $s->sensor_id }}" data-source="wireless" @selected($selectedItem === "wireless:{$s->sensor_id}")>{{ $s->sensor_class }} · {{ $s->sensor_descr }} (kini {{ $s->sensor_current }})</option>@endforeach
                                </optgroup>
                            @endif
                        @endif
                    </select>
                    <p class="help-block tw:text-xs">Pilih perangkat di atas untuk menampilkan port, sensor, dan metrik perangkatnya.</p>
                </div>
                <div class="row">
                    <div class="form-group col-sm-3">
                        <label for="function">Fungsi *</label>
                        <select id="function" name="function" class="form-control">
                            @foreach($T::FUNCTIONS as $k => $l)<option value="{{ $k }}" @selected(old('function', $trigger->function) === $k)>{{ $k }} — {{ $l }}</option>@endforeach
                        </select>
                    </div>
                    <div class="form-group col-sm-4">
                        <label for="metric">Metrik *</label>
                        <select id="metric" name="metric" class="form-control" required>
                            @foreach($T::METRICS as $src => $metrics)
                                <optgroup label="{{ $T::SOURCES[$src] }}" data-source="{{ $src }}">
                                    @foreach($metrics as $k => [$label, $unit])<option value="{{ $k }}" data-source="{{ $src }}" @selected(old('metric', $trigger->metric) === $k && ($trigger->source ?? null) === $src)>{{ $label }}{{ $unit ? " ({$unit})" : '' }}</option>@endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-sm-2"><label for="window_minutes">Jendela (menit) *</label><input id="window_minutes" name="window_minutes" type="number" min="1" max="10080" class="form-control" required value="{{ old('window_minutes', $trigger->window_minutes) }}"></div>
                    <div class="form-group col-sm-1">
                        <label for="operator">Op *</label>
                        <select id="operator" name="operator" class="form-control">
                            @foreach($T::OPERATORS as $k => $l)<option value="{{ $k }}" @selected(old('operator', $trigger->operator) === $k)>{{ $l }}</option>@endforeach
                        </select>
                    </div>
                    <div class="form-group col-sm-2"><label for="threshold">Ambang *</label><input id="threshold" name="threshold" type="number" step="any" class="form-control" required value="{{ old('threshold', $trigger->threshold) }}"></div>
                </div>
                <div class="row">
                    <div class="form-group col-sm-4">
                        <label for="recovery_threshold">Ambang pemulihan</label>
                        <input id="recovery_threshold" name="recovery_threshold" type="number" step="any" class="form-control" value="{{ old('recovery_threshold', $trigger->recovery_threshold) }}" placeholder="kosong = sama dengan ambang">
                        <p class="help-block tw:text-xs">Mencegah problem naik-turun. Mis. problem bila &gt; 80, pulih bila sudah ≤ 70.</p>
                    </div>
                    <div class="form-group col-sm-8">
                        <label for="description">Catatan / tindakan</label>
                        <textarea id="description" name="description" class="form-control" rows="2" placeholder="Langkah pengecekan bila trigger aktif">{{ old('description', $trigger->description) }}</textarea>
                    </div>
                </div>
                <div class="checkbox"><label><input type="hidden" name="enabled" value="0"><input type="checkbox" name="enabled" value="1" @checked(old('enabled', $trigger->enabled ?? true))> Aktif</label></div>
                <div class="ant-sunken tw:p-3 tw:rounded tw:text-xs">
                    <strong>Contoh</strong>: Quality Probe · Packet loss · avg 15 menit &gt; 5 ·
                    Port uplink · Utilisasi masuk · avg 10 menit &gt; 80 (pulih 70) ·
                    Sensor suhu · last &gt; 60 ·
                    Perangkat · Uptime · last &lt; 600 (perangkat baru reboot).
                    Fungsi <em>last</em> mengabaikan jendela waktu.
                </div>
            </div>
        </div>
        <button class="btn btn-primary"><i class="fa fa-save"></i> Simpan</button>
        <a href="{{ $trigger->exists ? route('antareja.triggers.show', $trigger) : route('antareja.triggers.index') }}" class="btn btn-link">Batal</a>
    </form>
</div>
<script>
    (function () {
        // Tampilkan hanya metrik yang cocok dengan sumber item terpilih
        var item = document.getElementById('item'), metric = document.getElementById('metric');
        function sync() {
            var opt = item.options[item.selectedIndex], src = opt ? opt.getAttribute('data-source') : null, firstOk = null, keep = false;
            Array.prototype.forEach.call(metric.querySelectorAll('optgroup'), function (g) {
                var on = !src || g.getAttribute('data-source') === src;
                g.hidden = !on; g.disabled = !on;
                if (on) { Array.prototype.forEach.call(g.children, function (o) { firstOk = firstOk || o; if (o.selected) keep = true; }); }
            });
            if (!keep && firstOk) { firstOk.selected = true; }
        }
        item.addEventListener('change', sync); sync();
    })();
</script>
@endsection
