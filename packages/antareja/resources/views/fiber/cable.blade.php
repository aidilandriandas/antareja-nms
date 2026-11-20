@extends('antareja::layouts.page')

@php
    $C = \Antareja\Models\FiberCable::class;
    $CO = \Antareja\Models\FiberCore::class;
    $canEdit = auth()->user()->can('ant-fiber.update');
    $spliceOf = [];
    foreach ($splices as $s) { $spliceOf[$s->core_a_id][] = $s; $spliceOf[$s->core_b_id][] = $s; }
@endphp

@section('title', $cable->code)
@section('breadcrumb')
    <li><a href="{{ route('antareja.fiber.index') }}">Fiber Optic</a></li>
    <li class="active">{{ $cable->code }}</li>
@endsection
@section('heading')<span class="ant-data">{{ $cable->code }}</span> {{ $cable->name }} <x-antareja::status :status="$cable->statusMeta()['tone']">{{ $cable->statusMeta()['label'] }}</x-antareja::status>@endsection
@section('subheading', $cable->endpointLabel('a') . ' → ' . $cable->endpointLabel('b') . ' · ' . strtoupper($cable->mode) . ' ' . ($cable->fiber_type ?? '') . ' · ' . $cable->core_count . ' core · ' . ($cable->length_m ? number_format($cable->length_m, 0, ',', '.') . ' m' : 'panjang belum dicatat') . ' · ' . ($C::INSTALLATION[$cable->installation] ?? ''))

@section('actions')
    @if($canEdit)<a href="{{ route('antareja.fiber.cables.edit', $cable) }}" class="btn btn-default"><i class="fa fa-pencil"></i> Edit</a>@endif
@endsection

