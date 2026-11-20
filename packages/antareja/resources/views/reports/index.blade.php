@extends('antareja::layouts.page')

@section('title', 'Reports')
@section('breadcrumb')<li class="active">Reports</li>@endsection
@section('heading', 'Reports')
@section('subheading', 'Laporan bulanan: SLA, availability, incident, maintenance, kualitas jaringan, dan utilisasi IP. Bisa dicetak / disimpan sebagai PDF, atau diekspor ke Excel dan CSV.')

@section('page')
    <form method="get" action="{{ route('antareja.reports.monthly') }}" class="panel panel-default tw:max-w-3xl">
        <div class="panel-heading"><strong>Laporan bulanan</strong></div>
        <div class="panel-body tw:flex tw:flex-wrap tw:items-end tw:gap-3">
            <div>
                <label for="month">Bulan</label>
                <select id="month" name="month" class="form-control">
                    @foreach($months as $m)<option value="{{ $m->format('Y-m') }}">{{ $m->locale('id')->translatedFormat('F Y') }}{{ $loop->first ? ' (berjalan)' : '' }}</option>@endforeach
                </select>
            </div>
            <div>
                <label for="site">Site</label>
                <select id="site" name="site" class="form-control">
                    <option value="">Semua site</option>
                    @foreach($sites as $s)<option value="{{ $s->id }}">{{ $s->code }} · {{ $s->name }}</option>@endforeach
                </select>
            </div>
            <button class="btn btn-primary"><i class="fa fa-file-alt"></i> Tampilkan</button>
        </div>
    </form>

    <div class="panel panel-default tw:max-w-3xl">
        <div class="panel-heading"><strong>Bulan sebelumnya</strong></div>
        <div class="list-group">
            @foreach($months->slice(1, 6) as $m)
                <div class="list-group-item tw:flex tw:items-center tw:justify-between tw:gap-2">
                    <a href="{{ route('antareja.reports.monthly', ['month' => $m->format('Y-m')]) }}">{{ $m->locale('id')->translatedFormat('F Y') }}</a>
                    <span class="tw:flex tw:gap-2">
                        <a href="{{ route('antareja.reports.monthly', ['month' => $m->format('Y-m'), 'print' => 1]) }}" target="_blank" rel="noopener" class="btn btn-default btn-xs"><i class="fa fa-print"></i> PDF</a>
                        @can('ant-report.export')<a href="{{ route('antareja.reports.export', ['month' => $m->format('Y-m'), 'format' => 'xlsx']) }}" class="btn btn-default btn-xs"><i class="fa fa-file-excel"></i> Excel</a>@endcan
                    </span>
                </div>
            @endforeach
        </div>
    </div>
@endsection
