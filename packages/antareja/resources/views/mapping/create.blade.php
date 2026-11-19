@extends('antareja::layouts.page')

@section('title', 'Tambah Mapping')
@section('breadcrumb')
    <li><a href="{{ route('antareja.mapping.index') }}">Device Mapping</a></li>
    <li class="active">Tambah</li>
@endsection
@section('heading', 'Tambah device mapping')

@section('page')
<form method="post" action="{{ route('antareja.mapping.store') }}" class="tw:max-w-4xl">
    @csrf
    <div class="row">
        @foreach(['a' => ['A — upstream / parent', 'mis. switch, router, NVR, base station'], 'b' => ['B — downstream / child', 'mis. access point, kamera, radio client']] as $side => [$title, $hint])
            <div class="col-md-6">
                <div class="panel panel-default">
                    <div class="panel-heading"><strong>{{ $title }}</strong><br><span class="tw:text-xs ant-muted">{{ $hint }}</span></div>
                    <div class="panel-body">
                        <div class="form-group">
                            <label for="{{ $side }}_device_id">Perangkat *</label>
                            <select id="{{ $side }}_device_id" name="{{ $side }}_device_id" class="form-control ant-device-select" data-port-target="{{ $side }}_port_id" required>
                                <option value="">— pilih —</option>
                                @foreach($devices as $device)
                                    <option value="{{ $device->device_id }}" @selected((string) old($side . '_device_id', $side === 'b' ? $preselect : '') === (string) $device->device_id)>
                                        {{ $device->displayName() }} ({{ $device->overwrite_ip ?: $device->ip ?: $device->hostname }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="{{ $side }}_port_id">Port</label>
                            <select id="{{ $side }}_port_id" name="{{ $side }}_port_id" class="form-control" data-old="{{ old($side . '_port_id') }}">
                                <option value="">— tidak spesifik —</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    <div class="panel panel-default">
        <div class="panel-body">
            <div class="form-group">
                <label for="link_type">Tipe relasi *</label>
                <select id="link_type" name="link_type" class="form-control" required>
                    @foreach($types as $key => $label)<option value="{{ $key }}" @selected(old('link_type') === $key)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="form-group">
                <label for="notes">Catatan / bukti</label>
                <textarea id="notes" name="notes" class="form-control" rows="2" placeholder="mis. dicek fisik 12 Nov, kabel label C-PITA-07">{{ old('notes') }}</textarea>
            </div>
            @can('ant-mapping.verify')
                <div class="checkbox"><label><input type="checkbox" name="verify_now" value="1" checked> Langsung verifikasi (sudah dicek di lapangan)</label></div>
            @endcan
        </div>
        <div class="panel-footer tw:flex tw:justify-between">
            <a href="{{ route('antareja.mapping.index') }}" class="btn btn-default">Batal</a>
            <button class="btn btn-primary"><i class="fa fa-save"></i> Simpan</button>
        </div>
    </div>
</form>

<script>
    (function () {
        const portsUrl = @json(route('antareja.api.device-ports', ['device' => '__ID__']));
        async function loadPorts(deviceSelect) {
            const target = document.getElementById(deviceSelect.dataset.portTarget);
            const old = target.dataset.old;
            target.length = 1;
            if (!deviceSelect.value) return;
            const res = await fetch(portsUrl.replace('__ID__', deviceSelect.value), {headers: {'Accept': 'application/json'}});
            if (!res.ok) return;
            for (const port of await res.json()) {
                const opt = new Option((port.up ? '● ' : '○ ') + port.label, port.id);
                if (String(port.id) === old) opt.selected = true;
                target.add(opt);
            }
        }
        document.querySelectorAll('.ant-device-select').forEach(function (sel) {
            if (window.jQuery && jQuery.fn.select2) {
                jQuery(sel).select2({width: '100%', theme: 'bootstrap'}).on('change', function () { loadPorts(sel); });
            } else {
                sel.addEventListener('change', function () { loadPorts(sel); });
            }
            loadPorts(sel);
        });
    })();
</script>
@endsection