@section('page')
    @if($cable->route)<p class="tw:text-sm tw:whitespace-pre-line">{{ $cable->route }}</p>@endif
    <div class="panel panel-default">
        <div class="panel-heading"><strong>Core</strong> <span class="tw:text-xs ant-muted">warna TIA-598: Biru, Oranye, Hijau, Cokelat, Abu, Putih, Merah, Hitam, Kuning, Ungu, Merah muda, Aqua</span></div>
        <div class="table-responsive">
            <table class="table table-condensed tw:m-0">
                <thead><tr><th>Core</th><th>Warna</th><th>Status</th><th>Layanan</th><th>Terhubung</th><th class="tw:text-right">Loss</th><th></th></tr></thead>
                <tbody>
                @foreach($cores as $core)
                    @php($core->setRelation('cable', $cable))
                    <tr>
                        <td>@include('antareja::fiber._core-chip', ['core' => $core])</td>
                        <td class="tw:text-xs">{{ $core->colorLabel() }}</td>
                        <td><x-antareja::status :status="$core->statusMeta()['tone']" size="sm">{{ $core->statusMeta()['label'] }}</x-antareja::status></td>
                        <td>{{ $core->service ?? '—' }}</td>
                        <td class="tw:text-xs">
                            @foreach($ports[$core->id] ?? [] as $p)
                                <div><a href="{{ route('antareja.fiber.odfs.show', $p->odf_id) }}">{{ $p->label() }}</a>@if($p->device) → {{ $p->device->displayName() }} {{ $p->port?->getShortLabel() }}@endif</div>
                            @endforeach
                            @foreach($spliceOf[$core->id] ?? [] as $s)
                                @php($other = $s->core_a_id === $core->id ? $s->coreB : $s->coreA)
                                <div>splice <a href="{{ route('antareja.fiber.joints.show', $s->joint_id) }}">{{ $s->joint->code }}</a> → {{ $other->label() }}</div>
                            @endforeach
                        </td>
                        <td class="tw:text-right ant-data">{{ $core->loss_db !== null ? number_format($core->loss_db, 2, ',', '.') . ' dB' : '—' }}</td>
                        <td class="tw:text-right tw:whitespace-nowrap">
                            <a href="{{ route('antareja.fiber.trace', $core) }}" class="btn btn-default btn-xs">Jalur</a>
                            @if($canEdit)<button type="button" class="btn btn-default btn-xs" onclick="document.getElementById('core-edit-{{ $core->id }}').hidden = !document.getElementById('core-edit-{{ $core->id }}').hidden" aria-controls="core-edit-{{ $core->id }}">Ubah</button>@endif
                        </td>
                    </tr>
                    @if($canEdit)
                        <tr id="core-edit-{{ $core->id }}" hidden>
                            <td colspan="7" class="ant-sunken">
                                <form method="post" action="{{ route('antareja.fiber.cores.update', $core) }}" class="tw:flex tw:flex-wrap tw:gap-2 tw:items-end">
                                    @csrf @method('PUT')
                                    <select name="status" class="form-control input-sm tw:w-40" aria-label="Status core {{ $core->core_no }}">@foreach($CO::STATUSES as $k => $m)<option value="{{ $k }}" @selected($core->status === $k)>{{ $m['label'] }}</option>@endforeach</select>
                                    <input name="service" class="form-control input-sm tw:flex-1 tw:min-w-48" maxlength="255" value="{{ $core->service }}" placeholder="Layanan, mis. Uplink SW-PIT-A Gi0/1" aria-label="Layanan">
                                    <input name="loss_db" type="number" step="0.01" min="0" max="100" class="form-control input-sm tw:w-28" value="{{ $core->loss_db }}" placeholder="Loss dB" aria-label="Loss dB">
                                    <input name="notes" class="form-control input-sm tw:flex-1 tw:min-w-40" maxlength="255" value="{{ $core->notes }}" placeholder="Catatan" aria-label="Catatan">
                                    <button class="btn btn-primary btn-sm">Simpan</button>
                                </form>
                                <form method="post" action="{{ route('antareja.fiber.cores.tests', $core) }}" class="tw:flex tw:flex-wrap tw:gap-2 tw:items-end tw:mt-2">
                                    @csrf
                                    <span class="tw:text-xs tw:font-semibold tw:self-center">Hasil uji:</span>
                                    <input name="tested_at" type="datetime-local" class="form-control input-sm tw:w-48" required value="{{ now()->format('Y-m-d\TH:i') }}" aria-label="Waktu uji">
                                    <select name="method" class="form-control input-sm tw:w-24" aria-label="Metode"><option value="otdr">OTDR</option><option value="opm">Power meter</option></select>
                                    <select name="wavelength_nm" class="form-control input-sm tw:w-24" aria-label="Panjang gelombang">@foreach([1310, 1550, 1625, 850, 1300] as $wl)<option value="{{ $wl }}">{{ $wl }} nm</option>@endforeach</select>
                                    <input name="length_m" type="number" step="0.1" class="form-control input-sm tw:w-28" placeholder="Panjang m" aria-label="Panjang terukur">
                                    <input name="total_loss_db" type="number" step="0.01" class="form-control input-sm tw:w-28" placeholder="Loss dB" aria-label="Loss total">
                                    <select name="result" class="form-control input-sm tw:w-24" aria-label="Hasil"><option value="pass">Lulus</option><option value="fail">Gagal</option></select>
                                    <input name="events" class="form-control input-sm tw:flex-1 tw:min-w-48" maxlength="5000" placeholder="Event: 1.240 m splice 0,08 dB; 3.105 m bending 0,4 dB" aria-label="Event OTDR">
                                    <button class="btn btn-default btn-sm">Catat uji</button>
                                </form>
                                @if($core->tests->isNotEmpty())
                                    <ul class="tw:text-xs tw:mt-2 tw:mb-0 tw:pl-4">
                                        @foreach($core->tests->take(5) as $t)<li>{{ $t->tested_at->format('d M Y') }} · {{ strtoupper($t->method) }} {{ $t->wavelength_nm }} nm · {{ $t->length_m ? number_format($t->length_m, 0, ',', '.') . ' m' : '' }} · {{ $t->total_loss_db !== null ? number_format($t->total_loss_db, 2, ',', '.') . ' dB' : '' }} · {{ $t->result === 'pass' ? 'lulus' : 'gagal' }} {{ $t->events ? '· ' . $t->events : '' }}</li>@endforeach
                                    </ul>
                                @endif
                            </td>
                        </tr>
                    @endif
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
