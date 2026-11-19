@extends('antareja::layouts.page')

@section('title', $problem->title)
@section('breadcrumb')
    <li><a href="{{ route('antareja.problems.index') }}">Event &amp; Alert Center</a></li>
    <li class="active">Problem #{{ $problem->id }}</li>
@endsection
@section('heading')
    {{ $problem->title }}
    <x-antareja::status :status="$problem->isOpen() ? $problem->severityMeta()['tone'] : 'ok'">{{ $problem->isOpen() ? $problem->severityMeta()['label'] : 'Selesai' }}</x-antareja::status>
@endsection
@section('subheading', (\Antareja\Models\Problem::CLASSES[$problem->class] ?? $problem->class) . ' · mulai ' . $problem->started_at->format('d M Y H:i') . ' · ' . $problem->duration())

@section('actions')
    @if($problem->device)<a href="{{ url('device/' . $problem->device_id) }}" class="btn btn-default"><i class="fa fa-chart-line"></i> Monitoring</a>@endif
    @if(! $problem->incident && $problem->isOpen())
        @can('ant-incident.create')
            <form method="post" action="{{ route('antareja.problems.incident', $problem) }}">@csrf<button class="btn btn-primary"><i class="fa fa-fire-extinguisher"></i> Buat incident</button></form>
        @endcan
    @endif
@endsection

