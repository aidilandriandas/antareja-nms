@extends('antareja::layouts.page')

@php
    $C = \Antareja\Models\Change::class;
    $c = $change;
    $me = auth()->user();
    $isRequester = $me->user_id === $c->requested_by;
    // Aksi yang tersedia sesuai status & hak akses
    $actions = [];
    if ($me->can('ant-change.approve') && $c->status === 'submitted' && ! $isRequester) {
        $actions[] = ['approve', 'Setujui', 'btn-primary'];
        $actions[] = ['reject', 'Tolak', 'btn-default'];
    }
    if ($me->can('ant-change.update')) {
        if ($c->status === 'draft') { $actions[] = ['submit', 'Ajukan untuk persetujuan', 'btn-primary']; }
        if ($c->status === 'approved') { $actions[] = ['start', 'Mulai pelaksanaan', 'btn-primary']; }
        if (in_array($c->status, ['draft', 'submitted', 'approved', 'rejected'])) { $actions[] = ['cancel', 'Batalkan', 'btn-default']; }
    }
@endphp

@section('title', $c->number)
@section('breadcrumb')
    <li><a href="{{ route('antareja.changes.index') }}">Change Request</a></li>
    <li class="active">{{ $c->number }}</li>
@endsection
@section('heading')<span class="ant-data">{{ $c->number }}</span> {{ $c->title }}@endsection
@section('subheading')
    <x-antareja::status :status="$c->statusMeta()['tone']" size="sm">{{ $c->statusMeta()['label'] }}</x-antareja::status>
    <x-antareja::status :status="$c->riskMeta()['tone']" size="sm">risiko {{ strtolower($c->riskMeta()['label']) }}</x-antareja::status>
    {{ $C::TYPES[$c->type] ?? $c->type }}
@endsection

@section('actions')
    @can('ant-change.update')
        @if(in_array($c->status, ['draft', 'submitted', 'rejected']))<a href="{{ route('antareja.changes.edit', $c) }}" class="btn btn-default"><i class="fa fa-pencil"></i> Edit</a>@endif
        @if($c->status === 'approved')<a href="{{ route('antareja.workorders.create', ['change' => $c->id]) }}" class="btn btn-default"><i class="fa fa-clipboard-list"></i> Buat work order</a>@endif
    @endcan
@endsection

