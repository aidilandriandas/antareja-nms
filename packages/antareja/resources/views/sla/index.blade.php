@extends('antareja::layouts.page')

@php($fmt = fn ($s) => \Antareja\Services\SlaCalculator::fmtDuration($s))

@section('title', 'SLA Management')
@section('breadcrumb')<li class="active">SLA Management</li>@endsection
@section('heading', 'SLA Management')
@section('subheading', 'Service tree dan availability bulanan. Downtime diambil dari outage poller; downtime selama maintenance yang dikecualikan tidak dihitung.')

@section('actions')
    <a href="{{ route('antareja.sla.devices', ['month' => $month->format('Y-m')]) }}" class="btn btn-default"><i class="fa fa-server"></i> Availability perangkat</a>
    @can('ant-sla.update')<a href="{{ route('antareja.sla.create') }}" class="btn btn-primary"><i class="fa fa-plus"></i> Tambah layanan</a>@endcan
@endsection

@section('page')
    <div class="tw:flex tw:flex-wrap tw:items-center tw:justify-between tw:gap-3 tw:mb-4">
        @include('antareja::partials.month-nav', ['route' => 'antareja.sla.index'])
        @php($withStats = collect($stats)->filter(fn ($s) => $s['availability'] !== null))
        <div class="tw:text-sm ant-muted">{{ $withStats->where('met', true)->count() }} dari {{ $withStats->count() }} layanan memenuhi target</div>
    </div>

    <div class="panel panel-default">
        <div class="table-responsive">
            <table class="table table-condensed table-hover tw:m-0">
                <thead><tr><th>Layanan</th><th>Status kini</th><th>Aturan</th><th class="tw:text-right">Target</th><th class="tw:text-right">Availability</th><th class="tw:text-right">Downtime</th><th class="tw:text-right">Gangguan</th><th class="tw:text-right">MTTR</th><th class="tw:text-right">Dikecualikan</th></tr></thead>
                <tbody>
                @forelse($tree as $row)
                    @php($s = $row['service'])
                    @php($st = $stats[$s->id] ?? null)
                    <tr>
                        <td style="padding-left: {{ 8 + $row['depth'] * 22 }}px">
                            @if($row['depth'] > 0)<span class="ant-muted" aria-hidden="true">└</span>@endif
                            <a href="{{ route('antareja.sla.show', ['service' => $s, 'month' => $month->format('Y-m')]) }}">{{ $s->name }}</a>
                            <span class="tw:text-xs ant-muted">{{ $s->site?->code }} · {{ $s->devices_count }} perangkat</span>
                        </td>
                        <td>@include('antareja::sla._live', ['state' => $live[$s->id] ?? 'empty'])</td>
                        <td class="tw:text-xs">{{ $s->calcLabel() }}</td>
                        <td class="tw:text-right ant-data">{{ \Antareja\Services\SlaCalculator::fmtPct($s->sla_target, 2) }}</td>
                        <td class="tw:text-right">
                            @include('antareja::sla._avail', ['s' => $st, 'target' => $s->sla_target])
                            @if($st && $st['met'] === false) <x-antareja::status status="crit" size="sm">di bawah target</x-antareja::status>@endif
                        </td>
                        <td class="tw:text-right ant-data">{{ $st ? $fmt($st['downtime']) : '—' }}</td>
                        <td class="tw:text-right ant-data">{{ $st['outages'] ?? '—' }}</td>
                        <td class="tw:text-right ant-data">{{ $st ? $fmt($st['mttr']) : '—' }}</td>
                        <td class="tw:text-right ant-data ant-muted">{{ $st && $st['excluded'] ? $fmt($st['excluded']) : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="tw:text-center tw:py-6 ant-muted">
                        Belum ada layanan. Buat layanan seperti "Jaringan Pit A" lalu pilih perangkatnya; layanan bisa bertingkat (service tree).
                    </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($inactive->isNotEmpty())
        <p class="tw:text-sm ant-muted">Nonaktif: @foreach($inactive as $s)<a href="{{ route('antareja.sla.show', $s) }}">{{ $s->name }}</a>@if(! $loop->last), @endif @endforeach</p>
    @endif

    <div class="panel panel-default">
        <div class="panel-heading"><strong>Cara hitung</strong></div>
        <div class="panel-body tw:text-sm">
            <ul class="tw:mb-0 tw:pl-5">
                <li><strong>Seri</strong>: layanan down bila salah satu anggota down (mis. switch core → radio PTP → switch pit).</li>
                <li><strong>Redundan</strong>: layanan down hanya bila semua anggota down (mis. dua jalur uplink).</li>
                <li><strong>Minimal N up</strong>: down bila anggota yang up kurang dari N (mis. 3 dari 4 AP).</li>
                <li>Availability = (waktu periode − downtime) ÷ waktu periode. MTTR = downtime ÷ jumlah gangguan. MTBF = waktu up ÷ jumlah gangguan.</li>
                <li>Bulan yang sudah lewat dibekukan setelah dihitung pertama kali agar laporan tidak berubah.</li>
            </ul>
        </div>
    </div>
@endsection