@section('page')
    @if($problem->suppressed_reason)
        <x-antareja::alert tone="info" :title="\Antareja\Models\Problem::SUPPRESSION[$problem->suppressed_reason]">
            @if($problem->parent)
                Problem induk: <a href="{{ route('antareja.problems.show', $problem->parent) }}">{{ $problem->parent->title }}</a>. Notifikasi dan incident otomatis ditahan sampai induk pulih.
            @else
                Notifikasi dan incident otomatis ditahan selama jendela maintenance.
            @endif
        </x-antareja::alert>
    @endif

    <div class="row">
        <div class="col-lg-7">
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Detail</strong></div>
                <table class="table table-condensed tw:m-0">
                    <tr><th class="tw:w-44">Perangkat</th><td>@if($problem->device)<a href="{{ route('antareja.inventory.show', $problem->device_id) }}">{{ $problem->device->displayName() }}</a> <x-antareja::device-status :device="$problem->device" />@else — @endif</td></tr>
                    @if($problem->port)<tr><th>Port</th><td class="ant-data">{{ $problem->port->getShortLabel() }}</td></tr>@endif
                    <tr><th>Site</th><td>{{ $problem->site ? $problem->site->code . ' · ' . $problem->site->name : '—' }}</td></tr>
                    <tr><th>Keterangan</th><td>{{ $problem->detail ?? '—' }}</td></tr>
                    <tr><th>Correlation key</th><td class="ant-data tw:text-xs">{{ $problem->correlation_key }}</td></tr>
                    <tr><th>Selesai</th><td>{{ $problem->resolved_at?->format('d M Y H:i') ?? 'masih aktif' }}</td></tr>
                    <tr><th>Acknowledge</th><td>
                        @if($problem->acknowledged_at)
                            {{ $problem->acknowledged_at->format('d M Y H:i') }} oleh {{ $problem->acknowledger?->username ?? 'poller' }}@if($problem->ack_message) — "{{ $problem->ack_message }}"@endif
                        @else — @endif
                    </td></tr>
                    <tr><th>Incident</th><td>@if($problem->incident)<a href="{{ route('antareja.incidents.show', $problem->incident) }}">{{ $problem->incident->number }} — {{ $problem->incident->title }}</a>@else — @endif</td></tr>
                </table>
            </div>

            <div class="panel panel-default">
                <div class="panel-heading"><strong>Sumber event asli ({{ $problem->sources->count() }})</strong></div>
                <div class="table-responsive">
                    <table class="table table-condensed tw:m-0">
                        <thead><tr><th>Sumber</th><th>Referensi asli</th><th>Keterangan</th><th>Pertama</th><th>Terakhir</th><th>Status</th></tr></thead>
                        <tbody>
                        @foreach($problem->sources as $src)
                            <tr>
                                <td class="tw:text-xs">{{ \Antareja\Models\ProblemSource::LABELS[$src->source] ?? $src->source }}</td>
                                <td class="ant-data tw:text-xs">@if($src->url())<a href="{{ $src->url() }}">{{ $src->source }}#{{ $src->source_ref }}</a>@else{{ $src->source }}#{{ $src->source_ref }}@endif</td>
                                <td class="tw:text-xs">{{ $src->label }}</td>
                                <td class="tw:text-xs tw:whitespace-nowrap">{{ $src->first_seen_at->format('d M H:i') }}</td>
                                <td class="tw:text-xs tw:whitespace-nowrap">{{ $src->last_seen_at->format('d M H:i') }}</td>
                                <td><x-antareja::status :status="$src->active ? 'crit' : 'ok'" size="sm">{{ $src->active ? 'Aktif' : 'Pulih' }}</x-antareja::status></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @if($problem->children->isNotEmpty())
                <div class="panel panel-default">
                    <div class="panel-heading"><strong>Problem terdampak ({{ $problem->children->count() }})</strong></div>
                    <ul class="list-group">
                        @foreach($problem->children as $child)
                            <li class="list-group-item tw:flex tw:justify-between tw:gap-2">
                                <a href="{{ route('antareja.problems.show', $child) }}">{{ $child->title }}</a>
                                <x-antareja::status :status="$child->isOpen() ? 'warn' : 'ok'" size="sm">{{ $child->isOpen() ? 'Aktif' : 'Selesai' }}</x-antareja::status>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <div class="col-lg-5">
            @if($problem->isOpen() && ! $problem->acknowledged_at)
                @can('ant-problem.acknowledge')
                    <form method="post" action="{{ route('antareja.problems.acknowledge', $problem) }}" class="panel panel-default">
                        @csrf
                        <div class="panel-heading"><strong>Acknowledge</strong></div>
                        <div class="panel-body">
                            <input name="message" id="ack-message" maxlength="255" class="form-control tw:mb-2" placeholder="mis. Tim lapangan menuju lokasi">
                            <button class="btn btn-primary"><i class="fa fa-check"></i> Acknowledge</button>
                            <p class="help-block tw:text-xs tw:mb-0">Acknowledge juga diteruskan ke alert rule poller yang menjadi sumbernya.</p>
                        </div>
                    </form>
                @endcan
            @endif

            @if(! $problem->incident && $problem->isOpen() && $openIncidents->isNotEmpty())
                @can('ant-incident.create')
                    <form method="post" action="{{ route('antareja.problems.link', $problem) }}" class="panel panel-default">
                        @csrf
                        <div class="panel-heading"><strong>Tautkan ke incident yang ada</strong></div>
                        <div class="panel-body tw:flex tw:gap-2">
                            <select name="incident_id" class="form-control" aria-label="Incident">
                                @foreach($openIncidents as $i)<option value="{{ $i->id }}">{{ $i->number }} — {{ \Illuminate\Support\Str::limit($i->title, 50) }}</option>@endforeach
                            </select>
                            <button class="btn btn-default">Tautkan</button>
                        </div>
                    </form>
                @endcan
            @endif

            <div class="panel panel-default">
                <div class="panel-heading"><strong>Timeline</strong></div>
                <ul class="list-group tw:text-sm">
                    @foreach($problem->events as $e)
                        <li class="list-group-item">
                            <strong>{{ \Antareja\Models\ProblemEvent::TYPES[$e->type] ?? $e->type }}</strong> — {{ $e->message }}
                            <div class="tw:text-xs ant-muted">{{ $e->created_at->format('d M Y H:i:s') }} · {{ $e->user?->username ?? 'sistem' }}</div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
@endsection
