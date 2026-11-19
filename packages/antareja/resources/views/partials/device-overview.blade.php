{{-- Panel ANTAREJA di halaman overview device LibreNMS (DeviceOverviewHook) --}}
<div class="row">
    <div class="col-md-12">
        <div class="panel panel-default panel-condensed">
            <div class="panel-heading tw:flex tw:items-center tw:justify-between">
                <span><i class="fa fa-th-large fa-fw"></i> <strong>ANTAREJA Inventaris</strong>
                    @include('antareja::partials.verification-badge', ['status' => $status])</span>
                <span class="tw:flex tw:items-center tw:gap-3">
                    @include('antareja::partials.completeness', ['completeness' => $completeness])
                    <a href="{{ route('antareja.inventory.show', $device->device_id) }}" class="btn btn-xs btn-default">Detail</a>
                    @can('ant-inventory.update')
                        <a href="{{ route('antareja.inventory.edit', $device->device_id) }}" class="btn btn-xs btn-primary">Edit</a>
                    @endcan
                </span>
            </div>
            <table class="table table-condensed table-hover tw:m-0">
                <tr>
                    <th class="tw:w-32">Kategori</th><td>{{ $detail->categoryLabel() }}</td>
                    <th class="tw:w-32">Site / Area</th><td>{{ $detail->site?->code ?? '—' }}{{ $detail->area ? ' / ' . $detail->area->name : '' }}</td>
                </tr>
                <tr>
                    <th>Lokasi</th><td>{{ trim(($detail->installation_location ?? '') . ($detail->rack ? " · Rak {$detail->rack}" : '')) ?: '—' }}</td>
                    <th>PIC</th><td>{{ $detail->picLabel() ?? '—' }}</td>
                </tr>
                <tr>
                    <th>Serial</th><td>{{ $detail->effectiveSerial() ?? '—' }}</td>
                    <th>Mapping</th><td>
                        @forelse($links->take(3) as $link)
                            @php($other = $link->a_device_id === $device->device_id ? $link->bDevice : $link->aDevice)
                            @php($port = $link->a_device_id === $device->device_id ? $link->bPort : $link->aPort)
                            <div class="tw:text-xs">
                                {{ $link->a_device_id === $device->device_id ? '→' : '←' }}
                                <a href="{{ url('device/' . $other?->device_id) }}">{{ $other?->displayName() }}</a>{{ $port ? ' ' . $port->getShortLabel() : '' }}
                                @if($link->status === 'suggested')<span class="label label-info">saran</span>@endif
                            </div>
                        @empty — @endforelse
                    </td>
                </tr>
            </table>
            @if($completeness['missing'])
                <div class="panel-body tw:py-2! tw:text-xs ant-muted">Belum diisi: {{ implode(', ', $completeness['missing']) }}</div>
            @endif
        </div>
    </div>
</div>
