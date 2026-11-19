@extends('antareja::layouts.page')

@section('title', $ip->address_text)
@section('breadcrumb')
    <li><a href="{{ route('antareja.ipam.index') }}">IPAM</a></li>
    @if($ip->subnet)<li><a href="{{ route('antareja.ipam.subnets.show', $ip->subnet) }}">{{ $ip->subnet->cidr }}</a></li>@endif
    <li class="active">{{ $ip->address_text }}</li>
@endsection
@section('heading')
    <span class="tw:font-mono">{{ $ip->address_text }}</span>
    <span class="label {{ $ip->statusMeta()['class'] }}">{{ $ip->statusMeta()['label'] }}</span>
    @if($ip->conflict)<span class="label label-danger">Konflik</span>@endif
@endsection
@section('subheading', $ip->subnet ? $ip->subnet->cidr . ' · ' . $ip->subnet->name : 'Di luar subnet IPAM')

@section('page')
    @if($ip->conflict)
        <div class="ant-alert ant-alert--crit"><i class="fa fa-exclamation-triangle"></i> {{ $ip->conflict_detail }}</div>
    @endif
    <div class="row">
        <div class="col-lg-5">
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Data IP</strong></div>
                <table class="table table-condensed tw:m-0">
                    <tr><th class="tw:w-40">Sumber</th><td>{{ \Antareja\Models\IpAddress::SOURCES[$ip->source] ?? $ip->source }}</td></tr>
                    <tr><th>Perangkat</th><td>@if($ip->device)<a href="{{ route('antareja.inventory.show', $ip->device_id) }}">{{ $ip->device->displayName() }}</a>@if($ip->port) · {{ $ip->port->getShortLabel() }}@endif @else — @endif</td></tr>
                    <tr><th>MAC</th><td class="tw:font-mono">{{ $ip->mac_address ?? '—' }}</td></tr>
                    <tr><th>Pertama terlihat</th><td>{{ $ip->first_seen_at?->format('d M Y H:i') ?? '—' }}</td></tr>
                    <tr><th>Terakhir terlihat</th><td>{{ $ip->last_seen_at?->format('d M Y H:i') ?? 'belum pernah (alokasi manual)' }}</td></tr>
                    <tr><th>Dialokasikan oleh</th><td>{{ $ip->assigner?->username ?? '—' }}</td></tr>
                </table>
            </div>

            @can('ant-ipam.update')
            <form method="post" action="{{ route('antareja.ipam.addresses.update', $ip) }}" class="panel panel-default">
                @csrf @method('PUT')
                <div class="panel-heading"><strong>Ubah alokasi</strong></div>
                <div class="panel-body">
                    <div class="row">
                        <div class="form-group col-xs-6">
                            <label for="status">Status</label>
                            <select id="status" name="status" class="form-control">
                                @foreach(\Antareja\Models\IpAddress::STATUSES as $key => $meta)<option value="{{ $key }}" @selected(old('status', $ip->status) === $key)>{{ $meta['label'] }}</option>@endforeach
                            </select>
                        </div>
                        <div class="form-group col-xs-6">
                            <label for="hostname">Hostname</label>
                            <input id="hostname" name="hostname" class="form-control" maxlength="128" value="{{ old('hostname', $ip->hostname) }}">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="device_id">Perangkat</label>
                        <select id="device_id" name="device_id" class="form-control">
                            <option value="">—</option>
                            @foreach($devices as $device)<option value="{{ $device->device_id }}" @selected((string) old('device_id', $ip->device_id) === (string) $device->device_id)>{{ $device->displayName() }}</option>@endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="mac_address">MAC</label>
                        <input id="mac_address" name="mac_address" class="form-control tw:font-mono" value="{{ old('mac_address', $ip->mac_address) }}">
                    </div>
                    <div class="form-group">
                        <label for="description">Keterangan</label>
                        <input id="description" name="description" class="form-control" maxlength="255" value="{{ old('description', $ip->description) }}">
                    </div>
                    <p class="help-block tw:text-xs">Status Static / Reserved / DHCP tidak ditimpa sinkronisasi otomatis. Reserved yang aktif di jaringan ditandai konflik.</p>
                </div>
                <div class="panel-footer tw:flex tw:justify-between">
                    <button form="release-form" class="btn btn-link ant-text-crit tw:p-0!" onclick="return confirm('Lepas IP {{ $ip->address_text }} dari IPAM?')"><i class="fa fa-unlink"></i> Lepas IP</button>
                    <button class="btn btn-primary"><i class="fa fa-save"></i> Simpan</button>
                </div>
            </form>
            <form id="release-form" method="post" action="{{ route('antareja.ipam.addresses.release', $ip) }}">@csrf @method('DELETE')</form>
            @endcan
        </div>

        <div class="col-lg-7">
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Histori IP</strong></div>
                <ul class="list-group">
                    @forelse($ip->history as $h)
                        <li class="list-group-item tw:text-sm">
                            <strong>{{ \Antareja\Models\IpHistory::EVENTS[$h->event] ?? $h->event }}</strong> — {{ $h->description }}
                            <div class="tw:text-xs ant-muted">{{ $h->created_at->format('d M Y H:i') }} · {{ $h->user?->username ?? 'sinkronisasi otomatis' }}</div>
                        </li>
                    @empty
                        <li class="list-group-item ant-muted">Belum ada histori.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
@endsection
