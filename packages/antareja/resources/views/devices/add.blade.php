@extends('antareja::layouts.page')

@section('title', 'Tambah Perangkat')
@section('breadcrumb')
    <li><a href="{{ route('devices') }}">Perangkat</a></li>
    <li class="active">Tambah</li>
@endsection
@section('heading', 'Tambah Perangkat')
@section('subheading', 'Beri nama dan grup sejak awal supaya perangkat mudah dicari — tidak hanya muncul sebagai alamat IP.')
@section('actions')
    <a href="{{ url('addhost') }}" class="btn btn-default btn-sm" title="Form LibreNMS dengan semua opsi lanjutan (port SNMP, transport, poller group, dll.)">
        <i class="fa-solid fa-sliders"></i> Opsi lanjutan
    </a>
@endsection

@php($tab = old('tab', $mode))
@section('page')
<form method="post" action="{{ route('antareja.devices.store') }}" class="ant-addform" id="ant-addform">
    @csrf
    <input type="hidden" name="tab" id="ant-tab" value="{{ $tab }}">

    <div class="ant-seg" role="tablist" aria-label="Cara menambah">
        <button type="button" role="tab" data-tab="single" aria-selected="{{ $tab === 'single' ? 'true' : 'false' }}">
            <i class="fa-solid fa-plus"></i> Satu perangkat
        </button>
        <button type="button" role="tab" data-tab="bulk" aria-selected="{{ $tab === 'bulk' ? 'true' : 'false' }}">
            <i class="fa-solid fa-list"></i> Banyak sekaligus
        </button>
    </div>

    <div class="row">
        <div class="col-lg-7">
            {{-- 1. Identitas --}}
            <section class="panel panel-default">
                <div class="panel-heading"><strong><span class="ant-step">1</span> Perangkat</strong></div>
                <div class="panel-body">
                    <div data-pane="single" @if($tab !== 'single') hidden @endif>
                        <div class="row">
                            <div class="form-group col-sm-6 @error('name') has-error @enderror">
                                <label for="name">Nama perangkat</label>
                                <input id="name" name="name" class="form-control" maxlength="128" value="{{ old('name') }}" placeholder="mis. CCTV Gerbang Pit A" autofocus>
                                <p class="help-block">Nama yang tampil di daftar, peta, dan notifikasi.</p>
                            </div>
                            <div class="form-group col-sm-6 @error('hostname') has-error @enderror">
                                <label for="hostname">Alamat IP / hostname *</label>
                                <input id="hostname" name="hostname" class="form-control ant-data" maxlength="128" value="{{ old('hostname') }}" placeholder="10.20.30.40" spellcheck="false" autocomplete="off">
                                @error('hostname')<p class="help-block">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </div>
                    <div data-pane="bulk" @if($tab !== 'bulk') hidden @endif>
                        <div class="form-group @error('lines') has-error @enderror">
                            <label for="lines">Daftar perangkat — satu per baris</label>
                            <textarea id="lines" name="lines" class="form-control ant-data" rows="10" spellcheck="false"
                                      placeholder="10.20.30.41, CCTV Gerbang Pit A&#10;10.20.30.42, CCTV Timbangan, CCTV&#10;10.20.40.10, AP Mess Blok B, Access Point">{{ old('lines') }}</textarea>
                            @error('lines')
                                @foreach($errors->get('lines') as $msg)<p class="help-block">{{ $msg }}</p>@endforeach
                            @else
                                <p class="help-block">Format: <code>IP, Nama</code> atau <code>IP, Nama, Grup</code> (pemisah koma, titik koma, atau tab — bisa tempel langsung dari Excel). Grup per baris menimpa grup di bawah. Maksimal {{ \Antareja\Http\Controllers\DeviceAddController::BULK_MAX }} baris.</p>
                            @enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="form-group col-sm-4">
                            <label for="category">Grup</label>
                            <select id="category" name="category" class="form-control">
                                <option value="">— pilih —</option>
                                @foreach($categories as $key => $label)
                                    <option value="{{ $key }}" @selected(old('category') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-sm-4">
                            <label for="site_id">Site</label>
                            <select id="site_id" name="site_id" class="form-control">
                                <option value="">— pilih —</option>
                                @foreach($sites as $site)
                                    <option value="{{ $site->id }}" @selected((string) old('site_id') === (string) $site->id)>{{ $site->code }} — {{ $site->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-sm-4">
                            <label for="area_id">Area</label>
                            <select id="area_id" name="area_id" class="form-control">
                                <option value="">— pilih —</option>
                                @foreach($sites as $site)
                                    @foreach($site->areas as $area)
                                        <option value="{{ $area->id }}" data-site="{{ $site->id }}" @selected((string) old('area_id') === (string) $area->id)>{{ $area->name }}</option>
                                    @endforeach
                                @endforeach
                            </select>
                        </div>
                    </div>
                    @if($sites->isEmpty())
                        <p class="help-block tw:m-0">Belum ada site. <a href="{{ route('antareja.sites.index') }}">Buat site</a> dulu supaya perangkat tampil di status per site.</p>
                    @endif
                </div>
            </section>

            {{-- 2. Pemantauan --}}
            <section class="panel panel-default">
                <div class="panel-heading"><strong><span class="ant-step">2</span> Cara pemantauan</strong></div>
                <div class="panel-body">
                    @php($m = old('mode', 'v2c'))
                    <div class="ant-choice" role="radiogroup">
                        <label class="ant-choice__opt"><input type="radio" name="mode" value="v2c" @checked($m === 'v2c')>
                            <span><b>SNMP v2c</b><small>Switch, router, AP, NVR — data lengkap: port, trafik, sensor.</small></span></label>
                        <label class="ant-choice__opt"><input type="radio" name="mode" value="v3" @checked($m === 'v3')>
                            <span><b>SNMP v3</b><small>Lebih aman (user + password terenkripsi).</small></span></label>
                        <label class="ant-choice__opt"><input type="radio" name="mode" value="ping" @checked($m === 'ping')>
                            <span><b>Ping saja</b><small>Kamera / perangkat tanpa SNMP — hanya status up/down & latensi.</small></span></label>
                    </div>

                    <div data-mode="v2c" class="tw:mt-4" @if($m !== 'v2c') hidden @endif>
                        <div class="form-group tw:mb-0">
                            <label for="community">Community</label>
                            <input id="community" name="community" type="password" class="form-control" autocomplete="off" maxlength="128">
                            <p class="help-block">Kosongkan untuk memakai daftar community di Pengaturan Sistem → SNMP.</p>
                        </div>
                    </div>
                    <div data-mode="v3" class="tw:mt-4" @if($m !== 'v3') hidden @endif>
                        <div class="row">
                            <div class="form-group col-sm-12 @error('v3_user') has-error @enderror">
                                <label for="v3_user">Username *</label>
                                <input id="v3_user" name="v3_user" class="form-control" maxlength="64" value="{{ old('v3_user') }}" autocomplete="off">
                                @error('v3_user')<p class="help-block">{{ $message }}</p>@enderror
                            </div>
                            <div class="form-group col-sm-8"><label for="v3_auth_pass">Password autentikasi</label>
                                <input id="v3_auth_pass" name="v3_auth_pass" type="password" class="form-control" autocomplete="new-password"></div>
                            <div class="form-group col-sm-4"><label for="v3_auth_algo">Algoritma</label>
                                <select id="v3_auth_algo" name="v3_auth_algo" class="form-control">
                                    @foreach(['SHA', 'SHA-256', 'SHA-512', 'MD5'] as $a)<option @selected(old('v3_auth_algo', 'SHA') === $a)>{{ $a }}</option>@endforeach
                                </select></div>
                            <div class="form-group col-sm-8"><label for="v3_priv_pass">Password enkripsi</label>
                                <input id="v3_priv_pass" name="v3_priv_pass" type="password" class="form-control" autocomplete="new-password"></div>
                            <div class="form-group col-sm-4"><label for="v3_priv_algo">Algoritma</label>
                                <select id="v3_priv_algo" name="v3_priv_algo" class="form-control">
                                    @foreach(['AES', 'AES-256', 'DES'] as $a)<option @selected(old('v3_priv_algo', 'AES') === $a)>{{ $a }}</option>@endforeach
                                </select></div>
                        </div>
                    </div>

                    <label class="ant-check tw:mt-3">
                        <input type="checkbox" name="force" value="1" @checked(old('force'))>
                        Tetap tambahkan walau perangkat belum bisa dihubungi <span class="ant-muted">(mis. dipasang besok)</span>
                    </label>
                </div>
                <div class="panel-footer tw:flex tw:items-center tw:justify-between tw:gap-3">
                    <span class="ant-muted tw:text-sm" data-pane="single" @if($tab !== 'single') hidden @endif>Perangkat diuji (ping & SNMP) sebelum disimpan.</span>
                    <span class="ant-muted tw:text-sm" data-pane="bulk" @if($tab !== 'bulk') hidden @endif>Tiap perangkat diuji satu per satu; bisa memakan waktu ± 5 detik per perangkat.</span>
                    <span class="tw:flex tw:gap-2">
                    <button type="button" class="btn btn-default" id="ant-test-btn" data-pane="single" @if($tab !== 'single') hidden @endif><i class="fa-solid fa-stethoscope"></i> Uji koneksi</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i> <span data-pane="single" @if($tab !== 'single') hidden @endif>Tambah perangkat</span><span data-pane="bulk" @if($tab !== 'bulk') hidden @endif>Tambah semua</span></button>
                    </span>
                </div>
            </section>
        </div>

        <div class="col-lg-5">
            <section class="panel panel-default" id="ant-test-wrap" hidden>
                <div class="panel-heading"><strong><i class="fa-solid fa-stethoscope"></i> Hasil uji koneksi</strong></div>
                <div id="ant-test-result"></div>
            </section>
            @if($results)
                <section class="panel panel-default">
                    <div class="panel-heading"><strong>Hasil penambahan</strong></div>
                    <ul class="ant-results">
                        @foreach($results as $r)
                            <li>
                                <x-antareja::status :status="$r['ok'] ? 'ok' : 'crit'" size="sm">{{ $r['ok'] ? 'Berhasil' : 'Gagal' }}</x-antareja::status>
                                <span class="ant-results__main">
                                    @if($r['url'])<a href="{{ $r['url'] }}">{{ $r['name'] ?: $r['hostname'] }}</a>@else<b>{{ $r['name'] ?: $r['hostname'] }}</b>@endif
                                    <small class="ant-data">{{ $r['hostname'] }}</small>
                                    @if(! $r['ok'] || $r['message'] !== 'Ditambahkan')<small>{{ $r['message'] }}</small>@endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
            <section class="panel panel-default">
                <div class="panel-heading"><strong>Tips</strong></div>
                <div class="panel-body tw:text-sm">
                    <ul class="tw:pl-5 tw:m-0 tw:space-y-2">
                        <li><b>Grup</b> menentukan perangkat masuk grup mana (CCTV, Access Point, Switch, …) — lihat menu <a href="{{ route('antareja.groups.index') }}">Grup Perangkat</a>.</li>
                        <li>Kamera Hikvision biasanya tanpa SNMP: pilih <b>Ping saja</b>, lalu atur live view di menu CCTV.</li>
                        <li>Bila SNMP tidak menjawab, perangkat tetap ditambahkan dengan pemantauan ping.</li>
                        <li>Nama bisa diubah kapan saja di Inventaris Perangkat → Edit.</li>
                    </ul>
                </div>
            </section>
        </div>
    </div>
</form>

@include('antareja::devices.test-script')
<script>
(function () {
    var form = document.getElementById('ant-addform');
    document.getElementById('ant-test-btn').addEventListener('click', function () {
        var f = new FormData(form), payload = {};
        ['hostname', 'mode', 'community', 'v3_user', 'v3_auth_pass', 'v3_auth_algo', 'v3_priv_pass', 'v3_priv_algo'].forEach(function (k) { payload[k] = f.get(k) || null; });
        var wrap = document.getElementById('ant-test-wrap'); wrap.hidden = false;
        AntConnTest.run('{{ route('antareja.devices.test') }}', payload, document.getElementById('ant-test-result'));
        wrap.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    });
    form.querySelectorAll('.ant-seg [data-tab]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var tab = btn.dataset.tab;
            document.getElementById('ant-tab').value = tab;
            form.querySelectorAll('.ant-seg [data-tab]').forEach(function (b) { b.setAttribute('aria-selected', b === btn ? 'true' : 'false'); });
            form.querySelectorAll('[data-pane]').forEach(function (p) { p.hidden = p.dataset.pane !== tab; });
            (tab === 'bulk' ? document.getElementById('lines') : document.getElementById('name')).focus();
        });
    });
    form.querySelectorAll('input[name=mode]').forEach(function (r) {
        r.addEventListener('change', function () {
            form.querySelectorAll('[data-mode]').forEach(function (p) { p.hidden = p.dataset.mode !== r.value; });
        });
    });
    // Area hanya yang sesuai site terpilih
    var site = document.getElementById('site_id'), area = document.getElementById('area_id');
    function filterAreas() {
        area.querySelectorAll('option[data-site]').forEach(function (o) { o.hidden = o.dataset.site !== site.value; if (o.hidden && o.selected) area.value = ''; });
    }
    site.addEventListener('change', filterAreas); filterAreas();
})();
</script>
@endsection
