@extends('antareja::layouts.page')

@php($CI = \Antareja\Models\CapacityItem::class)

@section('title', 'Capacity Planning')
@section('breadcrumb')<li class="active">Capacity Planning</li>@endsection
@section('heading', 'Capacity Planning')
@section('subheading', 'Utilisasi link uplink, storage perangkat (NVR, server), dan pool IP dari histori poller, dengan prakiraan kapan menyentuh ambang. Puncak harian = persentil-95 data 5 menit (7 hari terakhir) atau maksimum harian (histori lebih lama).')

@section('actions')
    @can('ant-capacity.update')
        <form method="post" action="{{ route('antareja.capacity.refresh') }}">@csrf<button class="btn btn-default"><i class="fa fa-sync"></i> Hitung ulang</button></form>
    @endcan
@endsection

@section('page')
    <div class="tw:grid tw:grid-cols-2 tw:md:grid-cols-4 tw:gap-3 tw:mb-5">
        <x-antareja::stat label="Melewati ambang kritis" :value="$counts['over_crit']" :tone="$counts['over_crit'] ? 'crit' : 'ok'" />
        <x-antareja::stat label="Melewati ambang peringatan" :value="$counts['over_warn']" :tone="$counts['over_warn'] ? 'warn' : 'ok'" />
        <x-antareja::stat label="Kritis dalam 90 hari" :value="$counts['soon']" :tone="$counts['soon'] ? 'warn' : 'ok'" hint="berdasarkan tren" />
        <x-antareja::stat label="Item dipantau" :value="$counts['total']" />
    </div>

    <div class="tw:flex tw:flex-wrap tw:gap-2 tw:mb-3">
        <a href="{{ route('antareja.capacity.index') }}" class="btn btn-sm {{ $type ? 'btn-default' : 'btn-primary' }}">Semua</a>
        @foreach($CI::TYPES as $k => $l)<a href="{{ route('antareja.capacity.index', ['type' => $k]) }}" class="btn btn-sm {{ $type === $k ? 'btn-primary' : 'btn-default' }}">{{ $l }}</a>@endforeach
    </div>

    <div class="panel panel-default">
        <div class="table-responsive">
            <table class="table table-condensed table-hover tw:m-0">
                <thead><tr><th>Item</th><th>Jenis</th><th class="tw:text-right">Kapasitas</th><th class="tw:w-56">Puncak harian terakhir</th><th class="tw:text-right">Tren / hari</th><th>Peringatan pada</th><th>Kritis pada</th><th class="tw:text-right">Data</th></tr></thead>
                <tbody>
                @forelse($items as $i)
                    <tr>
                        <td><a href="{{ route('antareja.capacity.show', $i) }}">{{ $i->label }}</a></td>
                        <td class="tw:text-xs">{{ $CI::TYPES[$i->type] }}</td>
                        <td class="tw:text-right ant-data tw:text-xs">{{ $i->capacityLabel() }}</td>
                        <td>@if($i->current_pct !== null)<x-antareja::progress :value="$i->current_pct" :warn-at="$i->warn_pct" :crit-at="$i->crit_pct" :label="'Utilisasi ' . $i->label" />@else<span class="ant-muted tw:text-xs">belum ada data</span>@endif</td>
                        <td class="tw:text-right ant-data tw:text-xs">{{ $i->trend_pct_per_day === null ? '—' : ($i->trend_pct_per_day >= 0 ? '+' : '') . number_format($i->trend_pct_per_day, 2, ',', '.') . ' pt' }}</td>
                        <td class="tw:text-xs">@include('antareja::capacity._date', ['d' => $i->forecast_warn_on])</td>
                        <td class="tw:text-xs">@include('antareja::capacity._date', ['d' => $i->forecast_crit_on, 'crit' => true])</td>
                        <td class="tw:text-right tw:text-xs ant-muted">{{ $i->days_of_data }} hr</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="tw:text-center tw:py-6 ant-muted">Belum ada item. Verifikasi mapping uplink di Device Mapping, atau tambahkan port di bawah, lalu tekan "Hitung ulang".</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <p class="tw:text-xs ant-muted">Prakiraan memakai regresi linear puncak harian 60 hari terakhir (minimal 7 hari data) dan hanya ditampilkan bila tercapai dalam 365 hari. Tren musiman (mis. shift, musim hujan) tidak dimodelkan.</p>

    @can('ant-capacity.update')
        <form method="post" action="{{ route('antareja.capacity.ports') }}" class="panel panel-default tw:max-w-3xl">
            @csrf
            <div class="panel-heading"><strong>Pantau port lain</strong> <span class="tw:text-xs ant-muted">uplink dari mapping terverifikasi sudah otomatis</span></div>
            <div class="panel-body tw:flex tw:gap-2">
                <select name="port_id" id="cap-port" class="form-control" required aria-label="Port">
                    <option value="">Pilih port</option>
                    @foreach($ports as $p)<option value="{{ $p->port_id }}">{{ $p->device?->displayName() }} · {{ $p->getShortLabel() }}{{ $p->ifAlias && $p->ifAlias !== $p->ifDescr ? ' — ' . $p->ifAlias : '' }}</option>@endforeach
                </select>
                <button class="btn btn-default tw:whitespace-nowrap">Tambah</button>
            </div>
        </form>
        <script>if (window.jQuery && jQuery.fn.select2) { jQuery('#cap-port').select2({ width: '100%', theme: 'bootstrap' }); }</script>
    @endcan
@endsection
