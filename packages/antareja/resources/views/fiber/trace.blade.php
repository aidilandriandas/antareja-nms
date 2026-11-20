@extends('antareja::layouts.page')

@php
    $num = fn ($v, $d = 0) => number_format($v, $d, ',', '.');
    $faultHop = $locate['hop'] ?? null;
@endphp

@section('title', 'Jalur ' . $core->label())
@section('breadcrumb')
    <li><a href="{{ route('antareja.fiber.index') }}">Fiber Optic</a></li>
    <li><a href="{{ route('antareja.fiber.cables.show', $core->cable_id) }}">{{ $core->cable->code }}</a></li>
    <li class="active">Jalur core {{ $core->core_no }}</li>
@endsection
@section('heading')Jalur fiber {{ $core->label() }}@endsection
@section('subheading')
    @if($trace['complete'])<x-antareja::status status="ok" size="sm">jalur lengkap</x-antareja::status>@else<x-antareja::status status="warn" size="sm">jalur belum lengkap</x-antareja::status>@endif
    {{ $num($trace['length_m']) }} m · {{ $trace['splices'] }} splice · {{ $trace['connectors'] }} konektor
@endsection

@section('page')
    @if($trace['issue'])<x-antareja::alert tone="warn">{{ $trace['issue'] }}</x-antareja::alert>@endif
    <div class="row">
        <div class="col-lg-7">
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Jalur end-to-end</strong></div>
                <div class="panel-body">
                    <ol class="ant-fiber-path">
                        @foreach($trace['hops'] as $h)
                            @if($h['kind'] === 'odf')
                                <li><span class="ant-fiber-path__node" aria-hidden="true"></span>
                                    <strong>{{ $h['port']->label() }}</strong> <span class="tw:text-xs ant-muted">{{ $h['port']->odf?->site?->code }} · {{ $num($h['at']) }} m</span>
                                    <div class="tw:text-sm">@if($h['port']->device)patch ke <a href="{{ route('antareja.inventory.show', $h['port']->device_id) }}">{{ $h['port']->device->displayName() }}</a> <span class="ant-data">{{ $h['port']->port?->getShortLabel() }}</span>@if($h['port']->port) <span class="tw:text-xs">({{ $h['port']->port->ifOperStatus }})</span>@endif @else<span class="ant-muted">tidak di-patch ke perangkat</span>@endif</div>
                                </li>
                            @elseif($h['kind'] === 'cable')
                                @php($isFault = $faultHop && $faultHop['cable']->id === $h['cable']->id && $faultHop['core']->id === $h['core']->id)
                                <li><span class="ant-fiber-path__node ant-fiber-path__node--cable {{ $isFault ? 'ant-fiber-path__node--fault' : '' }}" aria-hidden="true"></span>
                                    <a href="{{ route('antareja.fiber.cables.show', $h['cable']) }}" class="ant-data">{{ $h['cable']->code }}</a> @include('antareja::fiber._core-chip', ['core' => $h['core']])
                                    <span class="tw:text-xs ant-muted">{{ $h['core']->colorLabel() }} · {{ $h['length_m'] ? $num($h['length_m']) . ' m' : 'panjang belum dicatat' }}</span>
                                    @if($h['core']->status === 'broken')<x-antareja::status status="crit" size="sm">putus</x-antareja::status>@endif
                                    @if($isFault)<div class="tw:text-sm ant-text-crit tw:font-semibold">⚠ Perkiraan gangguan di segmen ini</div>@endif
                                </li>
                            @elseif($h['kind'] === 'joint')
                                <li><span class="ant-fiber-path__node ant-fiber-path__node--joint" aria-hidden="true"></span>
                                    <a href="{{ route('antareja.fiber.joints.show', $h['joint']) }}">{{ \Antareja\Models\FiberJoint::TYPES[$h['joint']->type] }} {{ $h['joint']->code }}</a>
                                    <span class="tw:text-xs ant-muted">{{ $h['joint']->location }} · {{ $num($h['at']) }} m · splice {{ $h['splice']->loss_db !== null ? $num($h['splice']->loss_db, 2) . ' dB' : '' }}</span>
                                </li>
                            @else
                                <li><span class="ant-fiber-path__node" aria-hidden="true"></span>{{ $h['label'] }} <span class="tw:text-xs ant-muted">{{ $num($h['at']) }} m</span></li>
                            @endif
                        @endforeach
                    </ol>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <form method="get" class="panel panel-default">
                <div class="panel-heading"><strong>Lokasi gangguan dari OTDR</strong></div>
                <div class="panel-body">
                    <div class="row">
                        <div class="form-group col-xs-7"><label for="distance">Jarak event OTDR (m)</label><input id="distance" name="distance" type="number" step="1" min="0" class="form-control" required value="{{ request('distance') }}"></div>
                        <div class="form-group col-xs-5"><label for="slack">Slack serat (%)</label><input id="slack" name="slack" type="number" step="0.1" min="0" max="20" class="form-control" value="{{ request('slack', 1.5) }}"></div>
                    </div>
                    <p class="help-block tw:text-xs">Diukur dari titik awal jalur (paling atas). Panjang serat biasanya 1–3% lebih panjang dari kabel karena helix & gulungan cadangan.</p>
                    <button class="btn btn-primary"><i class="fa fa-crosshairs"></i> Cari lokasi</button>
                    @if($locate)
                        <div class="tw:mt-3 ant-alert {{ $locate['found'] ? 'ant-alert--warn' : 'ant-alert--info' }}" role="status">{{ $locate['message'] }}</div>
                    @endif
                </div>
            </form>
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Estimasi loss budget</strong></div>
                <table class="table table-condensed tw:m-0">
                    @foreach($budget as $wl => $db)<tr><th>{{ $wl }} nm</th><td class="ant-data tw:text-right">{{ $num($db, 2) }} dB</td></tr>@endforeach
                    <tr><td colspan="2" class="tw:text-xs ant-muted">Atenuasi tipikal per km + splice (terukur atau 0,1 dB) + konektor 0,5 dB. Bandingkan dengan hasil uji core; selisih besar menandakan bending, konektor kotor, atau splice buruk.</td></tr>
                    @if($core->loss_db !== null)<tr><th>Terukur terakhir</th><td class="ant-data tw:text-right">{{ $num($core->loss_db, 2) }} dB</td></tr>@endif
                </table>
            </div>
        </div>
    </div>
@endsection
