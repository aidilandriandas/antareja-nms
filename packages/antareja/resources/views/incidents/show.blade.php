@extends('antareja::layouts.page')

@php
    $fmt = fn ($s) => $s === null ? '—' : \LibreNMS\Util\Time::formatInterval($s, true);
    $next = \Antareja\Models\Incident::TRANSITIONS[$incident->status] ?? [];
    $actionLabel = ['acknowledged' => 'Acknowledge', 'in_progress' => 'Mulai kerjakan', 'pending' => 'Tunda (pending)', 'resolved' => 'Tandai resolved', 'closed' => 'Tutup incident'];
@endphp

@section('title', $incident->number)
@section('breadcrumb')
    <li><a href="{{ route('antareja.incidents.index') }}">Incident Management</a></li>
    <li class="active">{{ $incident->number }}</li>
@endsection
@section('heading')
    <span class="ant-data tw:text-lg!">{{ $incident->number }}</span> {{ $incident->title }}
@endsection
@section('subheading')
    <span class="tw:inline-flex tw:gap-2 tw:items-center">
        <x-antareja::status :status="$incident->severityMeta()['tone']">{{ $incident->severityMeta()['label'] }}</x-antareja::status>
        <x-antareja::status :status="$incident->statusMeta()['tone']">{{ $incident->statusMeta()['label'] }}</x-antareja::status>
        <span>mulai {{ $incident->started_at->format('d M Y H:i') }}</span>
    </span>
@endsection

@section('actions')
    @can('ant-workorder.update')
        @if($incident->isActive())<a href="{{ route('antareja.workorders.create', ['incident' => $incident->id]) }}" class="btn btn-default"><i class="fa fa-clipboard-list"></i> Buat work order</a>@endif
    @endcan
@endsection

