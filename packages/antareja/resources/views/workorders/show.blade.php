@extends('antareja::layouts.page')

@php
    $WO = \Antareja\Models\WorkOrder::class;
    $next = $WO::TRANSITIONS[$wo->status] ?? [];
    $actionLabel = ['assigned' => 'Tandai ditugaskan', 'in_progress' => 'Mulai kerjakan', 'on_hold' => 'Tahan', 'done' => 'Tandai selesai', 'verified' => 'Verifikasi', 'cancelled' => 'Batalkan'];
    $p = $wo->checklistProgress();
    $canEdit = auth()->user()->can('ant-workorder.update') && ! in_array($wo->status, ['verified', 'cancelled']);
@endphp

@section('title', $wo->number)
@section('breadcrumb')
    <li><a href="{{ route('antareja.workorders.index') }}">Work Order</a></li>
    <li class="active">{{ $wo->number }}</li>
@endsection
@section('heading')
    <span class="ant-data">{{ $wo->number }}</span> {{ $wo->title }}
@endsection
@section('subheading')
    <x-antareja::status :status="$wo->priorityMeta()['tone']" size="sm">{{ $wo->priorityMeta()['label'] }}</x-antareja::status>
    <x-antareja::status :status="$wo->statusMeta()['tone']" size="sm">{{ $wo->statusMeta()['label'] }}</x-antareja::status>
    {{ $WO::TYPES[$wo->type] ?? $wo->type }} · tenggat {{ $wo->due_at?->format('d M Y H:i') ?? '—' }}
    @if($wo->isOverdue())<x-antareja::status status="crit" size="sm">lewat tenggat</x-antareja::status>@endif
@endsection

@section('actions')
    @if($canEdit)<a href="{{ route('antareja.workorders.edit', $wo) }}" class="btn btn-default"><i class="fa fa-pencil"></i> Edit</a>@endif
@endsection

