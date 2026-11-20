@extends('antareja::layouts.page')

@php($fmt = fn ($s) => \Antareja\Services\SlaCalculator::fmtDuration($s))

@section('title', 'Availability perangkat')
@section('breadcrumb')
    <li><a href="{{ route('antareja.sla.index', ['month' => $month->format('Y-m')]) }}">SLA Management</a></li>
    <li class="active">Availability perangkat</li>
@endsection
@section('heading', 'Availability perangkat')
@section('subheading', 'Semua perangkat aktif, diurutkan dari availability terendah.')

@section('page')
    <div class="tw:flex tw:flex-wrap tw:items-center tw:justify-between tw:gap-3 tw:mb-4">
        @include('antareja::partials.month-nav', ['route' => 'antareja.sla.devices'])
        <form method="get" class="form-inline tw:flex tw:gap-2">
            <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
            <select name="site" class="form-control input-sm" aria-label="Site" onchange="this.form.submit()">
                <option value="">Semua site</option>
                @foreach($sites as $s)<option value="{{ $s->id }}" @selected((string) request('site') === (string) $s->id)>{{ $s->code }}</option>@endforeach
            </select>
        </form>
    </div>
    <div class="panel panel-default">
        <div class="table-responsive">
            <table class="table table-condensed table-hover tw:m-0">
                <thead><tr><th>Perangkat</th><th>Site</th><th>Status</th><th class="tw:text-right">Availability</th><th class="tw:text-right">Downtime</th><th class="tw:text-right">Gangguan</th><th class="tw:text-right">MTTR</th><th class="tw:text-right">Dikecualikan</th></tr></thead>
                <tbody>
                @forelse($rows as $r)
                    <tr>
                        <td><a href="{{ route('antareja.inventory.show', $r['device']->device_id) }}">{{ $r['device']->displayName() }}</a></td>
                        <td>{{ $r['site'] ?? '—' }}</td>
                        <td><x-antareja::device-status :device="$r['device']" /></td>
                        <td class="tw:text-right">@include('antareja::sla._avail', ['s' => $r['stats'], 'target' => null])</td>
                        <td class="tw:text-right ant-data">{{ $fmt($r['stats']['downtime']) }}</td>
                        <td class="tw:text-right ant-data">{{ $r['stats']['outages'] }}</td>
                        <td class="tw:text-right ant-data">{{ $fmt($r['stats']['mttr']) }}</td>
                        <td class="tw:text-right ant-data ant-muted">{{ $r['stats']['excluded'] ? $fmt($r['stats']['excluded']) : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="tw:text-center ant-muted">Tidak ada perangkat.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
