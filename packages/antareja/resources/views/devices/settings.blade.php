@extends('antareja::layouts.page')

@php($state = $device->disabled || $device->ignore ? ['unknown', 'Nonaktif'] : ($device->status ? ['ok', 'Online'] : ['down', 'Down']))
@section('title', $device->displayName())
@section('breadcrumb')
    <li><a href="{{ route('devices') }}">Perangkat</a></li>
    <li class="active">{{ $device->displayName() }}</li>
@endsection
@section('heading')
    {{ $device->displayName() }} <x-antareja::status :status="$state[0]" size="sm">{{ $state[1] }}</x-antareja::status>
@endsection
@section('subheading', trim(($device->overwrite_ip ?: $device->hostname) . ' · ' . ($device->hardware ?: $device->os) . ' · ' . (Antareja\Support\Catalog::GROUP_NAMES[$detail->category] ?? 'Belum ada grup'), ' ·'))
@section('actions')
    <button type="button" class="btn btn-default btn-sm" id="ant-ct-open"><i class="fa-solid fa-stethoscope"></i> Uji koneksi</button>
    <a href="{{ url('device/' . $device->device_id) }}" class="btn btn-default btn-sm"><i class="fa-solid fa-chart-line"></i> Grafik & monitoring</a>
    <a href="{{ route('antareja.topology.index') }}" class="btn btn-default btn-sm"><i class="fa-solid fa-diagram-project"></i> Peta topologi</a>
@endsection

