@extends('antareja::layouts.page')

@php($MW = \Antareja\Models\MaintenanceWindow::class)
@php($typeTone = ['preventive' => 'info', 'corrective' => 'warn', 'change' => 'crit'])

@section('title', 'Maintenance')
@section('breadcrumb')<li class="active">Maintenance Management</li>@endsection
@section('heading', 'Maintenance Management')
@section('subheading', 'Kalender maintenance preventive, corrective, dan change. Selama jendela berlangsung, alert perangkat terkait ditekan dan waktunya dikecualikan dari SLA bila dipilih.')

@section('actions')
    @can('ant-maintenance.update')<a href="{{ route('antareja.maintenance.create') }}" class="btn btn-primary"><i class="fa fa-plus"></i> Jadwalkan maintenance</a>@endcan
@endsection

@section('page')
    <div class="tw:flex tw:flex-wrap tw:items-center tw:justify-between tw:gap-3 tw:mb-3">
        <div class="tw:flex tw:items-center tw:gap-2">
            <a href="{{ route('antareja.maintenance.index', array_merge(request()->except('month'), ['month' => $month->copy()->subMonth()->format('Y-m')])) }}" class="btn btn-default btn-sm" aria-label="Bulan sebelumnya"><i class="fa fa-chevron-left"></i></a>
            <h2 class="tw:text-xl tw:m-0 tw:min-w-44 tw:text-center">{{ $month->copy()->locale('id')->translatedFormat('F Y') }}</h2>
            <a href="{{ route('antareja.maintenance.index', array_merge(request()->except('month'), ['month' => $month->copy()->addMonth()->format('Y-m')])) }}" class="btn btn-default btn-sm" aria-label="Bulan berikutnya"><i class="fa fa-chevron-right"></i></a>
            <a href="{{ route('antareja.maintenance.index') }}" class="btn btn-link btn-sm">Bulan ini</a>
        </div>
        <form method="get" class="form-inline tw:flex tw:gap-2">
            <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
            <select name="type" class="form-control input-sm" aria-label="Jenis">
                <option value="">Semua jenis</option>
                @foreach($MW::TYPES as $k => $l)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $l }}</option>@endforeach
            </select>
            <select name="site" class="form-control input-sm" aria-label="Site">
                <option value="">Semua site</option>
                @foreach($sites as $s)<option value="{{ $s->id }}" @selected((string) request('site') === (string) $s->id)>{{ $s->code }}</option>@endforeach
            </select>
            <button class="btn btn-default btn-sm">Filter</button>
        </form>
    </div>

    <div class="tw:overflow-x-auto tw:mb-6">
        <div class="tw:grid tw:grid-cols-7 tw:min-w-[760px] tw:border-l tw:border-t ant-card">
            @foreach(['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $dn)
                <div class="tw:px-2 tw:py-1 tw:text-xs tw:font-semibold tw:uppercase tw:tracking-wide ant-muted ant-sunken tw:border-r tw:border-b ant-card">{{ $dn }}</div>
            @endforeach
            @foreach($days as $day)
                @php($inMonth = $day['date']->month === $month->month)
                <div class="tw:min-h-28 tw:p-1.5 tw:border-r tw:border-b ant-card {{ $inMonth ? '' : 'ant-sunken tw:opacity-60' }}">
                    <div class="tw:text-xs tw:mb-1 {{ $day['date']->isToday() ? 'tw:font-bold ant-text-crit' : 'ant-muted' }}">{{ $day['date']->day }}</div>
                    <div class="tw:flex tw:flex-col tw:gap-1">
                        @foreach($day['items']->take(3) as $w)
                            <a href="{{ route('antareja.maintenance.show', $w) }}" class="ant-badge ant-badge--{{ $w->status === 'cancelled' ? 'unknown' : ($typeTone[$w->type] ?? 'info') }} ant-badge--sm tw:max-w-full tw:overflow-hidden tw:no-underline!"
                               title="{{ $w->title }} · {{ $w->starts_at->format('d M H:i') }}–{{ $w->ends_at->format('d M H:i') }} · {{ $w->statusMeta()['label'] }}">
                                <span class="tw:truncate">{{ $w->starts_at->isSameDay($day['date']) ? $w->starts_at->format('H:i') . ' ' : '' }}{{ $w->title }}</span>
                            </a>
                        @endforeach
                        @if($day['items']->count() > 3)<span class="tw:text-xs ant-muted">+{{ $day['items']->count() - 3 }} lagi</span>@endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    <div class="tw:flex tw:flex-wrap tw:gap-3 tw:text-xs tw:mb-6">
        @foreach($MW::TYPES as $k => $l)<span class="ant-badge ant-badge--{{ $typeTone[$k] }} ant-badge--sm">{{ $l }}</span>@endforeach
    </div>

    <div class="panel panel-default">
        <div class="panel-heading"><strong>Akan datang &amp; berlangsung</strong></div>
        <div class="table-responsive">
            <table class="table table-condensed table-hover tw:m-0">
                <thead><tr><th>Jadwal</th><th>Judul</th><th>Jenis</th><th>Site</th><th>Perangkat</th><th>Status</th><th>SLA</th></tr></thead>
                <tbody>
                @forelse($upcoming as $w)
                    <tr>
                        <td class="tw:text-xs tw:whitespace-nowrap">{{ $w->starts_at->format('d M H:i') }} – {{ $w->ends_at->isSameDay($w->starts_at) ? $w->ends_at->format('H:i') : $w->ends_at->format('d M H:i') }}</td>
                        <td><a href="{{ route('antareja.maintenance.show', $w) }}">{{ $w->title }}</a></td>
                        <td class="tw:text-xs">{{ $MW::TYPES[$w->type] }}</td>
                        <td>{{ $w->site?->code ?? '—' }}</td>
                        <td>{{ $w->devices_count }}</td>
                        <td><x-antareja::status :status="$w->statusMeta()['tone']" size="sm">{{ $w->statusMeta()['label'] }}</x-antareja::status></td>
                        <td class="tw:text-xs">{{ $w->exclude_from_sla ? 'dikecualikan' : 'dihitung' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="tw:text-center tw:py-4 ant-muted">Tidak ada maintenance terjadwal.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