@section('page')
<div class="row">
    <div class="col-lg-7">
        @if($canEdit && $next)
            <form method="post" action="{{ route('antareja.workorders.transition', $wo) }}" class="panel panel-default">
                @csrf
                <div class="panel-heading"><strong>Ubah status</strong></div>
                <div class="panel-body">
                    <div class="form-group"><label for="comment">Komentar (opsional)</label><input id="comment" name="comment" class="form-control" maxlength="2000"></div>
                    @if(in_array('done', $next))
                        <div class="form-group">
                            <label for="result_notes">Hasil pekerjaan</label>
                            <textarea id="result_notes" name="result_notes" class="form-control" rows="3" placeholder="Apa yang dikerjakan, temuan, kondisi akhir">{{ old('result_notes', $wo->result_notes) }}</textarea>
                            <p class="help-block tw:text-xs">Wajib diisi dan checklist harus lengkap sebelum ditandai selesai.</p>
                        </div>
                    @endif
                    <div class="tw:flex tw:flex-wrap tw:gap-2">
                        @foreach($next as $to)
                            @if($to !== 'verified' || auth()->user()->can('ant-workorder.verify'))
                                <button name="status" value="{{ $to }}" class="btn {{ $loop->first ? 'btn-primary' : 'btn-default' }}">{{ $to === 'in_progress' && $wo->status === 'done' ? 'Kerjakan ulang' : ($actionLabel[$to] ?? $to) }}</button>
                            @endif
                        @endforeach
                    </div>
                </div>
            </form>
        @endif

        <form method="post" action="{{ route('antareja.workorders.checklist', $wo) }}" class="panel panel-default">
            @csrf
            <div class="panel-heading tw:flex tw:items-center tw:justify-between"><strong>Checklist ({{ $p['done'] }}/{{ $p['total'] }})</strong>
                @if($p['total'])<x-antareja::progress :value="$p['done'] / $p['total'] * 100" inverted :warn-at="100" :crit-at="0" label="Kemajuan checklist" class="tw:w-40" />@endif
            </div>
            <ul class="list-group">
                @forelse($wo->checklist ?? [] as $i => $item)
                    <li class="list-group-item tw:flex tw:items-center tw:gap-2">
                        <label class="tw:flex-1 tw:font-normal tw:m-0 tw:flex tw:items-center tw:gap-2">
                            <input type="checkbox" name="done[]" value="{{ $i }}" @checked(! empty($item['done'])) @disabled(! $canEdit)>
                            <span @class(['tw:line-through ant-muted' => ! empty($item['done'])])>{{ $item['text'] }}</span>
                        </label>
                        @if(! empty($item['done_at']))<span class="tw:text-xs ant-muted">{{ \Carbon\Carbon::parse($item['done_at'])->format('d M H:i') }}</span>@endif
                        @if($canEdit)<label class="tw:text-xs ant-muted tw:font-normal tw:m-0" title="Hapus butir"><input type="checkbox" name="remove[]" value="{{ $i }}"> hapus</label>@endif
                    </li>
                @empty
                    <li class="list-group-item ant-muted">Tidak ada checklist.</li>
                @endforelse
            </ul>
            @if($canEdit)
                <div class="panel-body tw:flex tw:gap-2">
                    <input name="new_item" class="form-control" maxlength="255" placeholder="Tambah butir checklist" aria-label="Butir checklist baru">
                    <button class="btn btn-default">Simpan checklist</button>
                </div>
            @endif
        </form>

        <div class="panel panel-default">
            <div class="panel-heading"><strong>Detail</strong></div>
            <table class="table table-condensed tw:m-0">
                <tr><th class="tw:w-44">Pelaksana</th><td>{{ $wo->assigneeLabel() ?? 'belum ditugaskan' }}</td></tr>
                <tr><th>Lokasi</th><td>{{ $wo->site ? $wo->site->code . ' · ' . $wo->site->name : '—' }}{{ $wo->area ? ' / ' . $wo->area->name : '' }}</td></tr>
                <tr><th>Perangkat</th><td>@if($wo->device)<a href="{{ route('antareja.inventory.show', $wo->device_id) }}">{{ $wo->device->displayName() }}</a> <x-antareja::device-status :device="$wo->device" />@else — @endif</td></tr>
                @if($wo->incident)<tr><th>Incident</th><td><a href="{{ route('antareja.incidents.show', $wo->incident) }}">{{ $wo->incident->number }}</a> {{ $wo->incident->title }}</td></tr>@endif
                @if($wo->maintenance)<tr><th>Maintenance</th><td><a href="{{ route('antareja.maintenance.show', $wo->maintenance) }}">{{ $wo->maintenance->title }}</a></td></tr>@endif
                @if($wo->change)<tr><th>Change request</th><td><a href="{{ route('antareja.changes.show', $wo->change) }}">{{ $wo->change->number }}</a> {{ $wo->change->title }}</td></tr>@endif
                <tr><th>Mulai / selesai</th><td>{{ $wo->started_at?->format('d M Y H:i') ?? '—' }} / {{ $wo->completed_at?->format('d M Y H:i') ?? '—' }}</td></tr>
                @if($wo->verified_at)<tr><th>Verifikasi</th><td>{{ $wo->verified_at->format('d M Y H:i') }} oleh {{ $wo->verifier?->realname ?: $wo->verifier?->username }}</td></tr>@endif
                @if($wo->description)<tr><th>Uraian</th><td class="tw:whitespace-pre-line">{{ $wo->description }}</td></tr>@endif
                @if($wo->result_notes)<tr><th>Hasil</th><td class="tw:whitespace-pre-line">{{ $wo->result_notes }}</td></tr>@endif
            </table>
        </div>

        <div class="panel panel-default">
            <div class="panel-heading"><strong>Material terpakai ({{ $wo->materials->count() }})</strong></div>
            <div class="table-responsive">
                <table class="table table-condensed tw:m-0">
                    <thead><tr><th>Material</th><th class="tw:text-right">Jumlah</th><th>Catatan</th><th></th></tr></thead>
                    <tbody>
                    @forelse($wo->materials as $m)
                        <tr><td>{{ $m->item }}</td><td class="tw:text-right ant-data">{{ rtrim(rtrim(number_format($m->quantity, 2, ',', '.'), '0'), ',') }} {{ $m->unit }}</td><td class="tw:text-xs">{{ $m->notes }}</td>
                            <td class="tw:text-right">@if($canEdit)<form method="post" action="{{ route('antareja.workorders.material.destroy', [$wo, $m->id]) }}">@csrf @method('DELETE')<button class="btn btn-link btn-xs" aria-label="Hapus {{ $m->item }}"><i class="fa fa-times"></i></button></form>@endif</td></tr>
                    @empty
                        <tr><td colspan="4" class="ant-muted">Belum ada.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($canEdit)
                <form method="post" action="{{ route('antareja.workorders.material', $wo) }}" class="panel-body tw:flex tw:flex-wrap tw:gap-2">
                    @csrf
                    <input name="item" class="form-control tw:flex-1 tw:min-w-48" required maxlength="255" placeholder="Patch cord LC-SC SM 3 m" aria-label="Material">
                    <input name="quantity" type="number" step="0.01" min="0.01" class="form-control tw:w-24" required placeholder="Jml" aria-label="Jumlah">
                    <input name="unit" class="form-control tw:w-24" required maxlength="16" value="pcs" aria-label="Satuan">
                    <input name="notes" class="form-control tw:flex-1 tw:min-w-40" maxlength="255" placeholder="Catatan (SN, sumber gudang)" aria-label="Catatan">
                    <button class="btn btn-default">Tambah</button>
                </form>
            @endif
        </div>
    </div>

    <div class="col-lg-5">
        @if($canEdit)
            <form method="post" action="{{ route('antareja.workorders.assign', $wo) }}" class="panel panel-default">
                @csrf
                <div class="panel-heading"><strong>Penugasan</strong></div>
                <div class="panel-body tw:flex tw:flex-col tw:gap-2">
                    <select name="assigned_to" class="form-control" aria-label="Pelaksana"><option value="">— tidak ada —</option>@foreach($users as $u)<option value="{{ $u->user_id }}" @selected($wo->assigned_to === $u->user_id)>{{ $u->realname ?: $u->username }}</option>@endforeach</select>
                    <input name="team" class="form-control" maxlength="128" value="{{ $wo->team }}" placeholder="atau tim / vendor" aria-label="Tim">
                    <button class="btn btn-default tw:self-start">Simpan penugasan</button>
                </div>
            </form>
        @endif

        <div class="panel panel-default">
            <div class="panel-heading"><strong>Evidence ({{ $attachments->count() }})</strong></div>
            <div class="panel-body">
                @foreach($attachments as $f)
                    <div class="tw:text-sm"><a href="{{ route('antareja.attachments.show', $f) }}" target="_blank" rel="noopener">{{ $f->original_name }}</a> <span class="tw:text-xs ant-muted">{{ $f->description }}</span></div>
                @endforeach
                @if($canEdit)
                    <form method="post" action="{{ route('antareja.workorders.attach', $wo) }}" enctype="multipart/form-data" class="tw:mt-2 tw:flex tw:flex-col tw:gap-2">
                        @csrf
                        <input type="file" name="file" accept=".jpg,.jpeg,.png,.webp,.pdf" required class="form-control" aria-label="File evidence">
                        <input type="text" name="description" maxlength="255" class="form-control" placeholder="Keterangan, mis. foto sebelum / sesudah" aria-label="Keterangan">
                        <button class="btn btn-default tw:self-start"><i class="fa fa-upload"></i> Unggah</button>
                    </form>
                @endif
            </div>
        </div>

        @can('ant-workorder.update')
            <form method="post" action="{{ route('antareja.workorders.note', $wo) }}" class="panel panel-default">
                @csrf
                <div class="panel-heading"><strong>Catatan</strong></div>
                <div class="panel-body">
                    <textarea name="body" class="form-control" rows="3" required maxlength="5000" placeholder="Progres, kendala, kebutuhan material" aria-label="Catatan"></textarea>
                    <button class="btn btn-default tw:mt-2"><i class="fa fa-plus"></i> Tambah catatan</button>
                </div>
            </form>
        @endcan

        <div class="panel panel-default">
            <div class="panel-heading"><strong>Riwayat</strong></div>
            <ul class="list-group tw:text-sm">
                @foreach($wo->logs as $l)
                    <li class="list-group-item">
                        <strong>{{ \Antareja\Models\WorkOrderLog::TYPES[$l->type] ?? $l->type }}</strong>
                        @if($l->type === 'status') {{ $WO::STATUSES[$l->old_value]['label'] ?? $l->old_value }} → {{ $WO::STATUSES[$l->new_value]['label'] ?? $l->new_value }}@endif
                        @if($l->body)<div class="tw:whitespace-pre-line">{{ $l->body }}</div>@endif
                        <div class="tw:text-xs ant-muted">{{ $l->created_at->format('d M Y H:i') }} · {{ $l->user?->username ?? 'sistem' }}</div>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
@endsection