@section('page')
    <section class="panel panel-default" id="ant-ct-panel" hidden>
        <div class="panel-heading tw:flex tw:items-center tw:justify-between">
            <strong><i class="fa-solid fa-stethoscope"></i> Uji koneksi ke {{ $device->overwrite_ip ?: $device->hostname }}</strong>
            @if($device->snmp_disable)<span class="ant-badge ant-badge--sm ant-badge--warn">Terdaftar: ping saja</span>@else<span class="ant-badge ant-badge--sm ant-badge--info">Terdaftar: SNMP {{ $device->snmpver }}</span>@endif
        </div>
        <div class="panel-body tw:pb-0">
            <form class="row" id="ant-ct-form">
                <div class="form-group col-sm-3">
                    <label for="ct-mode">Uji dengan</label>
                    <select id="ct-mode" class="form-control input-sm">
                        <option value="v2c" @selected($device->snmp_disable || $device->snmpver !== 'v3')>Ping + SNMP v2c</option>
                        <option value="v3" @selected(! $device->snmp_disable && $device->snmpver === 'v3')>Ping + SNMP v3</option>
                        <option value="ping">Ping saja</option>
                    </select>
                </div>
                <div class="form-group col-sm-5" data-ct="v2c">
                    <label for="ct-community">Community</label>
                    <input id="ct-community" type="password" class="form-control input-sm" autocomplete="off" placeholder="kosong = pakai yang tersimpan / Pengaturan Sistem">
                </div>
                <div class="form-group col-sm-5" data-ct="v3" hidden>
                    <label>SNMP v3</label>
                    <p class="form-control-static tw:text-sm ant-muted">Memakai user & password v3 yang tersimpan di perangkat.</p>
                </div>
                <div class="form-group col-sm-4 tw:flex tw:items-end">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-play"></i> Jalankan uji</button>
                </div>
            </form>
        </div>
        <div id="ant-ct-result" hidden></div>
    </section>

    <div class="row">
        <div class="col-lg-7">
            <form method="post" action="{{ route('antareja.devices.update', $device) }}" class="panel panel-default">
                @csrf @method('PUT')
                <div class="panel-heading"><strong>Identitas di NMS</strong></div>
                <div class="panel-body">
                    <div class="form-group">
                        <label for="display_name">Nama perangkat</label>
                        <input id="display_name" name="display_name" class="form-control" maxlength="128" @disabled(! $canEdit)
                               value="{{ old('display_name', $device->display_template) }}" placeholder="mis. Switch Server Mess">
                        <p class="help-block">Tampil di daftar, peta, Layar NOC, dan notifikasi. Kosong = pakai sysName / IP.</p>
                    </div>
                    <div class="row">
                        <div class="form-group col-sm-4">
                            <label for="category">Grup</label>
                            <select id="category" name="category" class="form-control" @disabled(! $canEdit)>
                                <option value="">— belum ada —</option>
                                @foreach($categories as $k => $label)<option value="{{ $k }}" @selected(old('category', $detail->category) === $k)>{{ $label }}</option>@endforeach
                            </select>
                        </div>
                        <div class="form-group col-sm-4">
                            <label for="site_id">Site</label>
                            <select id="site_id" name="site_id" class="form-control" @disabled(! $canEdit)>
                                <option value="">— belum ada —</option>
                                @foreach($sites as $s)<option value="{{ $s->id }}" @selected((string) old('site_id', $detail->site_id) === (string) $s->id)>{{ $s->code }} — {{ $s->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="form-group col-sm-4">
                            <label for="area_id">Area</label>
                            <select id="area_id" name="area_id" class="form-control" @disabled(! $canEdit)>
                                <option value="">— belum ada —</option>
                                @foreach($sites as $s)
                                    @foreach($s->areas as $a)<option value="{{ $a->id }}" data-site="{{ $s->id }}" @selected((string) old('area_id', $detail->area_id) === (string) $a->id)>{{ $a->name }}</option>@endforeach
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                @if($canEdit)
                    <div class="panel-footer tw:text-right"><button class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Simpan</button></div>
                @endif
            </form>

            <div class="panel panel-default">
                <div class="panel-heading tw:flex tw:items-center tw:justify-between"><strong>Terhubung ke</strong>
                    <a href="{{ route('antareja.topology.index') }}" class="tw:text-sm">Atur di Peta Topologi &rarr;</a></div>
                @if($links->isEmpty())
                    <div class="panel-body ant-muted tw:text-sm">Belum ada sambungan. Buat di Peta Topologi → <b>Hubungkan perangkat</b>, atau aktifkan LLDP/CDP.</div>
                @else
                    <table class="table table-condensed tw:m-0">
                        <thead><tr><th>Arah</th><th>Perangkat</th><th>Port di sini</th><th>Port lawan</th><th>Status</th></tr></thead>
                        <tbody>
                        @foreach($links as $l)
                            @php($up = $l->b_device_id === $device->device_id)
                            @php($other = $up ? $l->aDevice : $l->bDevice)
                            <tr>
                                <td>{{ $up ? '↑ induk' : '↓ anak' }}</td>
                                <td>@if($other)<a href="{{ route('antareja.devices.edit', $other->device_id) }}">{{ $other->displayName() }}</a>@else — @endif</td>
                                <td class="ant-data">{{ ($up ? $l->bPort : $l->aPort)?->getShortLabel() ?? '—' }}</td>
                                <td class="ant-data">{{ ($up ? $l->aPort : $l->bPort)?->getShortLabel() ?? '—' }}</td>
                                <td>@if($l->status === 'verified')<x-antareja::status status="ok" size="sm">Terverifikasi</x-antareja::status>@else<x-antareja::status status="info" size="sm">Saran</x-antareja::status>@endif</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        <div class="col-lg-5">
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Monitoring</strong></div>
                <table class="table table-condensed tw:m-0">
                    <tr><th class="tw:w-40">Status</th><td><x-antareja::status :status="$state[0]" size="sm">{{ $state[1] }}</x-antareja::status></td></tr>
                    <tr><th>IP / hostname</th><td class="ant-data">{{ $device->overwrite_ip ?: $device->hostname }}</td></tr>
                    <tr><th>Pemantauan</th><td>{{ $device->snmp_disable ? 'Ping saja (tanpa SNMP)' : 'SNMP ' . $device->snmpver }}</td></tr>
                    <tr><th>OS / model</th><td>{{ trim(($device->os ?: '') . ($device->hardware ? ' · ' . $device->hardware : ''), ' ·') ?: '—' }}</td></tr>
                    <tr><th>Uptime</th><td class="ant-data">{{ $device->status && $device->uptime ? \LibreNMS\Util\Time::formatInterval($device->uptime, true) : '—' }}</td></tr>
                    <tr><th>Terakhir dipolling</th><td>{{ $device->last_polled ? \Illuminate\Support\Carbon::parse($device->last_polled)->locale('id')->diffForHumans() : '—' }}</td></tr>
                    <tr><th>Jumlah port</th><td>{{ $device->ports()->count() }}</td></tr>
                </table>
                <div class="panel-footer tw:text-sm">
                    Pengaturan lanjutan (SNMP, poller group, dll.): <a href="{{ route('device.edit', $device->device_id) }}">⚙ Pengaturan LibreNMS</a>
                </div>
            </div>
        </div>
    </div>

    @include('antareja::devices.test-script')
    <script>
    (function () {
        var panel = document.getElementById('ant-ct-panel'), mode = document.getElementById('ct-mode');
        function payload() { return { mode: mode.value, community: document.getElementById('ct-community').value || null }; }
        function sync() { panel.querySelectorAll('[data-ct]').forEach(function (e) { e.hidden = e.dataset.ct !== mode.value; }); }
        mode.addEventListener('change', sync); sync();
        function go() {
            AntConnTest.run('{{ route('antareja.devices.test-existing', $device->device_id) }}', payload(), document.getElementById('ant-ct-result'),
                { enableUrl: '{{ route('antareja.devices.enable-snmp', $device->device_id) }}' });
        }
        document.getElementById('ant-ct-open').addEventListener('click', function () { panel.hidden = false; panel.scrollIntoView({ block: 'start', behavior: 'smooth' }); go(); });
        document.getElementById('ant-ct-form').addEventListener('submit', function (e) { e.preventDefault(); go(); });
        var site = document.getElementById('site_id'), area = document.getElementById('area_id');
        function filterAreas() { area.querySelectorAll('option[data-site]').forEach(function (o) { o.hidden = o.dataset.site !== site.value; if (o.hidden && o.selected) area.value = ''; }); }
        site.addEventListener('change', filterAreas); filterAreas();
    })();
    </script>
@endsection
