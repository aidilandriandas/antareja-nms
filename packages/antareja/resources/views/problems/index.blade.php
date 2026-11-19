@extends('antareja::layouts.page')

@php($S = \Antareja\Support\Severity::class)

@section('title', 'Event & Alert Center')
@section('breadcrumb')<li class="active">Event &amp; Alert Center</li>@endsection
@section('heading', 'Event & Alert Center')
@section('subheading', 'Problem aktif dari status polling, port uplink, Quality Probe, IPAM, dan alert rule — sudah digabung bila menunjuk masalah yang sama.')

@section('actions')
    @can('ant-problem.acknowledge')
        <form method="post" action="{{ route('antareja.problems.sync') }}">@csrf<button class="btn btn-default"><i class="fa fa-sync"></i> Sinkronkan sekarang</button></form>
    @endcan
    @can('ant-incident.view')<a href="{{ route('antareja.incidents.index') }}" class="btn btn-default"><i class="fa fa-fire-extinguisher"></i> Incident</a>@endcan
@endsection

@section('page')
    <div class="tw:grid tw:grid-cols-2 tw:md:grid-cols-4 tw:xl:grid-cols-7 tw:gap-3 tw:mb-4">
        @foreach(array_reverse($S::PROBLEM, true) as $key => $meta)
            <x-antareja::stat :label="$meta['label']" :value="$counts[$key] ?? 0" :tone="($counts[$key] ?? 0) > 0 ? ($meta['rank'] >= 4 ? 'crit' : ($meta['rank'] >= 2 ? 'warn' : 'neutral')) : 'neutral'"
                :href="route('antareja.problems.index', ['severity' => $key])" />
        @endforeach
        <x-antareja::stat label="Belum di-ack" :value="$unacked" :tone="$unacked ? 'warn' : 'neutral'" :href="route('antareja.problems.index', ['ack' => 'no'])" />
        <x-antareja::stat label="Ditekan" :value="$suppressedCount" hint="induk down / maintenance" :href="route('antareja.problems.index', ['suppressed' => 1])" />
    </div>

    <form method="get" class="form-inline tw:mb-3 tw:flex tw:flex-wrap tw:gap-2">
        <select name="status" class="form-control">
            @foreach(['open' => 'Aktif', 'resolved' => 'Selesai', 'all' => 'Semua'] as $k => $l)<option value="{{ $k }}" @selected($status === $k)>{{ $l }}</option>@endforeach
        </select>
        <select name="severity" class="form-control">
            <option value="">Semua severity</option>
            @foreach($S::PROBLEM as $k => $m)<option value="{{ $k }}" @selected(request('severity') === $k)>≥ {{ $m['label'] }}</option>@endforeach
        </select>
        <select name="class" class="form-control">
            <option value="">Semua jenis</option>
            @foreach(\Antareja\Models\Problem::CLASSES as $k => $l)<option value="{{ $k }}" @selected(request('class') === $k)>{{ $l }}</option>@endforeach
        </select>
        <select name="site" class="form-control">
            <option value="">Semua site</option>
            @foreach($sites as $site)<option value="{{ $site->id }}" @selected((string) request('site') === (string) $site->id)>{{ $site->code }}</option>@endforeach
        </select>
        <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Cari">
        <label class="tw:font-normal tw:m-0 tw:self-center"><input type="checkbox" name="suppressed" value="1" @checked($showSuppressed)> tampilkan yang ditekan</label>
        <button class="btn btn-default"><i class="fa fa-filter"></i> Filter</button>
    </form>

    <form method="post" action="{{ route('antareja.problems.bulk-acknowledge') }}">
        @csrf
        @can('ant-problem.acknowledge')
            <div class="tw:flex tw:flex-wrap tw:gap-2 tw:items-center tw:mb-2">
                <input type="text" name="message" maxlength="255" class="form-control input-sm tw:max-w-sm" placeholder="Pesan acknowledge (opsional), mis. sedang dicek tim lapangan">
                <button class="btn btn-sm btn-default"><i class="fa fa-check"></i> Acknowledge yang dicentang</button>
            </div>
        @endcan
        <div class="table-responsive">
            <table class="table table-hover table-condensed">
                <thead><tr>
                    <th><input type="checkbox" aria-label="Pilih semua" onclick="document.querySelectorAll('.pb-check').forEach(c => c.checked = this.checked)"></th>
                    <th>Severity</th><th>Problem</th><th>Perangkat</th><th>Site</th><th>Mulai</th><th>Durasi</th><th>Sumber</th><th>Ack</th><th>Incident</th>
                </tr></thead>
                <tbody>
                @forelse($problems as $p)
                    <tr class="{{ $p->suppressed_reason ? 'tw:opacity-60' : '' }}">
                        <td>@if($p->isOpen() && ! $p->acknowledged_at)<input type="checkbox" class="pb-check" name="problems[]" value="{{ $p->id }}">@endif</td>
                        <td><x-antareja::status :status="$p->isOpen() ? $p->severityMeta()['tone'] : 'ok'" size="sm">{{ $p->isOpen() ? $p->severityMeta()['label'] : 'Selesai' }}</x-antareja::status></td>
                        <td>
                            <a href="{{ route('antareja.problems.show', $p) }}"><strong>{{ $p->title }}</strong></a>
                            <div class="tw:text-xs ant-muted">{{ \Antareja\Models\Problem::CLASSES[$p->class] ?? $p->class }}
                                @if($p->suppressed_reason) · {{ \Antareja\Models\Problem::SUPPRESSION[$p->suppressed_reason] }}@endif
                                @if($p->children_count) · <span class="ant-text-warn">{{ $p->children_count }} problem terdampak</span>@endif
                            </div>
                        </td>
                        <td>@if($p->device)<a href="{{ route('antareja.inventory.show', $p->device_id) }}">{{ $p->device->displayName() }}</a>@else — @endif</td>
                        <td>{{ $p->site?->code ?? '—' }}</td>
                        <td class="tw:text-xs tw:whitespace-nowrap">{{ $p->started_at->format('d M H:i') }}</td>
                        <td class="tw:text-xs tw:whitespace-nowrap">{{ $p->duration() }}</td>
                        <td class="tw:text-xs">{{ $p->active_sources_count }}</td>
                        <td class="tw:text-xs">@if($p->acknowledged_at)<span title="{{ $p->ack_message }}">✓ {{ $p->acknowledger?->username ?? 'poller' }}</span>@else — @endif</td>
                        <td class="tw:text-xs">@if($p->incident)<a href="{{ route('antareja.incidents.show', $p->incident) }}">{{ $p->incident->number }}</a>@else — @endif</td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="tw:text-center tw:py-6 ant-muted">Tidak ada problem{{ $status === 'open' ? ' aktif' : '' }}. Semua sumber normal.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </form>
    {{ $problems->links() }}
    <p class="tw:text-xs ant-muted">Problem yang ditekan (induk down atau maintenance) tidak membuat incident otomatis dan tidak mengirim notifikasi. Sinkronisasi berjalan setiap menit.</p>
@endsection
