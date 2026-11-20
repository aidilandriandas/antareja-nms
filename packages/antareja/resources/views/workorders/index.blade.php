@extends('antareja::layouts.page')

@php
    $WO = \Antareja\Models\WorkOrder::class;
    $tabs = ['active' => 'Aktif (' . $counts['active'] . ')', 'mine' => 'Tugas saya (' . $counts['mine'] . ')', 'overdue' => 'Lewat tenggat (' . $counts['overdue'] . ')', 'done' => 'Selesai (' . $counts['verify'] . ' perlu verifikasi)', 'all' => 'Semua'];
@endphp

@section('title', 'Work Order')
@section('breadcrumb')<li class="active">Work Order</li>@endsection
@section('heading', 'Work Order')
@section('subheading', 'Pekerjaan lapangan: perbaikan, instalasi, relokasi, inspeksi, kabel/fiber. Tenggat otomatis dari prioritas; selesai harus diverifikasi orang lain.')

@section('actions')
    @can('ant-workorder.update')<a href="{{ route('antareja.workorders.create') }}" class="btn btn-primary"><i class="fa fa-plus"></i> Buat work order</a>@endcan
@endsection

@section('page')
    <div class="tw:flex tw:flex-wrap tw:items-center tw:justify-between tw:gap-3 tw:mb-3">
        <div class="tw:flex tw:flex-wrap tw:gap-2">
            @foreach($tabs as $k => $l)
                <a href="{{ route('antareja.workorders.index', array_merge(request()->except(['view', 'page']), ['view' => $k])) }}" class="btn btn-sm {{ $view === $k ? 'btn-primary' : 'btn-default' }}">{{ $l }}</a>
            @endforeach
        </div>
        <form method="get" class="form-inline tw:flex tw:gap-2">
            <input type="hidden" name="view" value="{{ $view }}">
            <select name="type" class="form-control input-sm" aria-label="Jenis"><option value="">Semua jenis</option>@foreach($WO::TYPES as $k => $l)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $l }}</option>@endforeach</select>
            <select name="site" class="form-control input-sm" aria-label="Site"><option value="">Semua site</option>@foreach($sites as $s)<option value="{{ $s->id }}" @selected((string) request('site') === (string) $s->id)>{{ $s->code }}</option>@endforeach</select>
            <button class="btn btn-default btn-sm">Filter</button>
        </form>
    </div>
    <div class="panel panel-default">
        <div class="table-responsive">
            <table class="table table-condensed table-hover tw:m-0">
                <thead><tr><th>Nomor</th><th>Judul</th><th>Prioritas</th><th>Status</th><th>Jenis</th><th>Site</th><th>Pelaksana</th><th>Tenggat</th><th>Checklist</th></tr></thead>
                <tbody>
                @forelse($orders as $o)
                    @php($p = $o->checklistProgress())
                    <tr>
                        <td class="ant-data tw:whitespace-nowrap"><a href="{{ route('antareja.workorders.show', $o) }}">{{ $o->number }}</a></td>
                        <td>{{ $o->title }}@if($o->device)<div class="tw:text-xs ant-muted">{{ $o->device->displayName() }}</div>@endif</td>
                        <td><x-antareja::status :status="$o->priorityMeta()['tone']" size="sm">{{ $o->priorityMeta()['label'] }}</x-antareja::status></td>
                        <td><x-antareja::status :status="$o->statusMeta()['tone']" size="sm">{{ $o->statusMeta()['label'] }}</x-antareja::status></td>
                        <td class="tw:text-xs">{{ $WO::TYPES[$o->type] ?? $o->type }}</td>
                        <td>{{ $o->site?->code ?? '—' }}</td>
                        <td>{{ $o->assigneeLabel() ?? '—' }}</td>
                        <td class="tw:text-xs tw:whitespace-nowrap {{ $o->isOverdue() ? 'ant-text-crit tw:font-semibold' : '' }}">{{ $o->due_at?->format('d M H:i') ?? '—' }}@if($o->isOverdue()) · lewat @endif</td>
                        <td class="ant-data tw:text-xs">{{ $p['total'] ? $p['done'] . '/' . $p['total'] : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="tw:text-center tw:py-6 ant-muted">Tidak ada work order.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $orders->links() }}
@endsection
