@extends('antareja::layouts.page')

@section('title', $window->title)
@section('breadcrumb')
    <li><a href="{{ route('antareja.maintenance.index') }}">Maintenance Management</a></li>
    <li class="active">{{ $window->title }}</li>
@endsection
@section('heading')
    {{ $window->title }} <x-antareja::status :status="$window->statusMeta()['tone']">{{ $window->statusMeta()['label'] }}</x-antareja::status>
@endsection
@section('subheading', \Antareja\Models\MaintenanceWindow::TYPES[$window->type] . ' · ' . $window->starts_at->format('d M Y H:i') . ' – ' . $window->ends_at->format('d M Y H:i'))

@section('actions')
    @can('ant-maintenance.update')
        @if(! in_array($window->status, ['completed', 'cancelled']))
            <a href="{{ route('antareja.maintenance.edit', $window) }}" class="btn btn-default"><i class="fa fa-pencil"></i> Edit</a>
            <form method="post" action="{{ route('antareja.maintenance.cancel', $window) }}" onsubmit="return confirm('Batalkan maintenance ini?')">@csrf<button class="btn btn-default">Batalkan</button></form>
        @endif
    @endcan
@endsection

@section('page')
<div class="row">
    <div class="col-lg-7">
        <div class="panel panel-default">
            <div class="panel-heading"><strong>Detail</strong></div>
            <table class="table table-condensed tw:m-0">
                <tr><th class="tw:w-48">Site</th><td>{{ $window->site ? $window->site->code . ' · ' . $window->site->name : '—' }}</td></tr>
                <tr><th>PIC</th><td>{{ $window->picLabel() ?? '—' }}</td></tr>
                <tr><th>SLA</th><td>{{ $window->exclude_from_sla ? 'Waktu maintenance dikecualikan dari SLA' : 'Tetap dihitung dalam SLA' }}</td></tr>
                <tr><th>Penekanan alert poller</th><td>{{ $window->alert_schedule_id ? 'aktif (jadwal maintenance #' . $window->alert_schedule_id . ')' : 'tidak ada' }}</td></tr>
                @if($window->description)<tr><th>Rencana kerja</th><td class="tw:whitespace-pre-line">{{ $window->description }}</td></tr>@endif
                @if($window->status === 'completed')
                    <tr><th>Hasil</th><td>{{ \Antareja\Models\MaintenanceWindow::RESULTS[$window->result] ?? $window->result }} · {{ $window->completed_at?->format('d M Y H:i') }}</td></tr>
                    <tr><th>Pekerjaan dilakukan</th><td class="tw:whitespace-pre-line">{{ $window->work_done }}</td></tr>
                @endif
            </table>
        </div>
        <div class="panel panel-default">
            <div class="panel-heading"><strong>Perangkat terdampak ({{ $window->devices->count() }})</strong></div>
            <div class="table-responsive">
                <table class="table table-condensed tw:m-0">
                    <tbody>
                    @foreach($window->devices as $d)
                        <tr><td><a href="{{ route('antareja.inventory.show', $d->device_id) }}">{{ $d->displayName() }}</a></td><td class="ant-data">{{ $d->overwrite_ip ?: $d->ip }}</td><td><x-antareja::device-status :device="$d" /></td></tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        @can('ant-maintenance.update')
            @if(! in_array($window->status, ['completed', 'cancelled']))
                <form method="post" action="{{ route('antareja.maintenance.complete', $window) }}" class="panel panel-default">
                    @csrf
                    <div class="panel-heading"><strong>Laporan penyelesaian</strong></div>
                    <div class="panel-body">
                        <div class="form-group">
                            <label for="work_done">Pekerjaan yang dilakukan *</label>
                            <textarea id="work_done" name="work_done" class="form-control" rows="4" required maxlength="5000">{{ old('work_done') }}</textarea>
                        </div>
                        <div class="form-group">
                            <label for="result">Hasil *</label>
                            <select id="result" name="result" class="form-control">
                                @foreach(\Antareja\Models\MaintenanceWindow::RESULTS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                            </select>
                        </div>
                        <button class="btn btn-primary"><i class="fa fa-check"></i> Tandai selesai</button>
                        <p class="help-block tw:text-xs tw:mb-0">Penekanan alert diakhiri saat itu juga bila selesai lebih cepat.</p>
                    </div>
                </form>
            @endif
        @endcan
        <div class="panel panel-default">
            <div class="panel-heading"><strong>Evidence ({{ $attachments->count() }})</strong></div>
            <div class="panel-body">
                @foreach($attachments as $f)
                    <div class="tw:text-sm"><a href="{{ route('antareja.attachments.show', $f) }}" target="_blank" rel="noopener">{{ $f->original_name }}</a> <span class="tw:text-xs ant-muted">{{ $f->description }}</span></div>
                @endforeach
                @can('ant-maintenance.update')
                    <form method="post" action="{{ route('antareja.maintenance.attach', $window) }}" enctype="multipart/form-data" class="tw:mt-2 tw:flex tw:flex-col tw:gap-2">
                        @csrf
                        <input type="file" name="file" accept=".jpg,.jpeg,.png,.webp,.pdf" required class="form-control" aria-label="File evidence">
                        <input type="text" name="description" maxlength="255" class="form-control" placeholder="Keterangan, mis. foto sebelum / sesudah" aria-label="Keterangan">
                        <button class="btn btn-default tw:self-start"><i class="fa fa-upload"></i> Unggah</button>
                    </form>
                @endcan
            </div>
        </div>
    </div>
</div>
@endsection
