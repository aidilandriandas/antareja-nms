@extends('antareja::layouts.page')

@section('title', 'Device Discovery')
@section('breadcrumb')<li class="active">Device Discovery</li>@endsection
@section('heading', 'Device Discovery & Verifikasi')
@section('subheading', 'Perangkat hasil auto-discovery belum menjadi inventaris resmi sampai diverifikasi engineer.')

@section('actions')
    @can('ant-discovery.approve')
        <form method="post" action="{{ route('antareja.discovery.sync') }}">@csrf
            <button class="btn btn-default"><i class="fa fa-sync"></i> Sinkronkan sekarang</button>
        </form>
    @endcan
    @can('device.create')
        <a href="{{ url('addhost') }}" class="btn btn-default"><i class="fa fa-plus"></i> Tambah perangkat</a>
    @endcan
@endsection

@section('page')
    <div class="tw:grid tw:grid-cols-2 tw:md:grid-cols-4 tw:gap-3 tw:mb-4 tw:text-sm">
        @foreach([
            ['1', 'Device ditemukan otomatis', 'LibreNMS discovery / SNMP scan'],
            ['2', 'Masuk antrean', 'Status Discovered → Pending Verification'],
            ['3', 'Engineer melengkapi', 'Kategori, site, area, PIC, lokasi'],
            ['4', 'Approve / Reject', 'Approve → inventaris resmi'],
        ] as [$n, $t, $d])
            <div class="tw:rounded tw:border ant-card tw:p-3">
                <div class="tw:font-semibold">{{ $n }}. {{ $t }}</div><div class="ant-muted tw:text-xs">{{ $d }}</div>
            </div>
        @endforeach
    </div>

    <ul class="nav nav-tabs tw:mb-3">
        @foreach(['pending' => 'Menunggu verifikasi', 'rejected' => 'Ditolak', 'retired' => 'Retired'] as $key => $label)
            <li class="{{ $tab === $key ? 'active' : '' }}"><a href="{{ route('antareja.discovery.index', ['tab' => $key]) }}">{{ $label }} <span class="badge">{{ $counts[$key] ?? 0 }}</span></a></li>
        @endforeach
        <li><a href="{{ route('antareja.inventory.index', ['verification' => 'verified']) }}">Terverifikasi <span class="badge">{{ $counts['verified'] ?? 0 }}</span></a></li>
    </ul>

    <form method="get" class="form-inline tw:mb-3">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Cari nama, IP, serial">
        <button class="btn btn-default"><i class="fa fa-search"></i></button>
    </form>

    <form method="post" action="{{ route('antareja.discovery.bulk') }}" id="bulk-form">
        @csrf
        @if($tab === 'pending')
            @can('ant-discovery.approve')
            <div class="panel panel-default">
                <div class="panel-body form-inline tw:flex tw:flex-wrap tw:gap-2 tw:items-center">
                    <strong class="tw:mr-2">Aksi massal untuk yang dicentang:</strong>
                    <select name="site_id" class="form-control input-sm">
                        <option value="">Site…</option>
                        @foreach($sites as $site)<option value="{{ $site->id }}">{{ $site->code }} · {{ $site->name }}</option>@endforeach
                    </select>
                    <select name="category" class="form-control input-sm">
                        <option value="">Kategori…</option>
                        @foreach(\Antareja\Support\Catalog::DEVICE_CATEGORIES as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                    </select>
                    <button name="action" value="assign" class="btn btn-sm btn-default">Terapkan site / kategori</button>
                    <span class="tw:mx-2 ant-muted">|</span>
                    <label class="tw:font-normal tw:m-0"><input type="checkbox" name="accept_suggested" value="1" checked> pakai saran kategori bila kosong</label>
                    <button name="action" value="verify" class="btn btn-sm btn-success" onclick="return confirm('Verifikasi perangkat yang dicentang? Perangkat tanpa kategori/site/PIC akan dilewati.')"><i class="fa fa-check"></i> Verifikasi</button>
                </div>
            </div>
            @endcan
        @endif

        <div class="table-responsive">
            <table class="table table-hover table-condensed">
                <thead>
                <tr>
                    @if($tab === 'pending')<th><input type="checkbox" onclick="document.querySelectorAll('.bulk-device').forEach(c => c.checked = this.checked)"></th>@endif
                    <th>Perangkat</th><th>IP</th><th>Vendor / Model</th><th>Saran kategori</th><th>Site</th><th>PIC</th><th>Ditemukan</th><th>Status</th><th></th>
                </tr>
                </thead>
                <tbody>
                @forelse($details as $detail)
                    @php($device = $detail->device)
                    <tr>
                        @if($tab === 'pending')<td><input type="checkbox" class="bulk-device" name="devices[]" value="{{ $detail->device_id }}"></td>@endif
                        <td><a href="{{ route('antareja.inventory.show', $detail->device_id) }}"><strong>{{ $device?->displayName() }}</strong></a>
                            <br><span class="tw:text-xs ant-muted">{{ $device?->sysName }}</span></td>
                        <td>{{ $device?->overwrite_ip ?: $device?->ip }}</td>
                        <td>{{ $device?->os }}<br><span class="tw:text-xs ant-muted">{{ \Illuminate\Support\Str::limit($device?->hardware, 40) }}</span></td>
                        <td>{{ $detail->category ? $detail->categoryLabel() : \Antareja\Support\Catalog::label(\Antareja\Support\Catalog::DEVICE_CATEGORIES, $detail->suggested_category) }}</td>
                        <td>{{ $detail->site?->code ?? '—' }}</td>
                        <td>{{ $detail->picLabel() ?? '—' }}</td>
                        <td class="tw:text-xs tw:whitespace-nowrap">{{ $detail->discovered_at?->format('d M Y H:i') }}</td>
                        <td>@include('antareja::partials.verification-badge', ['status' => $detail->displayStatus()])
                            @if($detail->rejected_reason)<br><span class="tw:text-xs">{{ $detail->rejected_reason }}</span>@endif</td>
                        <td class="tw:whitespace-nowrap">
                            @if($tab === 'pending')
                                @can('ant-inventory.update')
                                    <a href="{{ route('antareja.inventory.edit', $detail->device_id) }}" class="btn btn-xs btn-primary">Review</a>
                                @endcan
                            @else
                                @can('ant-discovery.approve')
                                    <button form="restore-{{ $detail->device_id }}" class="btn btn-xs btn-default">Kembalikan ke antrean</button>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="tw:text-center tw:py-6 ant-muted">Tidak ada perangkat di daftar ini.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </form>

    @foreach($details as $detail)
        @if($tab !== 'pending')
            <form id="restore-{{ $detail->device_id }}" method="post" action="{{ route('antareja.discovery.restore', $detail->device_id) }}" class="tw:hidden">@csrf</form>
        @endif
    @endforeach

    {{ $details->links() }}
@endsection