@section('page')
<div class="row">
    <div class="col-lg-7">
        @if($actions || ($c->status === 'in_progress' && $me->can('ant-change.update')))
            <form method="post" action="{{ route('antareja.changes.action', $c) }}" class="panel panel-default">
                @csrf
                <div class="panel-heading"><strong>Tindakan</strong></div>
                <div class="panel-body">
                    @if($c->status === 'in_progress')
                        <div class="row">
                            <div class="form-group col-sm-4">
                                <label for="result">Hasil *</label>
                                <select id="result" name="result" class="form-control">@foreach($C::RESULTS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
                            </div>
                            <div class="form-group col-sm-8"><label for="note">Review pelaksanaan *</label><textarea id="note" name="note" class="form-control" rows="2" placeholder="Apa yang dilakukan, hasil uji, kendala"></textarea></div>
                        </div>
                        <button name="action" value="finish" class="btn btn-primary"><i class="fa fa-check"></i> Selesaikan</button>
                    @else
                        <div class="form-group"><label for="note">Catatan{{ $c->status === 'submitted' ? ' persetujuan / alasan penolakan' : '' }}</label><input id="note" name="note" class="form-control" maxlength="5000"></div>
                        <div class="tw:flex tw:flex-wrap tw:gap-2">
                            @foreach($actions as [$a, $label, $cls])<button name="action" value="{{ $a }}" class="btn {{ $cls }}" @if($a === 'cancel') onclick="return confirm('Batalkan change ini?')" @endif>{{ $label }}</button>@endforeach
                        </div>
                    @endif
                    @if($c->status === 'submitted' && $isRequester)<p class="help-block tw:text-xs tw:mb-0 tw:mt-2">Menunggu persetujuan orang lain — pengaju tidak dapat menyetujui sendiri.</p>@endif
                </div>
            </form>
        @elseif($c->status === 'submitted')
            <x-antareja::alert tone="info">Menunggu persetujuan dari pemegang hak <em>approve</em> selain pengaju.</x-antareja::alert>
        @endif

        <div class="panel panel-default">
            <div class="panel-heading"><strong>Rencana</strong></div>
            <table class="table table-condensed tw:m-0">
                <tr><th class="tw:w-44">Jadwal</th><td>{{ $c->planned_start?->format('d M Y H:i') ?? '—' }} – {{ $c->planned_end?->format('d M Y H:i') ?? '—' }}</td></tr>
                <tr><th>Site</th><td>{{ $c->site ? $c->site->code . ' · ' . $c->site->name : '—' }}</td></tr>
                <tr><th>Rencana implementasi</th><td class="tw:whitespace-pre-line">{{ $c->description ?: '—' }}</td></tr>
                <tr><th>Dampak</th><td class="tw:whitespace-pre-line">{{ $c->impact ?: '—' }}</td></tr>
                <tr><th>Rollback</th><td class="tw:whitespace-pre-line">{{ $c->rollback_plan ?: '—' }}</td></tr>
                <tr><th>Rencana uji</th><td class="tw:whitespace-pre-line">{{ $c->test_plan ?: '—' }}</td></tr>
                <tr><th>SLA</th><td>{{ $c->exclude_from_sla ? 'jendela change dikecualikan' : 'tetap dihitung' }}</td></tr>
            </table>
        </div>

        <div class="panel panel-default">
            <div class="panel-heading"><strong>Perangkat terdampak ({{ $c->devices->count() }})</strong></div>
            <div class="table-responsive">
                <table class="table table-condensed tw:m-0">
                    <tbody>
                    @forelse($c->devices as $d)
                        <tr><td><a href="{{ route('antareja.inventory.show', $d->device_id) }}">{{ $d->displayName() }}</a></td><td class="ant-data">{{ $d->overwrite_ip ?: $d->ip }}</td><td><x-antareja::device-status :device="$d" /></td>
                            <td>@can('ant-config.view')<a href="{{ route('antareja.config.device', $d->device_id) }}" class="tw:text-xs">konfigurasi</a>@endcan</td></tr>
                    @empty
                        <tr><td class="ant-muted">Belum ada perangkat.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($configVersions->isNotEmpty())
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Perubahan konfigurasi tercatat dalam change ini</strong></div>
                <ul class="list-group">
                    @foreach($configVersions as $v)
                        <li class="list-group-item tw:text-sm"><a href="{{ route('antareja.config.device', ['device' => $v->device_id, 'b' => $v->id]) }}">{{ $v->device?->displayName() }}</a> · {{ $v->collected_at->format('d M H:i') }} · <span class="ant-data">+{{ $v->lines_added }} / −{{ $v->lines_removed }}</span></li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
    <div class="col-lg-5">
        <div class="panel panel-default">
            <div class="panel-heading"><strong>Persetujuan</strong></div>
            <table class="table table-condensed tw:m-0">
                <tr><th class="tw:w-36">Pengaju</th><td>{{ $c->requester?->realname ?: $c->requester?->username ?? '—' }} · {{ $c->created_at->format('d M Y H:i') }}</td></tr>
                <tr><th>Disetujui</th><td>{{ $c->approver ? ($c->approver->realname ?: $c->approver->username) . ' · ' . $c->approved_at?->format('d M Y H:i') : '—' }}</td></tr>
                @if($c->approval_note)<tr><th>Catatan</th><td class="tw:whitespace-pre-line">{{ $c->approval_note }}</td></tr>@endif
                @if($c->maintenance)<tr><th>Maintenance</th><td><a href="{{ route('antareja.maintenance.show', $c->maintenance) }}">{{ $c->maintenance->title }}</a> <x-antareja::status :status="$c->maintenance->statusMeta()['tone']" size="sm">{{ $c->maintenance->statusMeta()['label'] }}</x-antareja::status></td></tr>@endif
                @if($c->implemented_at)<tr><th>Dilaksanakan</th><td>{{ $c->implemented_at->format('d M Y H:i') }}</td></tr>@endif
                @if($c->result)<tr><th>Hasil</th><td>{{ $C::RESULTS[$c->result] }}@if($c->review_notes)<div class="tw:whitespace-pre-line tw:text-sm">{{ $c->review_notes }}</div>@endif</td></tr>@endif
            </table>
        </div>
        @if($c->workOrders->isNotEmpty())
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Work order</strong></div>
                <ul class="list-group">
                    @foreach($c->workOrders as $wo)<li class="list-group-item tw:text-sm"><a href="{{ route('antareja.workorders.show', $wo) }}">{{ $wo->number }}</a> {{ $wo->title }} <x-antareja::status :status="$wo->statusMeta()['tone']" size="sm">{{ $wo->statusMeta()['label'] }}</x-antareja::status></li>@endforeach
                </ul>
            </div>
        @endif
        <form method="post" action="{{ route('antareja.changes.action', $c) }}" class="panel panel-default">
            @csrf
            <div class="panel-heading"><strong>Riwayat</strong></div>
            <ul class="list-group tw:text-sm">
                @foreach($c->logs as $l)
                    <li class="list-group-item">
                        <strong>{{ ['status' => 'Status', 'note' => 'Catatan', 'system' => 'Sistem'][$l->type] ?? $l->type }}</strong>
                        @if($l->type === 'status') {{ $C::STATUSES[$l->old_value]['label'] ?? $l->old_value }} → {{ $C::STATUSES[$l->new_value]['label'] ?? $l->new_value }}@endif
                        @if($l->body)<div class="tw:whitespace-pre-line">{{ $l->body }}</div>@endif
                        <div class="tw:text-xs ant-muted">{{ $l->created_at->format('d M Y H:i') }} · {{ $l->user?->username ?? 'sistem' }}</div>
                    </li>
                @endforeach
            </ul>
            <div class="panel-body tw:flex tw:gap-2">
                <input name="note" class="form-control" maxlength="5000" placeholder="Tambah catatan" aria-label="Catatan" required>
                <button name="action" value="note" class="btn btn-default">Tambah</button>
            </div>
        </form>
    </div>
</div>
@endsection
