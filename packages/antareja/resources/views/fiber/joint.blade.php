@extends('antareja::layouts.page')

@php($canEdit = auth()->user()->can('ant-fiber.update'))

@section('title', $joint->code)
@section('breadcrumb')
    <li><a href="{{ route('antareja.fiber.index') }}">Fiber Optic</a></li>
    <li class="active">{{ $joint->code }}</li>
@endsection
@section('heading'){{ \Antareja\Models\FiberJoint::TYPES[$joint->type] }} {{ $joint->code }}@endsection
@section('subheading', ($joint->location ?: 'lokasi belum dicatat') . ($joint->lat !== null ? ' · ' . $joint->lat . ', ' . $joint->lng : ''))

@section('page')
<div class="row">
    <div class="col-lg-8">
        <div class="panel panel-default">
            <div class="panel-heading"><strong>Splice ({{ $joint->splices->count() }})</strong></div>
            <div class="table-responsive">
                <table class="table table-condensed tw:m-0">
                    <thead><tr><th>Kabel / core</th><th></th><th>Kabel / core</th><th class="tw:text-right">Loss</th><th></th></tr></thead>
                    <tbody>
                    @forelse($joint->splices as $s)
                        <tr>
                            <td><a href="{{ route('antareja.fiber.cables.show', $s->coreA->cable_id) }}">{{ $s->coreA->cable->code }}</a> @include('antareja::fiber._core-chip', ['core' => $s->coreA])</td>
                            <td aria-hidden="true">↔</td>
                            <td><a href="{{ route('antareja.fiber.cables.show', $s->coreB->cable_id) }}">{{ $s->coreB->cable->code }}</a> @include('antareja::fiber._core-chip', ['core' => $s->coreB])</td>
                            <td class="tw:text-right ant-data">{{ $s->loss_db !== null ? number_format($s->loss_db, 2, ',', '.') . ' dB' : '—' }}</td>
                            <td class="tw:text-right tw:whitespace-nowrap">
                                <a href="{{ route('antareja.fiber.trace', $s->core_a_id) }}" class="btn btn-default btn-xs">Jalur</a>
                                @if($canEdit)<form method="post" action="{{ route('antareja.fiber.splices.destroy', $s) }}" class="tw:inline" onsubmit="return confirm('Hapus splice ini?')">@csrf @method('DELETE')<button class="btn btn-link btn-xs" aria-label="Hapus splice"><i class="fa fa-times"></i></button></form>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="ant-muted">Belum ada splice.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($canEdit)
                <form method="post" action="{{ route('antareja.fiber.splices.store', $joint) }}" class="panel-body tw:flex tw:flex-wrap tw:gap-2 tw:items-end">
                    @csrf
                    @foreach(['core_a_id' => 'Core sisi 1', 'core_b_id' => 'Core sisi 2'] as $f => $l)
                        <select name="{{ $f }}" class="form-control tw:w-64" required aria-label="{{ $l }}">
                            <option value="">{{ $l }}</option>
                            @foreach($cores as $c)<option value="{{ $c->id }}">{{ $c->cable->code }} core {{ $c->core_no }} ({{ $c->colorLabel() }})</option>@endforeach
                        </select>
                    @endforeach
                    <input name="loss_db" type="number" step="0.01" min="0" max="10" class="form-control tw:w-28" placeholder="Loss dB" aria-label="Loss splice">
                    <button class="btn btn-default">Tambah splice</button>
                </form>
            @endif
        </div>
    </div>
    <div class="col-lg-4">
        @if($canEdit)
            <form method="post" action="{{ route('antareja.fiber.joints.update', $joint) }}" class="panel panel-default">
                @csrf @method('PUT')
                <div class="panel-heading"><strong>Data joint</strong></div>
                <div class="panel-body">
                    <div class="form-group"><label for="code">Kode</label><input id="code" name="code" class="form-control" required maxlength="64" value="{{ $joint->code }}"></div>
                    <div class="form-group"><label for="type">Jenis</label><select id="type" name="type" class="form-control">@foreach(\Antareja\Models\FiberJoint::TYPES as $k => $l)<option value="{{ $k }}" @selected($joint->type === $k)>{{ $l }}</option>@endforeach</select></div>
                    <div class="form-group"><label for="site_id">Site</label><select id="site_id" name="site_id" class="form-control"><option value="">—</option>@foreach($sites as $s)<option value="{{ $s->id }}" @selected($joint->site_id === $s->id)>{{ $s->code }}</option>@endforeach</select></div>
                    <div class="form-group"><label for="location">Patokan lokasi</label><input id="location" name="location" class="form-control" maxlength="255" value="{{ $joint->location }}"></div>
                    <div class="row">
                        <div class="form-group col-xs-6"><label for="lat">Latitude</label><input id="lat" name="lat" type="number" step="0.0000001" class="form-control" value="{{ $joint->lat }}"></div>
                        <div class="form-group col-xs-6"><label for="lng">Longitude</label><input id="lng" name="lng" type="number" step="0.0000001" class="form-control" value="{{ $joint->lng }}"></div>
                    </div>
                    <div class="form-group"><label for="notes">Catatan</label><textarea id="notes" name="notes" class="form-control" rows="2">{{ $joint->notes }}</textarea></div>
                    <button class="btn btn-default">Simpan</button>
                </div>
            </form>
        @endif
    </div>
</div>
@endsection
