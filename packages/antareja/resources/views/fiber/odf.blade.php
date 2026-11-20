@extends('antareja::layouts.page')

@php($canEdit = auth()->user()->can('ant-fiber.update'))

@section('title', $odf->name)
@section('breadcrumb')
    <li><a href="{{ route('antareja.fiber.index') }}">Fiber Optic</a></li>
    <li class="active">{{ $odf->name }}</li>
@endsection
@section('heading')ODF {{ $odf->name }}@endsection
@section('subheading', ($odf->site ? $odf->site->code . ' · ' . $odf->site->name : 'tanpa site') . ($odf->area ? ' / ' . $odf->area->name : '') . ($odf->rack ? ' · rak ' . $odf->rack : '') . ' · ' . $odf->port_count . ' port ' . $odf->connector)

@section('page')
    <div class="panel panel-default">
        <div class="panel-heading"><strong>Port</strong> <span class="tw:text-xs ant-muted">belakang: core kabel · depan: patch ke port perangkat</span></div>
        <div class="table-responsive">
            <table class="table table-condensed tw:m-0">
                <thead><tr><th class="tw:w-16">Port</th><th>Core (belakang)</th><th>Patch ke perangkat (depan)</th><th>Catatan</th><th></th></tr></thead>
                <tbody>
                @foreach($odf->ports as $p)
                    <tr>
                        <td class="ant-data">{{ $p->port_no }}</td>
                        <td>@if($p->core)<a href="{{ route('antareja.fiber.cables.show', $p->core->cable_id) }}">{{ $p->core->cable->code }}</a> @include('antareja::fiber._core-chip', ['core' => $p->core])@else<span class="ant-muted">—</span>@endif</td>
                        <td>@if($p->device){{ $p->device->displayName() }} <span class="ant-data tw:text-xs">{{ $p->port?->getShortLabel() }}</span>@else<span class="ant-muted">—</span>@endif</td>
                        <td class="tw:text-xs">{{ $p->notes }}</td>
                        <td class="tw:text-right tw:whitespace-nowrap">
                            @if($p->core)<a href="{{ route('antareja.fiber.trace', $p->core_id) }}" class="btn btn-default btn-xs">Jalur</a>@endif
                            @if($canEdit)<button type="button" class="btn btn-default btn-xs" onclick="document.getElementById('port-edit-{{ $p->id }}').hidden = !document.getElementById('port-edit-{{ $p->id }}').hidden" aria-controls="port-edit-{{ $p->id }}">Ubah</button>@endif
                        </td>
                    </tr>
                    @if($canEdit)
                        <tr id="port-edit-{{ $p->id }}" hidden>
                            <td colspan="5" class="ant-sunken">
                                <form method="post" action="{{ route('antareja.fiber.odf-ports.update', $p) }}" class="tw:flex tw:flex-wrap tw:gap-2 tw:items-end ant-odf-port-form">
                                    @csrf @method('PUT')
                                    <select name="core_id" class="form-control input-sm tw:w-64" aria-label="Core port {{ $p->port_no }}">
                                        <option value="">— tanpa core —</option>
                                        @foreach($cores as $c)<option value="{{ $c->id }}" @selected($p->core_id === $c->id)>{{ $c->cable->code }} core {{ $c->core_no }} ({{ $c->colorLabel() }}){{ $c->status !== 'free' ? ' · ' . $c->statusMeta()['label'] : '' }}</option>@endforeach
                                    </select>
                                    <select class="form-control input-sm tw:w-56 ant-patch-device" aria-label="Perangkat">
                                        <option value="">— perangkat —</option>
                                        @foreach($devices as $d)<option value="{{ $d->device_id }}" @selected($p->device_id === $d->device_id)>{{ $d->displayName() }}</option>@endforeach
                                    </select>
                                    <select name="device_port_id" class="form-control input-sm tw:w-56 ant-patch-port" aria-label="Port perangkat" data-selected="{{ $p->port_id }}">
                                        <option value="">— port —</option>
                                        @if($p->port)<option value="{{ $p->port_id }}" selected>{{ $p->port->getShortLabel() }}</option>@endif
                                    </select>
                                    <input name="notes" class="form-control input-sm tw:flex-1 tw:min-w-40" maxlength="255" value="{{ $p->notes }}" placeholder="Catatan patch cord" aria-label="Catatan">
                                    <button class="btn btn-primary btn-sm">Simpan</button>
                                </form>
                            </td>
                        </tr>
                    @endif
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if($canEdit)
        <form method="post" action="{{ route('antareja.fiber.odfs.update', $odf) }}" class="panel panel-default tw:max-w-3xl">
            @csrf @method('PUT')
            <div class="panel-heading"><strong>Data ODF</strong></div>
            <div class="panel-body">
                <div class="row">
                    <div class="form-group col-sm-6"><label for="name">Nama</label><input id="name" name="name" class="form-control" required maxlength="128" value="{{ $odf->name }}"></div>
                    <div class="form-group col-sm-3"><label for="port_count">Jumlah port</label><input id="port_count" name="port_count" type="number" min="1" max="288" class="form-control" value="{{ $odf->port_count }}"></div>
                    <div class="form-group col-sm-3"><label for="connector">Konektor</label><select id="connector" name="connector" class="form-control">@foreach(['SC', 'LC', 'FC', 'ST'] as $k)<option @selected($odf->connector === $k)>{{ $k }}</option>@endforeach</select></div>
                </div>
                <div class="row">
                    <div class="form-group col-sm-4"><label for="site_id">Site</label><select id="site_id" name="site_id" class="form-control"><option value="">—</option>@foreach($sites as $s)<option value="{{ $s->id }}" @selected($odf->site_id === $s->id)>{{ $s->code }}</option>@endforeach</select></div>
                    <div class="form-group col-sm-4"><label for="area_id">Area</label><select id="area_id" name="area_id" class="form-control"><option value="">—</option>@foreach($areas as $a)<option value="{{ $a->id }}" @selected($odf->area_id === $a->id)>{{ $a->name }}</option>@endforeach</select></div>
                    <div class="form-group col-sm-4"><label for="rack">Rak</label><input id="rack" name="rack" class="form-control" maxlength="64" value="{{ $odf->rack }}"></div>
                </div>
                <button class="btn btn-default">Simpan</button>
            </div>
        </form>
        <script>
            // Isi pilihan port saat perangkat dipilih
            document.querySelectorAll('.ant-odf-port-form').forEach(function (f) {
                var dev = f.querySelector('.ant-patch-device'), port = f.querySelector('.ant-patch-port');
                dev.addEventListener('change', function () {
                    port.innerHTML = '<option value="">— port —</option>';
                    if (!dev.value) return;
                    fetch('{{ url('antareja/fiber/device-ports') }}/' + encodeURIComponent(dev.value), { headers: { 'Accept': 'application/json' } })
                        .then(function (r) { return r.json(); })
                        .then(function (list) { list.forEach(function (p) { var o = document.createElement('option'); o.value = p.id; o.textContent = p.label; port.appendChild(o); }); });
                });
            });
        </script>
    @endif
@endsection
