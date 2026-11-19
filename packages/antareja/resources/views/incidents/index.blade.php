@extends('antareja::layouts.page')

@php($fmt = fn ($s) => $s === null ? '—' : \LibreNMS\Util\Time::formatInterval($s, true))

@section('title', 'Incident Management')
@section('breadcrumb')<li class="active">Incident Management</li>@endsection
@section('heading', 'Incident Management')
@section('subheading', 'Gangguan yang ditangani tim: PIC, waktu acknowledge & penyelesaian, root cause, tindakan korektif, evidence.')

@section('actions')
    @can('ant-incident.create')<a href="{{ route('antareja.incidents.create') }}" class="btn btn-primary"><i class="fa fa-plus"></i> Buat incident</a>@endcan
@endsection

@section('page')
    <div class="tw:grid tw:grid-cols-2 tw:md:grid-cols-5 tw:gap-3 tw:mb-4">
        <x-antareja::stat label="Incident aktif" :value="$stats['open']" :tone="$stats['open'] ? 'warn' : 'neutral'" :href="route('antareja.incidents.index')" />
        <x-antareja::stat label="Critical aktif" :value="$stats['critical_open']" :tone="$stats['critical_open'] ? 'crit' : 'neutral'" :href="route('antareja.incidents.index', ['severity' => 'critical'])" />
        <x-antareja::stat label="Selesai 30 hari" :value="$stats['resolved']" />
        <x-antareja::stat label="MTTA 30 hari" :value="$fmt($stats['mtta'])" hint="rata-rata waktu sampai acknowledge" />
        <x-antareja::stat label="MTTR 30 hari" :value="$fmt($stats['mttr'])" hint="rata-rata waktu sampai resolved" />
    </div>

    <form method="get" class="form-inline tw:mb-3 tw:flex tw:flex-wrap tw:gap-2">
        <select name="status" class="form-control">
            <option value="active" @selected($status === 'active')>Aktif</option>
            @foreach(\Antareja\Models\Incident::STATUSES as $k => $m)<option value="{{ $k }}" @selected($status === $k)>{{ $m['label'] }}</option>@endforeach
            <option value="all" @selected($status === 'all')>Semua</option>
        </select>
        <select name="severity" class="form-control">
            <option value="">Semua severity</option>
            @foreach(\Antareja\Support\Severity::INCIDENT as $k => $m)<option value="{{ $k }}" @selected(request('severity') === $k)>{{ $m['label'] }}</option>@endforeach
        </select>
        <select name="site" class="form-control">
            <option value="">Semua site</option>
            @foreach($sites as $site)<option value="{{ $site->id }}" @selected((string) request('site') === (string) $site->id)>{{ $site->code }}</option>@endforeach
        </select>
        <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Nomor / judul">
        <label class="tw:font-normal tw:m-0 tw:self-center"><input type="checkbox" name="mine" value="1" @checked(request('mine'))> ditugaskan ke saya</label>
        <button class="btn btn-default"><i class="fa fa-filter"></i> Filter</button>
    </form>

    <div class="table-responsive">
        <table class="table table-hover table-condensed">
            <thead><tr><th>Nomor</th><th>Severity</th><th>Status</th><th>Judul</th><th>Perangkat</th><th>Site</th><th>PIC</th><th>Mulai</th><th>Durasi</th><th>Problem</th></tr></thead>
            <tbody>
            @forelse($incidents as $i)
                <tr>
                    <td class="tw:whitespace-nowrap"><a href="{{ route('antareja.incidents.show', $i) }}" class="ant-data"><strong>{{ $i->number }}</strong></a></td>
                    <td><x-antareja::status :status="$i->severityMeta()['tone']" size="sm">{{ $i->severityMeta()['label'] }}</x-antareja::status></td>
                    <td><x-antareja::status :status="$i->statusMeta()['tone']" size="sm">{{ $i->statusMeta()['label'] }}</x-antareja::status></td>
                    <td><a href="{{ route('antareja.incidents.show', $i) }}">{{ \Illuminate\Support\Str::limit($i->title, 70) }}</a>
                        @if($i->source === 'auto')<div class="tw:text-xs ant-muted">otomatis dari problem</div>@endif</td>
                    <td>{{ $i->device?->displayName() ?? '—' }}</td>
                    <td>{{ $i->site?->code ?? '—' }}</td>
                    <td>{{ $i->picLabel() ?? '—' }}</td>
                    <td class="tw:text-xs tw:whitespace-nowrap">{{ $i->started_at->format('d M H:i') }}</td>
                    <td class="tw:text-xs tw:whitespace-nowrap">{{ $fmt(($i->resolved_at ?? now())->getTimestamp() - $i->started_at->getTimestamp()) }}</td>
                    <td>{{ $i->problems_count }}</td>
                </tr>
            @empty
                <tr><td colspan="10" class="tw:text-center tw:py-6 ant-muted">Tidak ada incident.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $incidents->links() }}
    <p class="tw:text-xs ant-muted">MTTA dan MTTR dihitung dari incident yang dimulai dalam 30 hari terakhir dan sudah di-acknowledge / resolved. Durasi dihitung dari waktu mulai gangguan (bukan waktu incident dibuat).</p>
@endsection
