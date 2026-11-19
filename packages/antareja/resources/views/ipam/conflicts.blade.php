@extends('antareja::layouts.page')

@section('title', 'Konflik IP')
@section('breadcrumb')
    <li><a href="{{ route('antareja.ipam.index') }}">IPAM</a></li>
    <li class="active">Konflik & IP di luar subnet</li>
@endsection
@section('heading', 'Konflik IP')
@section('subheading', 'Duplikat IP terdeteksi dari data poller: satu IP dengan beberapa MAC, IP terpasang di beberapa perangkat, reserved tetapi aktif, atau MAC berbeda dari alokasi static.')

@section('page')
    <div class="panel panel-default">
        <div class="panel-heading"><strong>IP konflik ({{ $conflicts->count() }})</strong></div>
        <div class="table-responsive">
            <table class="table table-condensed table-hover tw:m-0">
                <thead><tr><th>IP</th><th>Subnet</th><th>Status</th><th>Detail</th><th>Perangkat</th><th>Terakhir terlihat</th></tr></thead>
                <tbody>
                @forelse($conflicts as $ip)
                    <tr>
                        <td><a href="{{ route('antareja.ipam.addresses.edit', $ip) }}" class="tw:font-mono">{{ $ip->address_text }}</a></td>
                        <td>{{ $ip->subnet?->cidr ?? '—' }}</td>
                        <td><span class="label {{ $ip->statusMeta()['class'] }}">{{ $ip->statusMeta()['label'] }}</span></td>
                        <td class="tw:text-sm">{{ $ip->conflict_detail }}</td>
                        <td>{{ $ip->device?->displayName() ?? '—' }}</td>
                        <td class="tw:text-xs">{{ $ip->last_seen_at?->diffForHumans() ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="tw:text-center tw:py-4 ant-muted">Tidak ada konflik IP.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="panel panel-default" id="orphans">
        <div class="panel-heading"><strong>IP perangkat di luar subnet IPAM ({{ $orphans->count() }})</strong>
            <a href="{{ route('antareja.ipam.import') }}" class="tw:text-xs tw:ml-2">Impor subnet dari perangkat &rarr;</a></div>
        <div class="table-responsive">
            <table class="table table-condensed table-hover tw:m-0">
                <thead><tr><th>IP</th><th>Sumber</th><th>Perangkat</th><th>MAC</th></tr></thead>
                <tbody>
                @forelse($orphans as $ip)
                    <tr>
                        <td><a href="{{ route('antareja.ipam.addresses.edit', $ip) }}" class="tw:font-mono">{{ $ip->address_text }}</a></td>
                        <td class="tw:text-xs">{{ \Antareja\Models\IpAddress::SOURCES[$ip->source] ?? $ip->source }}</td>
                        <td>{{ $ip->device?->displayName() ?? '—' }}</td>
                        <td class="tw:font-mono tw:text-xs">{{ $ip->mac_address ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="tw:text-center tw:py-4 ant-muted">Semua IP perangkat berada di subnet IPAM.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
