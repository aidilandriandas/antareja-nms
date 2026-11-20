@extends('antareja::layouts.page')

@php($C = \Antareja\Models\Change::class)

@section('title', 'Change Request')
@section('breadcrumb')<li class="active">Change Request</li>@endsection
@section('heading', 'Change Request')
@section('subheading', 'Setiap perubahan jaringan diajukan dengan rencana & rollback, disetujui orang lain, lalu otomatis dijadwalkan sebagai maintenance. ANTAREJA tidak pernah menerapkan perubahan ke perangkat.')

@section('actions')
    @can('ant-change.update')<a href="{{ route('antareja.changes.create') }}" class="btn btn-primary"><i class="fa fa-plus"></i> Ajukan perubahan</a>@endcan
@endsection

@section('page')
    <div class="tw:flex tw:flex-wrap tw:gap-2 tw:mb-3">
        @foreach(['open' => 'Berjalan', 'approval' => 'Menunggu persetujuan (' . $pending . ')', 'closed' => 'Selesai / ditutup', 'all' => 'Semua'] as $k => $l)
            <a href="{{ route('antareja.changes.index', ['view' => $k]) }}" class="btn btn-sm {{ $view === $k ? 'btn-primary' : 'btn-default' }}">{{ $l }}</a>
        @endforeach
    </div>
    <div class="panel panel-default">
        <div class="table-responsive">
            <table class="table table-condensed table-hover tw:m-0">
                <thead><tr><th>Nomor</th><th>Judul</th><th>Status</th><th>Jenis</th><th>Risiko</th><th>Jadwal</th><th class="tw:text-right">Perangkat</th><th>Pengaju</th></tr></thead>
                <tbody>
                @forelse($changes as $c)
                    <tr>
                        <td class="ant-data tw:whitespace-nowrap"><a href="{{ route('antareja.changes.show', $c) }}">{{ $c->number }}</a></td>
                        <td>{{ $c->title }}</td>
                        <td><x-antareja::status :status="$c->statusMeta()['tone']" size="sm">{{ $c->statusMeta()['label'] }}</x-antareja::status></td>
                        <td class="tw:text-xs">{{ ucfirst($c->type) }}</td>
                        <td><x-antareja::status :status="$c->riskMeta()['tone']" size="sm">{{ $c->riskMeta()['label'] }}</x-antareja::status></td>
                        <td class="tw:text-xs tw:whitespace-nowrap">{{ $c->planned_start ? $c->planned_start->format('d M H:i') . ' – ' . $c->planned_end?->format('H:i') : '—' }}</td>
                        <td class="tw:text-right ant-data">{{ $c->devices_count }}</td>
                        <td>{{ $c->requester?->realname ?: $c->requester?->username }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="tw:text-center tw:py-6 ant-muted">Tidak ada change request.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $changes->links() }}
@endsection
