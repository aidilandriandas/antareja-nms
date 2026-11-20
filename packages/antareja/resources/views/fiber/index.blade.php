@extends('antareja::layouts.page')

@section('title', 'Fiber Optic')
@section('breadcrumb')<li class="active">Fiber Optic</li>@endsection
@section('heading', 'Fiber Optic Management')
@section('subheading', 'Kabel & core (warna TIA-598), ODF / patch panel, joint & splice, uji OTDR, penelusuran jalur end-to-end, dan lokasi gangguan dari jarak OTDR.')

@section('actions')
    @can('ant-fiber.update')<a href="{{ route('antareja.fiber.cables.create') }}" class="btn btn-primary"><i class="fa fa-plus"></i> Tambah kabel</a>@endcan
@endsection

@section('page')
    @php($total = $coreStats->sum())
    <div class="tw:grid tw:grid-cols-2 tw:md:grid-cols-5 tw:gap-3 tw:mb-5">
        <x-antareja::stat label="Kabel" :value="$cables->count()" :hint="number_format($cables->sum('length_m') / 1000, 1, ',', '.') . ' km tercatat'" />
        <x-antareja::stat label="Core total" :value="$total" />
        <x-antareja::stat label="Core terpakai" :value="$coreStats['used'] ?? 0" :hint="$total ? round(($coreStats['used'] ?? 0) / $total * 100) . '%' : null" />
        <x-antareja::stat label="Core kosong" :value="$coreStats['free'] ?? 0" tone="ok" />
        <x-antareja::stat label="Core putus / rusak" :value="$coreStats['broken'] ?? 0" :tone="($coreStats['broken'] ?? 0) ? 'crit' : 'ok'" />
    </div>

    <div class="panel panel-default">
        <div class="panel-heading"><strong>Kabel</strong></div>
        <div class="table-responsive">
            <table class="table table-condensed table-hover tw:m-0">
                <thead><tr><th>Kode</th><th>Nama</th><th>Ujung A</th><th>Ujung B</th><th class="tw:text-right">Panjang</th><th class="tw:w-48">Core terpakai</th><th>Status</th></tr></thead>
                <tbody>
                @forelse($cables as $c)
                    @php($pc = $perCable[$c->id] ?? null)
                    <tr>
                        <td class="ant-data"><a href="{{ route('antareja.fiber.cables.show', $c) }}">{{ $c->code }}</a></td>
                        <td>{{ $c->name }} <span class="tw:text-xs ant-muted">{{ strtoupper($c->mode) }} {{ $c->core_count }}C</span></td>
                        <td class="tw:text-xs">{{ $c->endpointLabel('a') }}</td>
                        <td class="tw:text-xs">{{ $c->endpointLabel('b') }}</td>
                        <td class="tw:text-right ant-data">{{ $c->length_m ? number_format($c->length_m, 0, ',', '.') . ' m' : '—' }}</td>
                        <td>@if($pc)<x-antareja::progress :value="$pc->total ? $pc->used / $pc->total * 100 : 0" :label="'Core terpakai ' . $c->code" />@endif
                            @if($pc && $pc->broken)<span class="tw:text-xs ant-text-crit">{{ $pc->broken }} putus</span>@endif</td>
                        <td><x-antareja::status :status="$c->statusMeta()['tone']" size="sm">{{ $c->statusMeta()['label'] }}</x-antareja::status></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="tw:text-center tw:py-6 ant-muted">Belum ada kabel. Buat ODF dan/atau joint dulu sebagai titik ujung kabel.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="panel panel-default">
                <div class="panel-heading"><strong>ODF / patch panel</strong></div>
                <ul class="list-group">
                    @forelse($odfs as $o)
                        <a href="{{ route('antareja.fiber.odfs.show', $o) }}" class="list-group-item tw:flex tw:justify-between tw:gap-2"><span>{{ $o->label() }}{{ $o->area ? ' / ' . $o->area->name : '' }}{{ $o->rack ? ' · rak ' . $o->rack : '' }}</span><span class="tw:text-xs ant-muted">{{ $o->used_ports }}/{{ $o->port_count }} port · {{ $o->connector }}</span></a>
                    @empty
                        <li class="list-group-item ant-muted">Belum ada ODF.</li>
                    @endforelse
                </ul>
                @can('ant-fiber.update')
                    <form method="post" action="{{ route('antareja.fiber.odfs.store') }}" class="panel-body tw:flex tw:flex-wrap tw:gap-2">
                        @csrf
                        <input name="name" class="form-control tw:flex-1 tw:min-w-40" required maxlength="128" placeholder="Nama ODF, mis. ODF-PIT-A-01" aria-label="Nama ODF">
                        <select name="site_id" class="form-control tw:w-32" aria-label="Site"><option value="">Site</option>@foreach(\Antareja\Models\Site::orderBy('code')->get() as $s)<option value="{{ $s->id }}">{{ $s->code }}</option>@endforeach</select>
                        <input name="port_count" type="number" min="1" max="288" value="24" class="form-control tw:w-24" required aria-label="Jumlah port">
                        <select name="connector" class="form-control tw:w-24" aria-label="Konektor">@foreach(['SC', 'LC', 'FC', 'ST'] as $k)<option>{{ $k }}</option>@endforeach</select>
                        <button class="btn btn-default">Tambah ODF</button>
                    </form>
                @endcan
            </div>
        </div>
        <div class="col-lg-6">
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Joint / ODP</strong></div>
                <ul class="list-group">
                    @forelse($joints as $j)
                        <a href="{{ route('antareja.fiber.joints.show', $j) }}" class="list-group-item tw:flex tw:justify-between tw:gap-2"><span>{{ $j->label() }}</span><span class="tw:text-xs ant-muted">{{ \Antareja\Models\FiberJoint::TYPES[$j->type] }} · {{ $j->splices_count }} splice</span></a>
                    @empty
                        <li class="list-group-item ant-muted">Belum ada joint.</li>
                    @endforelse
                </ul>
                @can('ant-fiber.update')
                    <form method="post" action="{{ route('antareja.fiber.joints.store') }}" class="panel-body tw:flex tw:flex-wrap tw:gap-2">
                        @csrf
                        <input name="code" class="form-control tw:w-40" required maxlength="64" placeholder="Kode, mis. JC-HL-07" aria-label="Kode joint">
                        <select name="type" class="form-control tw:w-40" aria-label="Jenis">@foreach(\Antareja\Models\FiberJoint::TYPES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
                        <input name="location" class="form-control tw:flex-1 tw:min-w-40" maxlength="255" placeholder="Patokan: tiang 37, KM 4 hauling road" aria-label="Lokasi">
                        <button class="btn btn-default">Tambah joint</button>
                    </form>
                @endcan
            </div>
        </div>
    </div>
@endsection