@section('page')
<div class="row">
    <div class="col-lg-7">
        @can('ant-incident.update')
            @if($next)
                <form method="post" action="{{ route('antareja.incidents.transition', $incident) }}" class="panel panel-default">
                    @csrf
                    <div class="panel-heading"><strong>Ubah status</strong></div>
                    <div class="panel-body">
                        <div class="form-group">
                            <label for="comment">Komentar (opsional)</label>
                            <input id="comment" name="comment" class="form-control" maxlength="2000" placeholder="mis. Tim tiba di lokasi, kabel FO putus di tiang 12">
                        </div>
                        @if(in_array('resolved', $next) || in_array('closed', $next))
                            <div class="row">
                                <div class="form-group col-sm-6">
                                    <label for="root_cause">Root cause</label>
                                    <textarea id="root_cause" name="root_cause" class="form-control" rows="2">{{ old('root_cause', $incident->root_cause) }}</textarea>
                                </div>
                                <div class="form-group col-sm-6">
                                    <label for="corrective_action">Corrective action</label>
                                    <textarea id="corrective_action" name="corrective_action" class="form-control" rows="2">{{ old('corrective_action', $incident->corrective_action) }}</textarea>
                                </div>
                            </div>
                            <p class="help-block tw:text-xs">Root cause dan corrective action wajib sebelum incident ditutup.</p>
                        @endif
                        <div class="tw:flex tw:flex-wrap tw:gap-2">
                            @foreach($next as $to)
                                @if($to !== 'closed' || auth()->user()->can('ant-incident.close'))
                                    <button name="status" value="{{ $to }}" class="btn {{ $loop->first ? 'btn-primary' : 'btn-default' }}">{{ $to === 'in_progress' && in_array($incident->status, ['resolved', 'closed']) ? 'Buka kembali' : ($actionLabel[$to] ?? $to) }}</button>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </form>
            @endif
        @endcan

        <div class="panel panel-default">
            <div class="panel-heading"><strong>Detail</strong></div>
            <table class="table table-condensed tw:m-0">
                <tr><th class="tw:w-44">Perangkat</th><td>@if($incident->device)<a href="{{ route('antareja.inventory.show', $incident->device_id) }}">{{ $incident->device->displayName() }}</a> <x-antareja::device-status :device="$incident->device" />@else — @endif</td></tr>
                <tr><th>Site</th><td>{{ $incident->site ? $incident->site->code . ' · ' . $incident->site->name : '—' }}</td></tr>
                <tr><th>PIC</th><td>{{ $incident->picLabel() ?? 'belum ditugaskan' }}</td></tr>
                <tr><th>Sumber</th><td>{{ ['manual' => 'Manual', 'problem' => 'Dari problem', 'auto' => 'Otomatis dari problem'][$incident->source] ?? $incident->source }}{{ $incident->creator ? ' · ' . $incident->creator->username : '' }}</td></tr>
                <tr><th>Waktu acknowledge</th><td>{{ $incident->acknowledged_at?->format('d M Y H:i') ?? '—' }} <span class="ant-muted tw:text-xs">({{ $fmt($incident->secondsToAck()) }})</span></td></tr>
                <tr><th>Waktu resolved</th><td>{{ $incident->resolved_at?->format('d M Y H:i') ?? '—' }} <span class="ant-muted tw:text-xs">({{ $fmt($incident->secondsToResolve()) }})</span></td></tr>
                <tr><th>Ditutup</th><td>{{ $incident->closed_at?->format('d M Y H:i') ?? '—' }}</td></tr>
                <tr><th>Eskalasi</th><td>{{ $policy?->name ?? 'tidak ada kebijakan yang cocok' }}@if($incident->last_escalation_step >= 0) · tahap {{ $incident->last_escalation_step + 1 }} terkirim @endif</td></tr>
                @if($incident->description)<tr><th>Deskripsi</th><td class="tw:whitespace-pre-line">{{ $incident->description }}</td></tr>@endif
                @if($incident->root_cause)<tr><th>Root cause</th><td class="tw:whitespace-pre-line">{{ $incident->root_cause }}</td></tr>@endif
                @if($incident->corrective_action)<tr><th>Corrective action</th><td class="tw:whitespace-pre-line">{{ $incident->corrective_action }}</td></tr>@endif
            </table>
        </div>

        <div class="panel panel-default">
            <div class="panel-heading"><strong>Problem terkait ({{ $incident->problems->count() }})</strong></div>
            <ul class="list-group">
                @forelse($incident->problems as $p)
                    <li class="list-group-item tw:flex tw:justify-between tw:gap-2 tw:items-center">
                        <span><a href="{{ route('antareja.problems.show', $p) }}">{{ $p->title }}</a>
                            <span class="tw:text-xs ant-muted">· {{ $p->duration() }}{{ $p->parent_problem_id ? ' · terdampak' : '' }}</span></span>
                        <x-antareja::status :status="$p->isOpen() ? $p->severityMeta()['tone'] : 'ok'" size="sm">{{ $p->isOpen() ? 'Aktif' : 'Selesai' }}</x-antareja::status>
                    </li>
                @empty
                    <li class="list-group-item ant-muted">Tidak ada problem monitoring terkait (incident manual).</li>
                @endforelse
            </ul>
        </div>

        <div class="panel panel-default">
            <div class="panel-heading"><strong>Evidence ({{ $attachments->count() }})</strong></div>
            <div class="panel-body">
                @if($attachments->isNotEmpty())
                    <ul class="tw:pl-5 tw:mb-3">
                        @foreach($attachments as $f)
                            <li><a href="{{ route('antareja.attachments.show', $f) }}" target="_blank" rel="noopener">{{ $f->original_name }}</a>
                                <span class="tw:text-xs ant-muted">{{ $f->description }} · {{ $f->humanSize() }} · {{ $f->created_at->format('d M H:i') }}</span></li>
                        @endforeach
                    </ul>
                @endif
                @can('ant-incident.update')
                    <form method="post" action="{{ route('antareja.incidents.attach', $incident) }}" enctype="multipart/form-data" class="tw:flex tw:flex-wrap tw:gap-2">
                        @csrf
                        <input type="file" name="file" accept=".jpg,.jpeg,.png,.webp,.pdf" required class="form-control tw:max-w-xs" aria-label="File evidence">
                        <input type="text" name="description" maxlength="255" class="form-control tw:max-w-xs" placeholder="Keterangan" aria-label="Keterangan">
                        <button class="btn btn-default"><i class="fa fa-upload"></i> Unggah</button>
                    </form>
                @endcan
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        @can('ant-incident.update')
            <form method="post" action="{{ route('antareja.incidents.assign', $incident) }}" class="panel panel-default">
                @csrf
                <div class="panel-heading"><strong>PIC</strong></div>
                <div class="panel-body">
                    <div class="form-group">
                        <select name="assigned_to" class="form-control" aria-label="PIC user">
                            <option value="">— tidak ada —</option>
                            @foreach($users as $u)<option value="{{ $u->user_id }}" @selected($incident->assigned_to === $u->user_id)>{{ $u->realname ?: $u->username }}</option>@endforeach
                        </select>
                    </div>
                    <div class="form-group"><input name="pic_name" class="form-control" maxlength="128" value="{{ $incident->pic_name }}" placeholder="atau nama PIC lain" aria-label="Nama PIC"></div>
                    <button class="btn btn-default">Simpan PIC</button>
                </div>
            </form>

            <form method="post" action="{{ route('antareja.incidents.note', $incident) }}" class="panel panel-default">
                @csrf
                <div class="panel-heading"><strong>Work note</strong></div>
                <div class="panel-body">
                    <textarea name="body" class="form-control tw:mb-2" rows="3" required maxlength="5000" placeholder="Tindakan yang dilakukan, temuan di lapangan" aria-label="Work note"></textarea>
                    <button class="btn btn-default"><i class="fa fa-plus"></i> Tambah catatan</button>
                </div>
            </form>
        @endcan

        <div class="panel panel-default">
            <div class="panel-heading"><strong>Riwayat</strong></div>
            <ul class="list-group tw:text-sm">
                @foreach($incident->notes as $n)
                    <li class="list-group-item">
                        <strong>{{ \Antareja\Models\IncidentNote::TYPES[$n->type] ?? $n->type }}</strong>
                        @if($n->type === 'status') {{ \Antareja\Models\Incident::STATUSES[$n->old_value]['label'] ?? $n->old_value }} → {{ \Antareja\Models\Incident::STATUSES[$n->new_value]['label'] ?? $n->new_value }}@endif
                        @if($n->body)<div class="tw:whitespace-pre-line">{{ $n->body }}</div>@endif
                        <div class="tw:text-xs ant-muted">{{ $n->created_at->format('d M Y H:i') }} · {{ $n->user?->username ?? 'sistem' }}</div>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
@endsection
