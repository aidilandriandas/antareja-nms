@extends('antareja::layouts.page')

@php
    $cellClass = [
        'free' => 'tw:bg-gray-200 tw:dark:bg-dark-gray-200',
        'network' => 'tw:bg-gray-400 tw:dark:bg-gray-600',
        'gateway' => 'tw:bg-violet-600',
        'dhcp-pool' => 'tw:bg-cyan-200 tw:dark:bg-cyan-900',
        'used' => 'tw:bg-blue-600',
        'static' => 'tw:bg-emerald-600',
        'reserved' => 'tw:bg-amber-500',
        'dhcp' => 'tw:bg-cyan-600',
        'conflict' => 'tw:bg-red-600',
    ];
    $legend = ['free' => 'Kosong', 'used' => 'Used', 'static' => 'Static', 'reserved' => 'Reserved', 'dhcp' => 'DHCP', 'dhcp-pool' => 'Pool DHCP', 'gateway' => 'Gateway', 'network' => 'Network/Broadcast', 'conflict' => 'Konflik'];
@endphp

@section('title', $subnet->cidr)
@section('breadcrumb')
    <li><a href="{{ route('antareja.ipam.index') }}">IPAM</a></li>
    <li class="active">{{ $subnet->cidr }}</li>
@endsection
@section('heading')
    <span class="tw:font-mono">{{ $subnet->cidr }}</span> — {{ $subnet->name }}
@endsection
@section('subheading', implode(' · ', array_filter([$subnet->purposeLabel(), $subnet->site?->code, $subnet->vlan ? 'VLAN ' . $subnet->vlan->vlan_number : null, $subnet->gatewayText() ? 'GW ' . $subnet->gatewayText() : null])))

@section('actions')
    @can('ant-ipam.update')<a href="{{ route('antareja.ipam.subnets.edit', $subnet) }}" class="btn btn-default"><i class="fa fa-pencil"></i> Edit subnet</a>@endcan
@endsection

