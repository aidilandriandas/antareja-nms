@extends('antareja::layouts.page')

@php($catalog = \Antareja\Support\Catalog::class)

@section('title', $device->displayName())
@section('breadcrumb')
    <li><a href="{{ route('antareja.inventory.index') }}">Device Inventory</a></li>
    <li class="active">{{ $device->displayName() }}</li>
@endsection
@section('heading')
    {{ $device->displayName() }}
    @include('antareja::partials.verification-badge', ['status' => $status])
@endsection
@section('subheading', trim(($device->overwrite_ip ?: $device->ip) . ' · ' . $device->os . ' · ' . $detail->categoryLabel(), ' ·'))

@section('actions')
    <button type="button" class="btn btn-default" id="ant-ct-open"><i class="fa-solid fa-stethoscope"></i> Uji koneksi</button>
    <a href="{{ url('device/' . $device->device_id) }}" class="btn btn-default"><i class="fa fa-chart-line"></i> Monitoring</a>
    @can('ant-incident.create')
        <a href="{{ route('antareja.incidents.create', ['device' => $device->device_id]) }}" class="btn btn-default"><i class="fa fa-fire-extinguisher"></i> Buat incident</a>
    @endcan
    @can('ant-maintenance.update')
        <a href="{{ route('antareja.maintenance.create', ['device' => $device->device_id]) }}" class="btn btn-default"><i class="fa fa-tools"></i> Jadwalkan maintenance</a>
    @endcan
    @can('ant-workorder.update')
        <a href="{{ route('antareja.workorders.create', ['device' => $device->device_id]) }}" class="btn btn-default"><i class="fa fa-clipboard-list"></i> Work order</a>
    @endcan
    @can('ant-config.view')
        <a href="{{ route('antareja.config.device', $device->device_id) }}" class="btn btn-default"><i class="fa fa-file-code"></i> Konfigurasi</a>
    @endcan
    @can('ant-inventory.update')
        <a href="{{ route('antareja.inventory.edit', $device) }}" class="btn btn-primary"><i class="fa fa-pencil"></i> Edit inventaris</a>
    @endcan
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
        @if($status !== 'verified')
            <div class="alert {{ in_array($status, ['rejected', 'retired']) ? 'alert-danger' : 'alert-warning' }}">
                @switch($status)
                    @case('discovered') <strong>Perangkat baru terdeteksi.</strong> Lengkapi kategori, site, dan PIC lalu verifikasi. @break
                    @case('pending_verification') <strong>Menunggu verifikasi engineer.</strong> @break
                    @case('incomplete_mapping') <strong>Terverifikasi, data belum lengkap:</strong> {{ implode(', ', $completeness['missing']) }}. @break
                    @case('rejected') <strong>Ditolak</strong> — {{ $detail->rejected_reason }} @break
                    @case('retired') <strong>Retired</strong> — {{ $detail->rejected_reason }} @break
                @endswitch
            </div>
        @endif

        <div class="panel panel-default">
            <div class="panel-heading tw:flex tw:justify-between tw:items-center">
                <strong>Data aset</strong>
                @include('antareja::partials.completeness', ['completeness' => $completeness])
            </div>
            <table class="table table-condensed tw:m-0">
                <tr><th class="tw:w-48">Nama perangkat</th><td>{{ $device->displayName() }}</td></tr>
                <tr><th>Hostname</th><td>{{ $device->hostname }}</td></tr>
                <tr><th>IP address</th><td>{{ $device->overwrite_ip ?: $device->ip ?: '—' }}</td></tr>
                <tr><th>MAC address</th><td>{{ $macAddress ?? '—' }}</td></tr>
                <tr><th>Vendor / OS</th><td>{{ $device->os }} @if($device->version)<span class="ant-muted">({{ $device->version }})</span>@endif</td></tr>
                <tr><th>Model</th><td>{{ $detail->effectiveModel() ?? '—' }}</td></tr>
                <tr><th>Serial number</th><td>{{ $detail->effectiveSerial() ?? '—' }} @if(! $detail->serial_number && $device->serial)<span class="tw:text-xs ant-muted">(dari SNMP)</span>@endif</td></tr>
                <tr><th>Asset tag</th><td>{{ $detail->asset_tag ?? '—' }}</td></tr>
                <tr><th>Kategori</th><td>{{ $detail->categoryLabel() }}
                    @if(! $detail->category && $detail->suggested_category)<span class="ant-muted">(saran: {{ $catalog::label($catalog::DEVICE_CATEGORIES, $detail->suggested_category) }})</span>@endif</td></tr>
                <tr><th>Site / Area</th><td>
                    @if($detail->site)<a href="{{ route('antareja.sites.show', $detail->site) }}">{{ $detail->site->code }} · {{ $detail->site->name }}</a>@else — @endif
                    @if($detail->area) / {{ $detail->area->name }}@endif
                </td></tr>
                <tr><th>Departemen</th><td>{{ $detail->department ?? '—' }}</td></tr>
                <tr><th>Lokasi instalasi / Rak</th><td>{{ $detail->installation_location ?? '—' }} @if($detail->rack) · Rak {{ $detail->rack }}@endif</td></tr>
                <tr><th>Lokasi SNMP</th><td>{{ $device->location?->location ?? '—' }}</td></tr>
                <tr><th>Jumlah port</th><td>{{ $portCount }}</td></tr>
                <tr><th>Protokol manajemen</th><td>{{ $catalog::label($catalog::MANAGEMENT_PROTOCOLS, $detail->management_protocol) }}</td></tr>
                <tr><th>PIC</th><td>{{ $detail->picLabel() ?? '—' }} @if($detail->pic_phone)<span class="ant-muted">· {{ $detail->pic_phone }}</span>@endif</td></tr>
                <tr><th>Tanggal instalasi</th><td>{{ $detail->installed_at?->format('d M Y') ?? '—' }}</td></tr>
                <tr><th>Garansi</th><td>
                    @if($detail->warranty_until)
                        s/d {{ $detail->warranty_until->format('d M Y') }}
                        @if($detail->warranty_until->isPast())<span class="label label-danger">Habis</span>
                        @elseif($detail->warranty_until->lt(now()->addDays(90)))<span class="label label-warning">&lt; 90 hari</span>@endif
                    @else — @endif
                    @if($detail->warranty_info)<br><span class="tw:text-xs">{{ $detail->warranty_info }}</span>@endif
                </td></tr>
                <tr><th>Lifecycle</th><td>{{ $catalog::label($catalog::LIFECYCLE, $detail->lifecycle_status) }}</td></tr>
                <tr><th>Ditemukan</th><td>{{ $detail->discovered_at?->format('d M Y H:i') ?? '—' }}</td></tr>
                <tr><th>Diverifikasi</th><td>{{ $detail->verified_at ? $detail->verified_at->format('d M Y H:i') . ' oleh ' . ($detail->verifier?->realname ?: $detail->verifier?->username) : '—' }}</td></tr>
                @if($detail->notes)<tr><th>Catatan</th><td class="tw:whitespace-pre-line">{{ $detail->notes }}</td></tr>@endif
            </table>
        </div>

        <div class="panel panel-default">
            <div class="panel-heading tw:flex tw:justify-between tw:items-center">
                <strong>Device mapping</strong>
                @can('ant-mapping.update')
                    <a href="{{ route('antareja.mapping.create', ['device' => $device->device_id]) }}" class="btn btn-xs btn-default"><i class="fa fa-plus"></i> Tambah mapping</a>
                @endcan
            </div>
            @include('antareja::mapping.partials.table', ['links' => $links, 'focus' => $device->device_id])
        </div>
    </div>

    <div class="col-lg-5">
        <div class="panel panel-default">
            <div class="panel-heading"><strong>Monitoring</strong></div>
            <table class="table table-condensed tw:m-0">
                <tr><th class="tw:w-36">Status</th><td>
                    <x-antareja::device-status :device="$device" size="md" />
                    @if(! $device->disabled && ! $device->status)<span class="tw:text-xs">{{ $device->status_reason }}</span>@endif
                </td></tr>
                <tr><th>Uptime</th><td>{{ $device->uptime ? \LibreNMS\Util\Time::formatInterval($device->uptime) : '—' }}</td></tr>
                <tr><th>Terakhir dipoll</th><td>{{ $device->last_polled ? \Carbon\Carbon::parse($device->last_polled)->diffForHumans() : 'belum pernah' }}</td></tr>
                <tr><th>SNMP</th><td>{{ $device->snmp_disable ? 'nonaktif (ICMP saja)' : $device->snmpver }}</td></tr>
            </table>
        </div>

        @can('ant-discovery.approve')
            @include('antareja::discovery.partials.actions', ['device' => $device, 'detail' => $detail])
        @endcan

        <div class="panel panel-default">
            <div class="panel-heading"><strong>Evidence ({{ $attachments->count() }})</strong></div>
            <div class="panel-body">
                @if($attachments->isNotEmpty())
                    <div class="tw:grid tw:grid-cols-2 tw:gap-3 tw:mb-3">
                        @foreach($attachments as $file)
                            <div class="tw:border ant-card tw:rounded tw:p-2 tw:text-xs">
                                <a href="{{ route('antareja.attachments.show', $file) }}" target="_blank" rel="noopener">
                                    @if($file->isImage())
                                        <img src="{{ route('antareja.attachments.show', $file) }}" alt="{{ $file->original_name }}" class="tw:w-full tw:h-28 tw:object-cover tw:rounded tw:mb-1" loading="lazy">
                                    @else
                                        <div class="tw:h-28 tw:flex tw:items-center tw:justify-center ant-sunken tw:rounded tw:mb-1"><i class="fa fa-file-pdf fa-3x"></i></div>
                                    @endif
                                    <span class="tw:break-all">{{ $file->original_name }}</span>
                                </a>
                                <div class="ant-muted">{{ $file->description }}</div>
                                <div class="ant-muted">{{ $file->humanSize() }} · {{ $file->created_at->format('d M Y') }} · {{ $file->uploader?->username }}</div>
                                @can('ant-inventory.update')
                                    <form method="post" action="{{ route('antareja.attachments.destroy', $file) }}" onsubmit="return confirm('Hapus evidence ini?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-link btn-xs tw:p-0! ant-text-crit">Hapus</button>
                                    </form>
                                @endcan
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="ant-muted tw:text-sm">Belum ada evidence (foto instalasi, label serial, dokumen garansi).</p>
                @endif
                @can('ant-inventory.update')
                    <form method="post" action="{{ route('antareja.inventory.attachments.store', $device) }}" enctype="multipart/form-data">
                        @csrf
                        <div class="form-group">
                            <input type="file" name="file" accept=".jpg,.jpeg,.png,.webp,.pdf" required class="form-control">
                            <p class="help-block tw:text-xs">JPG, PNG, WEBP, atau PDF, maks. 10 MB.</p>
                        </div>
                        <div class="form-group"><input type="text" name="description" maxlength="255" class="form-control" placeholder="Keterangan, mis. Foto rak setelah instalasi"></div>
                        <button class="btn btn-default"><i class="fa fa-upload"></i> Unggah</button>
                    </form>
                @endcan
            </div>
        </div>

        @can('ant-audit.view')
        <div class="panel panel-default">
            <div class="panel-heading"><strong>Riwayat perubahan</strong></div>
            <ul class="list-group tw:text-xs">
                @forelse($history as $log)
                    <li class="list-group-item">
                        <strong>{{ \Antareja\Models\AuditLog::EVENTS[$log->event] ?? $log->event }}</strong> — {{ $log->description }}
                        <div class="ant-muted">{{ $log->created_at->format('d M Y H:i') }} · {{ $log->username ?? 'system' }}</div>
                    </li>
                @empty
                    <li class="list-group-item ant-muted">Belum ada riwayat.</li>
                @endforelse
            </ul>
        </div>
        @endcan
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
})();
</script>
@endsection
