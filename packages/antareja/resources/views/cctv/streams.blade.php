@extends('antareja::layouts.page')

@section('title', 'Sumber stream CCTV')
@section('breadcrumb')
    <li><a href="{{ route('antareja.cctv.index') }}">CCTV Infrastructure</a></li>
    <li class="active">Sumber stream</li>
@endsection
@section('heading', 'Sumber stream CCTV')
@section('subheading', 'Dari mana ANTAREJA menarik video tiap kamera: channel di NVR Hikvision (disarankan) atau langsung ke kamera. Pakai akun viewer/operator NVR, bukan admin.')

@section('actions')
    @can('ant-cctv.live')<a href="{{ route('antareja.cctv.live') }}" class="btn btn-primary"><i class="fa fa-video"></i> Live view</a>@endcan
@endsection

@section('page')
    @unless($gatewayUp)
        <x-antareja::alert tone="crit">Gateway video (container go2rtc) tidak bisa dihubungi. Jalankan <code>./antareja.sh restart</code> lalu cek <code>./antareja.sh logs go2rtc</code>.</x-antareja::alert>
    @endunless

    <div class="panel panel-default">
        <div class="panel-heading"><strong>Kamera dengan stream ({{ $streams->count() }})</strong></div>
        <div class="table-responsive">
            <table class="table table-condensed table-hover tw:m-0">
                <thead><tr><th>Kamera</th><th>Sumber</th><th>Path RTSP</th><th>Kredensial</th><th>Kesehatan gambar</th><th>Dicek</th></tr></thead>
                <tbody>
                @forelse($streams as $s)
                    <tr @class(['tw:opacity-60' => ! $s->enabled])>
                        <td><a href="{{ route('antareja.cctv.streams.show', $s) }}">{{ $s->device?->displayName() }}</a></td>
                        <td class="tw:text-xs">{{ $s->nvr ? 'NVR ' . $s->nvr->displayName() . ' · ch ' . $s->channel : 'langsung ke kamera' }} <span class="ant-data">{{ $s->host }}:{{ $s->rtsp_port }}</span></td>
                        <td class="ant-data tw:text-xs">{{ $s->path() }}</td>
                        <td class="tw:text-xs">{{ $s->credential?->name }}</td>
                        <td>@if(! $s->health_check)<span class="ant-muted tw:text-xs">nonaktif</span>@else<x-antareja::status :status="$s->healthMeta()['tone']" size="sm">{{ $s->healthMeta()['label'] }}</x-antareja::status>@endif</td>
                        <td class="tw:text-xs">{{ $s->last_checked_at?->diffForHumans() ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="tw:text-center tw:py-6 ant-muted">Belum ada. Simpan kredensial NVR, lalu tambahkan kamera di bawah.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-7">
            <form method="post" action="{{ route('antareja.cctv.streams.store') }}" class="panel panel-default">
                @csrf
                <div class="panel-heading"><strong>Tambah stream kamera</strong></div>
                <div class="panel-body">
                    <div class="row">
                        <div class="form-group col-sm-6">
                            <label for="device_id">Kamera *</label>
                            <select id="device_id" name="device_id" class="form-control" required>
                                <option value="">— pilih kamera (kategori CCTV di inventaris) —</option>
                                @foreach($cameras as $c)<option value="{{ $c->device_id }}" data-nvr="{{ $nvrOf[$c->device_id] ?? '' }}">{{ $c->device->displayName() }}</option>@endforeach
                            </select>
                        </div>
                        <div class="form-group col-sm-6">
                            <label for="nvr_device_id">Lewat NVR</label>
                            <select id="nvr_device_id" name="nvr_device_id" class="form-control">
                                <option value="">— langsung ke kamera —</option>
                                @foreach($nvrs as $n)<option value="{{ $n->device_id }}">{{ $n->device->displayName() }} ({{ $n->device->overwrite_ip ?: $n->device->ip }})</option>@endforeach
                            </select>
                            <p class="help-block tw:text-xs">Otomatis terisi bila relasi NVR ↔ kamera sudah ada di Device Mapping.</p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-sm-3"><label for="channel">Channel *</label><input id="channel" name="channel" type="number" min="1" max="256" value="1" class="form-control" required></div>
                        <div class="form-group col-sm-3"><label for="rtsp_port">Port RTSP</label><input id="rtsp_port" name="rtsp_port" type="number" min="1" max="65535" value="554" class="form-control" required></div>
                        <div class="form-group col-sm-6"><label for="host">Host (opsional)</label><input id="host" name="host" class="form-control" maxlength="255" placeholder="kosong = IP NVR / kamera di inventaris"></div>
                    </div>
                    <div class="row">
                        <div class="form-group col-sm-6">
                            <label for="credential_id">Kredensial *</label>
                            <select id="credential_id" name="credential_id" class="form-control" required>@foreach($credentials as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select>
                        </div>
                        <div class="form-group col-sm-6"><label for="rtsp_path">Path RTSP (opsional)</label><input id="rtsp_path" name="rtsp_path" class="form-control" maxlength="255" placeholder="kosong = Hikvision /Streaming/Channels/{ch}0{1|2}"></div>
                    </div>
                    <button class="btn btn-primary" @disabled($credentials->isEmpty())><i class="fa fa-plus"></i> Tambah</button>
                    @if($credentials->isEmpty())<span class="tw:text-xs ant-muted tw:ml-2">Buat kredensial dulu.</span>@endif
                </div>
            </form>
        </div>
        <div class="col-lg-5">
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Kredensial NVR / kamera</strong></div>
                <ul class="list-group">
                    @forelse($credentials as $c)
                        <li class="list-group-item tw:flex tw:items-center tw:justify-between tw:gap-2">
                            <span><strong>{{ $c->name }}</strong> <span class="tw:text-xs ant-muted">{{ $c->username }} · {{ $c->streams_count }} kamera</span></span>
                            @if(! $c->streams_count)<form method="post" action="{{ route('antareja.cctv.credentials.destroy', $c) }}">@csrf @method('DELETE')<button class="btn btn-link btn-xs" aria-label="Hapus {{ $c->name }}"><i class="fa fa-times"></i></button></form>@endif
                        </li>
                    @empty
                        <li class="list-group-item ant-muted">Belum ada.</li>
                    @endforelse
                </ul>
                <form method="post" action="{{ route('antareja.cctv.credentials.store') }}" class="panel-body tw:flex tw:flex-col tw:gap-2" autocomplete="off">
                    @csrf
                    <input name="name" class="form-control" required maxlength="64" placeholder="Nama, mis. NVR Pit A (viewer)" aria-label="Nama kredensial">
                    <div class="tw:flex tw:gap-2">
                        <input name="username" class="form-control" required maxlength="128" placeholder="Username" aria-label="Username" autocomplete="off">
                        <input name="secret" type="password" class="form-control" required maxlength="255" placeholder="Password" aria-label="Password" autocomplete="new-password">
                    </div>
                    <button class="btn btn-default tw:self-start">Simpan kredensial</button>
                    <p class="help-block tw:text-xs tw:m-0">Buat user khusus di NVR dengan hak <em>Live View</em> saja. Password terenkripsi dan tidak pernah dikirim ke browser.</p>
                </form>
            </div>
        </div>
    </div>
    <script>
        (function () {
            var cam = document.getElementById('device_id'), nvr = document.getElementById('nvr_device_id');
            cam.addEventListener('change', function () { var o = cam.selectedOptions[0]; if (o && o.dataset.nvr) nvr.value = o.dataset.nvr; });
        })();
    </script>
@endsection