@section('page')
    <div class="tw:grid tw:grid-cols-2 tw:md:grid-cols-4 tw:xl:grid-cols-7 tw:gap-3 tw:mb-4">
        @include('antareja::partials.stat', ['label' => 'Usable', 'value' => $subnet->family === 4 ? number_format($stats['usable']) : 'IPv6'])
        @include('antareja::partials.stat', ['label' => 'Used', 'value' => $stats['used'], 'color' => 'tw:text-blue-600 tw:dark:text-blue-400'])
        @include('antareja::partials.stat', ['label' => 'Static', 'value' => $stats['static'], 'color' => 'tw:text-emerald-600 tw:dark:text-emerald-400'])
        @include('antareja::partials.stat', ['label' => 'Reserved', 'value' => $stats['reserved'], 'color' => 'tw:text-amber-600 tw:dark:text-amber-400'])
        @include('antareja::partials.stat', ['label' => 'Pool DHCP', 'value' => $stats['dhcp_pool'], 'hint' => $subnet->dhcpStartText() ? $subnet->dhcpStartText() . ' – ' . $subnet->dhcpEndText() : null])
        @include('antareja::partials.stat', ['label' => 'Available', 'value' => $subnet->family === 4 ? number_format($stats['available']) : '—', 'color' => 'tw:text-emerald-600 tw:dark:text-emerald-400'])
        @include('antareja::partials.stat', ['label' => 'Utilisasi', 'value' => $stats['utilization'] . '%', 'color' => $stats['utilization'] >= 90 ? 'tw:text-red-600' : ($stats['utilization'] >= 80 ? 'tw:text-amber-600' : null)])
    </div>

    <div class="row">
        <div class="col-lg-8">
            @if($map)
                <div class="panel panel-default">
                    <div class="panel-heading"><strong>Peta alamat</strong></div>
                    <div class="panel-body">
                        <div class="tw:grid tw:gap-[3px]" style="grid-template-columns: repeat(32, minmax(0, 1fr));">
                            @foreach($map as $cell)
                                @php($r = $cell['record'])
                                @php($title = $cell['ip'] . ' — ' . ($legend[$cell['state']] ?? $cell['state']) . ($r?->hostname ? ' · ' . $r->hostname : '') . ($r?->device ? ' · ' . $r->device->displayName() : '') . ($r?->conflict_detail ? ' · ' . $r->conflict_detail : ''))
                                @if($r)
                                    <a href="{{ route('antareja.ipam.addresses.edit', $r) }}" title="{{ $title }}" class="tw:block tw:aspect-square tw:rounded-sm {{ $cellClass[$cell['state']] ?? '' }}"></a>
                                @else
                                    <span title="{{ $title }}" class="tw:block tw:aspect-square tw:rounded-sm {{ $cellClass[$cell['state']] ?? '' }}"></span>
                                @endif
                            @endforeach
                        </div>
                        <div class="tw:flex tw:flex-wrap tw:gap-3 tw:mt-3 tw:text-xs">
                            @foreach($legend as $key => $label)
                                <span class="tw:inline-flex tw:items-center tw:gap-1"><span class="tw:inline-block tw:w-3 tw:h-3 tw:rounded-sm {{ $cellClass[$key] }}"></span>{{ $label }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            <div class="panel panel-default">
                <div class="panel-heading tw:flex tw:flex-wrap tw:justify-between tw:items-center tw:gap-2">
                    <strong>IP tercatat ({{ $addresses->total() }})</strong>
                    <span class="tw:flex tw:gap-1 tw:text-xs">
                        @foreach(['' => 'Semua', 'used' => 'Used', 'static' => 'Static', 'reserved' => 'Reserved', 'dhcp' => 'DHCP', 'conflict' => 'Konflik'] as $key => $label)
                            <a href="{{ route('antareja.ipam.subnets.show', [$subnet, 'status' => $key ?: null]) }}" class="label {{ request('status', '') === $key ? 'label-primary' : 'label-default' }}">{{ $label }}</a>
                        @endforeach
                    </span>
                </div>
                <div class="table-responsive">
                    <table class="table table-condensed table-hover tw:m-0">
                        <thead><tr><th>IP</th><th>Status</th><th>Hostname / keterangan</th><th>Perangkat</th><th>MAC</th><th>Sumber</th><th>Terakhir terlihat</th></tr></thead>
                        <tbody>
                        @forelse($addresses as $ip)
                            <tr class="{{ $ip->conflict ? 'danger' : '' }}">
                                <td><a href="{{ route('antareja.ipam.addresses.edit', $ip) }}" class="tw:font-mono">{{ $ip->address_text }}</a></td>
                                <td><span class="label {{ $ip->statusMeta()['class'] }}">{{ $ip->statusMeta()['label'] }}</span>
                                    @if($ip->conflict)<span class="label label-danger" title="{{ $ip->conflict_detail }}">Konflik</span>@endif</td>
                                <td>{{ $ip->hostname }} <span class="tw:text-xs tw:text-gray-500">{{ $ip->description }}</span></td>
                                <td>@if($ip->device)<a href="{{ route('antareja.inventory.show', $ip->device_id) }}">{{ $ip->device->displayName() }}</a>@if($ip->port) <span class="tw:text-xs tw:text-gray-500">{{ $ip->port->getShortLabel() }}</span>@endif @else — @endif</td>
                                <td class="tw:font-mono tw:text-xs">{{ $ip->mac_address ?? '—' }}</td>
                                <td class="tw:text-xs">{{ \Antareja\Models\IpAddress::SOURCES[$ip->source] ?? $ip->source }}</td>
                                <td class="tw:text-xs">{{ $ip->last_seen_at?->diffForHumans() ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="tw:text-center tw:py-4 tw:text-gray-500">Belum ada IP tercatat di subnet ini.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            {{ $addresses->links() }}
        </div>

        <div class="col-lg-4">
            @can('ant-ipam.update')
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Alokasi IP</strong></div>
                <form method="post" action="{{ route('antareja.ipam.subnets.allocate', $subnet) }}" class="panel-body">
                    @csrf
                    <div class="form-group">
                        <label for="address">Alamat</label>
                        <input id="address" name="address" class="form-control tw:font-mono" value="{{ old('address') }}" placeholder="kosongkan = IP kosong berikutnya ({{ $free[0] ?? 'penuh' }})">
                    </div>
                    <div class="row">
                        <div class="form-group col-xs-6">
                            <label for="status">Status</label>
                            <select id="status" name="status" class="form-control">
                                @foreach(['static' => 'Static', 'reserved' => 'Reserved', 'dhcp' => 'DHCP', 'used' => 'Used'] as $key => $label)<option value="{{ $key }}" @selected(old('status') === $key)>{{ $label }}</option>@endforeach
                            </select>
                        </div>
                        <div class="form-group col-xs-6">
                            <label for="hostname">Hostname</label>
                            <input id="hostname" name="hostname" class="form-control" maxlength="128" value="{{ old('hostname') }}">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="device_id">Perangkat</label>
                        <select id="device_id" name="device_id" class="form-control">
                            <option value="">—</option>
                            @foreach($devices as $device)<option value="{{ $device->device_id }}" @selected((string) old('device_id') === (string) $device->device_id)>{{ $device->displayName() }}</option>@endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="mac_address">MAC</label>
                        <input id="mac_address" name="mac_address" class="form-control tw:font-mono" value="{{ old('mac_address') }}">
                    </div>
                    <div class="form-group">
                        <label for="description">Keterangan</label>
                        <input id="description" name="description" class="form-control" maxlength="255" value="{{ old('description') }}" placeholder="mis. Kamera pintu gerbang Pit A">
                    </div>
                    <button class="btn btn-primary btn-block"><i class="fa fa-plus"></i> Alokasikan</button>
                </form>
            </div>
            @endcan

            @if($free)
            <div class="panel panel-default">
                <div class="panel-heading"><strong>IP kosong</strong> <span class="tw:text-xs tw:text-gray-500">(20 pertama)</span></div>
                <div class="panel-body tw:flex tw:flex-wrap tw:gap-1 tw:font-mono tw:text-xs">
                    @foreach($free as $f)<span class="label label-default">{{ $f }}</span>@endforeach
                </div>
            </div>
            @endif

            @if($subnet->description)
                <div class="panel panel-default"><div class="panel-body tw:text-sm tw:whitespace-pre-line">{{ $subnet->description }}</div></div>
            @endif
        </div>
    </div>
@endsection
