@extends('antareja::layouts.page')

@section('title', $r->title())
@section('breadcrumb')
    <li><a href="{{ route('antareja.reports.index') }}">Reports</a></li>
    <li class="active">{{ $month->locale('id')->translatedFormat('F Y') }}</li>
@endsection
@section('heading', $r->title())

@section('actions')
    <a href="{{ route('antareja.reports.monthly', array_merge(request()->query(), ['print' => 1])) }}" target="_blank" rel="noopener" class="btn btn-default"><i class="fa fa-print"></i> Cetak / PDF</a>
    @can('ant-report.export')
        <a href="{{ route('antareja.reports.export', array_merge(request()->except('print'), ['format' => 'xlsx'])) }}" class="btn btn-primary"><i class="fa fa-file-excel"></i> Excel</a>
        <div class="btn-group">
            <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i class="fa fa-file-csv"></i> CSV <span class="caret"></span></button>
            <ul class="dropdown-menu dropdown-menu-right">
                @foreach(\Antareja\Http\Controllers\ReportController::SECTIONS as $k => $l)
                    <li><a href="{{ route('antareja.reports.export', array_merge(request()->except('print'), ['format' => 'csv', 'section' => $k])) }}">{{ $l }}</a></li>
                @endforeach
            </ul>
        </div>
    @endcan
@endsection

@section('page')
    <div class="tw:flex tw:flex-wrap tw:items-center tw:justify-between tw:gap-3 tw:mb-4">
        @include('antareja::partials.month-nav', ['route' => 'antareja.reports.monthly'])
        <form method="get" class="form-inline">
            <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
            <select name="site" class="form-control input-sm" aria-label="Site" onchange="this.form.submit()">
                <option value="">Semua site</option>
                @foreach($allSites as $s)<option value="{{ $s->id }}" @selected((string) request('site') === (string) $s->id)>{{ $s->code }} · {{ $s->name }}</option>@endforeach
            </select>
        </form>
    </div>
    <div class="panel panel-default">
        <div class="panel-body ant-report">
            @include('antareja::reports._body', ['deviceLimit' => 50])
        </div>
    </div>
@endsection
