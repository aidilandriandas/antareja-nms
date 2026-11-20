@extends('antareja::layouts.page')

@section('title', 'Edit ' . $device->displayName())
@section('breadcrumb')
    <li><a href="{{ route('antareja.inventory.index') }}">Device Inventory</a></li>
    <li><a href="{{ route('antareja.inventory.show', $device) }}">{{ $device->displayName() }}</a></li>
    <li class="active">Edit</li>
@endsection
@section('heading', 'Edit inventaris: ' . $device->displayName())
@section('subheading', ($device->overwrite_ip ?: $device->ip) . ' · ' . $device->os . ' · ' . ($device->hardware ?: 'model tidak diketahui'))

@section('page')
<form method="post" action="{{ route('antareja.inventory.update', $device) }}">
    @csrf @method('PUT')
    <div class="row">
        <div class="col-lg-6">
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Identitas &amp; klasifikasi</strong></div>
                <div class="panel-body">
                    <div class="form-group">
                        <label for="display_name">Nama perangkat</label>
                        <input type="text" id="display_name" name="display_name" class="form-control" maxlength="128"
                               value="{{ old('display_name', $device->display_template) }}" placeholder="mis. CCTV Gerbang Pit A">
                        <p class="help-block">Nama yang tampil di seluruh ANTAREJA (daftar, peta, notifikasi). Kosong = pakai sysName / IP.</p>
                    </div>
                    <div class="form-group">
                        <label for="category">Kategori (grup) *</label>
                        <select id="category" name="category" class="form-control">
                            <option value="">— pilih —</option>
                            @foreach($categories as $key => $label)
                                <option value="{{ $key }}" @selected(old('category', $detail->category ?? $detail->suggested_category) === $key)>{{ $label }}{{ ! $detail->category && $detail->suggested_category === $key ? ' (saran otomatis)' : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row">
                        <div class="form-group col-sm-6">
                            <label for="model">Model</label>
                            <input id="model" name="model" class="form-control" maxlength="128" value="{{ old('model', $detail->model) }}" placeholder="{{ $device->hardware }}">
                            <p class="help-block tw:text-xs">Kosongkan untuk memakai data SNMP.</p>
                        </div>
                        <div class="form-group col-sm-6">
                            <label for="serial_number">Serial number</label>
                            <input id="serial_number" name="serial_number" class="form-control" maxlength="128" value="{{ old('serial_number', $detail->serial_number) }}" placeholder="{{ $device->serial }}">
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-sm-6">
                            <label for="asset_tag">Asset tag</label>
                            <input id="asset_tag" name="asset_tag" class="form-control" maxlength="64" value="{{ old('asset_tag', $detail->asset_tag) }}">
                        </div>
                        <div class="form-group col-sm-6">
                            <label for="mac_address">MAC address</label>
                            <input id="mac_address" name="mac_address" class="form-control" maxlength="32" value="{{ old('mac_address', $detail->mac_address) }}" placeholder="00:11:22:AA:BB:CC">
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-sm-6">
                            <label for="management_protocol">Protokol manajemen</label>
                            <select id="management_protocol" name="management_protocol" class="form-control">
                                <option value="">—</option>
                                @foreach($protocols as $key => $label)
                                    <option value="{{ $key }}" @selected(old('management_protocol', $detail->management_protocol) === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-sm-6">
                            <label for="lifecycle_status">Lifecycle *</label>
                            <select id="lifecycle_status" name="lifecycle_status" class="form-control" required>
                                @foreach($lifecycle as $key => $label)
                                    <option value="{{ $key }}" @selected(old('lifecycle_status', $detail->lifecycle_status) === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel panel-default">
                <div class="panel-heading"><strong>Garansi &amp; catatan</strong></div>
                <div class="panel-body">
                    <div class="row">
                        <div class="form-group col-sm-6">
                            <label for="installed_at">Tanggal instalasi</label>
                            <input id="installed_at" name="installed_at" type="date" class="form-control" value="{{ old('installed_at', $detail->installed_at?->format('Y-m-d')) }}">
                        </div>
                        <div class="form-group col-sm-6">
                            <label for="warranty_until">Garansi sampai</label>
                            <input id="warranty_until" name="warranty_until" type="date" class="form-control" value="{{ old('warranty_until', $detail->warranty_until?->format('Y-m-d')) }}">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="warranty_info">Info garansi (vendor, no. kontrak)</label>
                        <textarea id="warranty_info" name="warranty_info" class="form-control" rows="2">{{ old('warranty_info', $detail->warranty_info) }}</textarea>
                    </div>
                    <div class="form-group">
                        <label for="notes">Catatan</label>
                        <textarea id="notes" name="notes" class="form-control" rows="4">{{ old('notes', $detail->notes) }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Lokasi</strong></div>
                <div class="panel-body">
                    <div class="row">
                        <div class="form-group col-sm-6">
                            <label for="site_id">Site *</label>
                            <select id="site_id" name="site_id" class="form-control">
                                <option value="">— pilih —</option>
                                @foreach($sites as $site)
                                    <option value="{{ $site->id }}" @selected((string) old('site_id', $detail->site_id) === (string) $site->id)>{{ $site->code }} · {{ $site->name }}</option>
                                @endforeach
                            </select>
                            @if($sites->isEmpty())<p class="help-block tw:text-xs">Belum ada site. <a href="{{ route('antareja.sites.create') }}">Buat site</a>.</p>@endif
                        </div>
                        <div class="form-group col-sm-6">
                            <label for="area_id">Area</label>
                            <select id="area_id" name="area_id" class="form-control">
                                <option value="">—</option>
                                @foreach($areas as $area)
                                    <option value="{{ $area->id }}" data-site="{{ $area->site_id }}" @selected((string) old('area_id', $detail->area_id) === (string) $area->id)>{{ $area->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="department">Departemen</label>
                        <input id="department" name="department" class="form-control" maxlength="128" list="departments" value="{{ old('department', $detail->department) }}">
                        <datalist id="departments">@foreach($departments as $dept)<option value="{{ $dept }}">@endforeach</datalist>
                    </div>
                    <div class="row">
                        <div class="form-group col-sm-8">
                            <label for="installation_location">Lokasi instalasi</label>
                            <input id="installation_location" name="installation_location" class="form-control" maxlength="255" value="{{ old('installation_location', $detail->installation_location) }}" placeholder="Panel tiang LV-03, Ruang server lt.1">
                        </div>
                        <div class="form-group col-sm-4">
                            <label for="rack">Rak / U</label>
                            <input id="rack" name="rack" class="form-control" maxlength="64" value="{{ old('rack', $detail->rack) }}" placeholder="R01 U12">
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel panel-default">
                <div class="panel-heading"><strong>PIC / pemilik *</strong></div>
                <div class="panel-body">
                    <div class="form-group">
                        <label for="pic_user_id">PIC (user ANTAREJA)</label>
                        <select id="pic_user_id" name="pic_user_id" class="form-control">
                            <option value="">—</option>
                            @foreach($users as $user)
                                <option value="{{ $user->user_id }}" @selected((string) old('pic_user_id', $detail->pic_user_id) === (string) $user->user_id)>{{ $user->realname ?: $user->username }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row">
                        <div class="form-group col-sm-7">
                            <label for="pic_name">atau nama PIC (vendor / pihak lain)</label>
                            <input id="pic_name" name="pic_name" class="form-control" maxlength="128" value="{{ old('pic_name', $detail->pic_name) }}">
                        </div>
                        <div class="form-group col-sm-5">
                            <label for="pic_phone">Kontak</label>
                            <input id="pic_phone" name="pic_phone" class="form-control" maxlength="64" value="{{ old('pic_phone', $detail->pic_phone) }}">
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel panel-default">
                <div class="panel-body tw:flex tw:flex-wrap tw:items-center tw:justify-between tw:gap-3">
                    <a href="{{ route('antareja.inventory.show', $device) }}" class="btn btn-default">Batal</a>
                    <div class="tw:flex tw:items-center tw:gap-3">
                        @if($detail->verification_status === 'pending')
                            @can('ant-discovery.approve')
                                <label class="tw:m-0 tw:font-normal"><input type="checkbox" name="verify_after_save" value="1"> Verifikasi setelah simpan</label>
                            @endcan
                        @endif
                        <button class="btn btn-primary"><i class="fa fa-save"></i> Simpan</button>
                    </div>
                </div>
            </div>
            <p class="tw:text-xs ant-muted">* Wajib sebelum perangkat dapat diverifikasi: kategori, site, PIC.</p>
        </div>
    </div>
</form>

<script>
    (function () {
        const site = document.getElementById('site_id');
        const area = document.getElementById('area_id');
        function filterAreas() {
            for (const opt of area.options) {
                if (!opt.dataset.site) continue;
                const show = opt.dataset.site === site.value;
                opt.hidden = !show;
                if (!show && opt.selected) area.value = '';
            }
        }
        site.addEventListener('change', filterAreas);
        filterAreas();
    })();
</script>
@endsection
