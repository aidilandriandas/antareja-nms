@extends('antareja::layouts.page')

@php
    $m = $stream->metrics ?? [];
    $r = $stream->reference_metrics ?? [];
    $num = fn ($v, $d = 1) => $v === null ? '—' : number_format((float) $v, $d, ',', '.');
@endphp

@section('title', 'Stream ' . $stream->device?->displayName())
@section('breadcrumb')
    <li><a href="{{ route('antareja.cctv.index') }}">CCTV Infrastructure</a></li>
    @can('ant-cctv.update')<li><a href="{{ route('antareja.cctv.streams') }}">Sumber stream</a></li>@endcan
    <li class="active">{{ $stream->device?->displayName() }}</li>
@endsection
@section('heading')
    {{ $stream->device?->displayName() }} <x-antareja::status :status="$stream->healthMeta()['tone']">{{ $stream->healthMeta()['label'] }}</x-antareja::status>
@endsection
@section('subheading', ($stream->nvr ? 'NVR ' . $stream->nvr->displayName() . ' · channel ' . $stream->channel : 'Langsung ke kamera') . ' · ' . $stream->host . ':' . $stream->rtsp_port . $stream->path() . ($stream->last_checked_at ? ' · dicek ' . $stream->last_checked_at->diffForHumans() : ''))

@section('actions')
    <a href="{{ route('antareja.cctv.live', ['focus' => $stream->id]) }}" class="btn btn-default"><i class="fa fa-video"></i> Live view</a>
    @can('ant-cctv.update')
        <form method="post" action="{{ route('antareja.cctv.streams.check', $stream) }}">@csrf<button class="btn btn-primary"><i class="fa fa-heartbeat"></i> Cek sekarang</button></form>
        <form method="post" action="{{ route('antareja.cctv.streams.reference', $stream) }}" onsubmit="return confirm('Jadikan gambar saat ini sebagai acuan? Pastikan kamera dalam kondisi normal (sebaiknya siang hari).')">@csrf<button class="btn btn-default"><i class="fa fa-crosshairs"></i> Jadikan acuan</button></form>
    @endcan
@endsection

@section('page')
    @if($stream->health_detail && $stream->health !== 'error')<x-antareja::alert :tone="$stream->healthMeta()['tone'] === 'crit' ? 'crit' : 'warn'">{{ $stream->health_detail }}</x-antareja::alert>@endif
    <div class="row">
        <div class="col-lg-6">
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Snapshot terakhir dicek</strong></div>
                <div class="panel-body tw:p-0">@if($hasLatest)<img src="{{ route('antareja.cctv.streams.image', [$stream, 'latest']) }}?t={{ $stream->last_checked_at?->timestamp }}" alt="Snapshot terakhir" class="tw:w-full tw:block">@else<p class="ant-muted tw:p-4 tw:m-0">Belum ada. Tekan "Cek sekarang".</p>@endif</div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Gambar acuan</strong> <span class="tw:text-xs ant-muted">{{ $stream->reference_at ? 'diambil ' . $stream->reference_at->format('d M Y H:i') : 'belum ada — buram & pergeseran belum diperiksa' }}</span></div>
                <div class="panel-body tw:p-0">@if($hasReference)<img src="{{ route('antareja.cctv.streams.image', [$stream, 'reference']) }}?t={{ $stream->reference_at?->timestamp }}" alt="Gambar acuan" class="tw:w-full tw:block">@else<p class="ant-muted tw:p-4 tw:m-0">Ambil gambar acuan saat kondisi kamera normal.</p>@endif</div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-lg-6">
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Metrik gambar</strong></div>
                <table class="table table-condensed tw:m-0">
                    <thead><tr><th></th><th class="tw:text-right">Terakhir</th><th class="tw:text-right">Acuan</th><th class="tw:text-xs ant-muted">Batas</th></tr></thead>
                    <tr><th>Kecerahan (0–255)</th><td class="tw:text-right ant-data">{{ $num($m['mean'] ?? null) }}</td><td class="tw:text-right ant-data">{{ $num($r['mean'] ?? null) }}</td><td class="tw:text-xs ant-muted">gelap bila &lt; 20 &amp; kontras &lt; 12</td></tr>
                    <tr><th>Kontras</th><td class="tw:text-right ant-data">{{ $num($m['stddev'] ?? null) }}</td><td class="tw:text-right ant-data">{{ $num($r['stddev'] ?? null) }}</td><td class="tw:text-xs ant-muted">polos bila &lt; 4</td></tr>
                    <tr><th>Ketajaman</th><td class="tw:text-right ant-data">{{ $num($m['sharpness'] ?? null, 2) }}</td><td class="tw:text-right ant-data">{{ $num($r['sharpness'] ?? null, 2) }}</td><td class="tw:text-xs ant-muted">buram bila &lt; 35% acuan</td></tr>
                    @if(isset($m['thumb'], $r['thumb']))<tr><th>Selisih dari acuan</th><td class="tw:text-right ant-data">{{ $num(\Antareja\Services\CctvHealth::diff($m['thumb'], $r['thumb'], true)) }}</td><td></td><td class="tw:text-xs ant-muted">bergeser bila &gt; 28</td></tr>@endif
                </table>
            </div>
        </div>
        <div class="col-lg-6">
            @can('ant-cctv.update')
                <form method="post" action="{{ route('antareja.cctv.streams.update', $stream) }}" class="panel panel-default">
                    @csrf @method('PUT')
                    <div class="panel-heading"><strong>Pengaturan</strong></div>
                    <div class="panel-body">
                        <div class="row">
                            <div class="form-group col-xs-6"><label for="host">Host</label><input id="host" name="host" class="form-control" maxlength="255" value="{{ $stream->host }}"></div>
                            <div class="form-group col-xs-3"><label for="rtsp_port">Port</label><input id="rtsp_port" name="rtsp_port" type="number" class="form-control" value="{{ $stream->rtsp_port }}"></div>
                            <div class="form-group col-xs-3"><label for="channel">Channel</label><input id="channel" name="channel" type="number" min="1" max="256" class="form-control" value="{{ $stream->channel }}"></div>
                        </div>
                        <input type="hidden" name="nvr_device_id" value="{{ $stream->nvr_device_id }}">
                        <div class="form-group"><label for="rtsp_path">Path RTSP</label><input id="rtsp_path" name="rtsp_path" class="form-control" maxlength="255" value="{{ $stream->rtsp_path }}" placeholder="kosong = format Hikvision"></div>
                        <div class="form-group"><label for="credential_id">Kredensial</label><select id="credential_id" name="credential_id" class="form-control">@foreach($credentials as $c)<option value="{{ $c->id }}" @selected($stream->credential_id === $c->id)>{{ $c->name }}</option>@endforeach</select></div>
                        <div class="checkbox"><label><input type="hidden" name="enabled" value="0"><input type="checkbox" name="enabled" value="1" @checked($stream->enabled)> Tampil di live view</label></div>
                        <div class="checkbox"><label><input type="hidden" name="health_check" value="0"><input type="checkbox" name="health_check" value="1" @checked($stream->health_check)> Cek kesehatan gambar tiap 15 menit</label></div>
                        <button class="btn btn-default">Simpan</button>
                    </div>
                </form>
                <form method="post" action="{{ route('antareja.cctv.streams.destroy', $stream) }}" onsubmit="return confirm('Hapus sumber stream kamera ini?')">@csrf @method('DELETE')<button class="btn btn-link ant-text-crit">Hapus stream</button></form>
            @endcan
        </div>
    </div>
@endsection
