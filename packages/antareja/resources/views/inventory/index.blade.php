@extends('antareja::layouts.page')

@section('title', 'Device Inventory')
@section('breadcrumb')<li class="active">Device Inventory</li>@endsection
@section('heading', 'Device Inventory')
@section('subheading', 'Inventaris resmi perangkat ICT. Data monitoring diambil langsung dari poller; data aset diisi engineer.')

@section('actions')
    <a href="{{ route('antareja.inventory.export', request()->query()) }}" class="btn btn-default"><i class="fa fa-file-csv"></i> Export CSV</a>
    @can('ant-discovery.view')
        <a href="{{ route('antareja.discovery.index') }}" class="btn btn-default"><i class="fa fa-search-plus"></i> Antrean verifikasi</a>
    @endcan
    @can('device.create')
        <a href="{{ url('addhost') }}" class="btn btn-primary"><i class="fa fa-plus"></i> Tambah perangkat</a>
    @endcan
@endsection

@section('page')
    @if(request('issue') && isset(\Antareja\Services\DataQuality::ISSUES[request('issue')]))
        <div class="alert alert-warning tw:flex tw:justify-between tw:items-center">
            <span><i class="fa fa-filter"></i> Filter data quality: <strong>{{ \Antareja\Services\DataQuality::ISSUES[request('issue')]['label'] }}</strong></span>
            <a href="{{ route('antareja.inventory.index', request()->except(['issue', 'page'])) }}" class="btn btn-xs btn-default">Hapus filter</a>
        </div>
    @endif

    <form method="get" class="panel panel-default">
        @if(request('issue'))<input type="hidden" name="issue" value="{{ request('issue') }}">@endif
        <div class="panel-body">
            <div class="row">
                <div class="col-sm-6 col-md-3 form-group">
                    <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Cari nama, IP, serial, MAC, lokasi, PIC">
                </div>
                <div class="col-sm-6 col-md-2 form-group">
                    <select name="site" class="form-control">
                        <option value="">Semua site</option>
                        <option value="none" @selected(request('site') === 'none')>— Tanpa site —</option>
                        @foreach($filters['sites'] as $site)
                            <option value="{{ $site->id }}" @selected((string) request('site') === (string) $site->id)>{{ $site->code }} · {{ $site->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-md-2 form-group">
                    <select name="category" class="form-control">
                        <option value="">Semua kategori</option>
                        <option value="none" @selected(request('category') === 'none')>— Tanpa kategori —</option>
                        @foreach($filters['categories'] as $key => $label)
                            <option value="{{ $key }}" @selected(request('category') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-md-2 form-group">
                    <select name="verification" class="form-control">
                        <option value="">Aktif (tanpa rejected/retired)</option>
                        @foreach(['pending' => 'Belum diverifikasi', 'verified' => 'Terverifikasi', 'rejected' => 'Ditolak', 'retired' => 'Retired', 'all' => 'Semua'] as $key => $label)
                            <option value="{{ $key }}" @selected(request('verification') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-md-1 form-group">
                    <select name="state" class="form-control">
                        <option value="">Status</option>
                        @foreach(['up' => 'Online', 'down' => 'Offline', 'disabled' => 'Disabled'] as $key => $label)
                            <option value="{{ $key }}" @selected(request('state') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-md-2 form-group tw:flex tw:gap-2">
                    <button class="btn btn-primary"><i class="fa fa-filter"></i> Filter</button>
                    <a href="{{ route('antareja.inventory.index') }}" class="btn btn-default">Reset</a>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-4 col-md-2 form-group tw:mb-0!">
                    <select name="department" class="form-control input-sm">
                        <option value="">Semua departemen</option>
                        @foreach($filters['departments'] as $dept)
                            <option value="{{ $dept }}" @selected(request('department') === $dept)>{{ $dept }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-4 col-md-2 form-group tw:mb-0!">
                    <select name="vendor" class="form-control input-sm">
                        <option value="">Semua vendor / OS</option>
                        @foreach($filters['vendors'] as $os)
                            <option value="{{ $os }}" @selected(request('vendor') === $os)>{{ $os }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-4 col-md-2 form-group tw:mb-0!">
                    <select name="sort" class="form-control input-sm">
                        @foreach(['name' => 'Urut: nama', 'site' => 'Urut: site', 'category' => 'Urut: kategori', 'status' => 'Urut: status (offline dulu)', 'discovered' => 'Urut: terbaru ditemukan'] as $key => $label)
                            <option value="{{ $key }}" @selected(request('sort', 'name') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </form>

    <div class="tw:text-sm tw:text-gray-500 tw:mb-2">{{ number_format($details->total()) }} perangkat</div>

    <div class="table-responsive">
        <table class="table table-hover table-condensed">
            <thead>
            <tr>
                <th></th><th>Perangkat</th><th>IP</th><th>Kategori</th><th>Vendor / Model</th><th>Serial</th>
                <th>Site / Area</th><th>Lokasi</th><th>PIC</th><th>Verifikasi</th><th>Kelengkapan</th>
            </tr>
            </thead>
            <tbody>
            @forelse($details as $detail)
                @php($device = $detail->device)
                @php($completeness = $detail->completeness(isset($mapped[$detail->device_id])))
                <tr>
                    <td>
                        @if($device?->disabled)<span class="label label-default" title="Disabled">&nbsp;</span>
                        @elseif($device?->status)<span class="label label-success" title="Online">&nbsp;</span>
                        @else<span class="label label-danger" title="Offline">&nbsp;</span>@endif
                    </td>
                    <td>
                        <a href="{{ route('antareja.inventory.show', $detail->device_id) }}"><strong>{{ $device?->displayName() }}</strong></a>
                        @if($device && $device->displayName() !== $device->hostname)<br><span class="tw:text-xs tw:text-gray-500">{{ $device->hostname }}</span>@endif
                    </td>
                    <td class="tw:whitespace-nowrap">{{ $device?->overwrite_ip ?: $device?->ip }}</td>
                    <td>
                        @if($detail->category){{ $detail->categoryLabel() }}
                        @elseif($detail->suggested_category)<span class="tw:text-gray-500" title="Saran otomatis, belum dikonfirmasi">{{ \Antareja\Support\Catalog::label(\Antareja\Support\Catalog::DEVICE_CATEGORIES, $detail->suggested_category) }}?</span>
                        @else — @endif
                    </td>
                    <td>{{ $device?->os }}<br><span class="tw:text-xs tw:text-gray-500">{{ \Illuminate\Support\Str::limit($detail->effectiveModel(), 40) }}</span></td>
                    <td class="tw:text-xs">{{ $detail->effectiveSerial() ?? '—' }}</td>
                    <td>{{ $detail->site?->code ?? '—' }}@if($detail->area)<br><span class="tw:text-xs tw:text-gray-500">{{ $detail->area->name }}</span>@endif</td>
                    <td class="tw:text-xs">{{ \Illuminate\Support\Str::limit(trim(($detail->installation_location ?? '') . ' ' . ($detail->rack ? "Rak {$detail->rack}" : '')), 40) ?: '—' }}</td>
                    <td>{{ $detail->picLabel() ?? '—' }}</td>
                    <td>@include('antareja::partials.verification-badge', ['status' => $detail->displayStatus($completeness)])</td>
                    <td>@include('antareja::partials.completeness', ['completeness' => $completeness])</td>
                </tr>
            @empty
                <tr><td colspan="11" class="tw:text-center tw:py-6 tw:text-gray-500">Tidak ada perangkat yang cocok dengan filter.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $details->links() }}
@endsection
