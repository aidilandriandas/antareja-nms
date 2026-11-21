@extends('antareja::layouts.page')

@section('title', 'Konflik IP')
@section('breadcrumb')
    <li><a href="{{ route('antareja.ipam.index') }}">IPAM</a></li>
    <li class="active">Konflik & IP di luar subnet</li>
@endsection
@section('heading', 'Konflik IP')
@section('subheading', 'Konflik = MAC untuk satu IP berganti-ganti antara dua perangkat, router melihat dua MAC untuk satu IP di interface yang sama, IP terpasang di interface beberapa perangkat, IP reserved tetapi aktif, atau MAC berbeda dari alokasi static.')
@section('actions')
    @can('ant-ipam.update')
        <form method="post" action="{{ route('antareja.ipam.conflicts.refresh') }}">@csrf
            <button class="btn btn-primary btn-sm" @disabled($arpRunning)><i class="fa-solid fa-arrows-rotate"></i> {{ $arpRunning ? 'Sedang membaca ARP…' : 'Perbarui data ARP & cek ulang' }}</button>
        </form>
    @endcan
@endsection

@section('page')
    <p class="ant-muted tw:text-sm">
        Tabel ARP router/L3 dibaca ulang otomatis tiap 30 menit.
        @if($arpLast) Terakhir: {{ \Illuminate\Support\Carbon::parse($arpLast['at'])->locale('id')->diffForHumans() }} dari {{ $arpLast['devices'] }} perangkat. @endif
        MAC berbeda di tabel ARP <em>perangkat yang berbeda</em> tidak dihitung konflik (biasanya cache ARP lama / IP DHCP berpindah).
    </p>
    @if($overlaps)
        <div class="panel panel-default">
            <div class="panel-heading"><strong><i class="fa-solid fa-triangle-exclamation ant-text-warn"></i> Subnet terlihat di beberapa interface router ({{ count($overlaps) }})</strong></div>
            <div class="panel-body tw:text-sm tw:pb-2">
                Router mencari host dari subnet yang sama di lebih dari satu interface/VLAN — biasanya karena alamat IP dari subnet yang sama
                terpasang di dua interface. Akibatnya tabel ARP berisi entri <code>failed</code>/<code>stale</code> dan IP yang sama tercatat di dua VLAN.
                Periksa di router (MikroTik: <code>/ip address print</code>) dan sisakan subnet itu di satu interface saja.
            </div>
            <table class="table table-condensed tw:m-0">
                <thead><tr><th>Router</th><th>Subnet</th><th>Interface (jumlah entri ARP)</th></tr></thead>
                <tbody>
                @foreach($overlaps as $o)
                    <tr>
                        <td><a href="{{ url('device/' . $o['device_id']) }}">{{ $o['device'] }}</a></td>
                        <td class="ant-data">{{ $o['subnet'] }}@if($o['guessed']) <small class="ant-muted">(perkiraan /24 — subnet belum ada di IPAM)</small>@endif</td>
                        <td>@foreach($o['interfaces'] as $if)<span class="ant-badge ant-badge--sm ant-badge--unknown tw:mr-1">{{ $if['name'] }} · {{ $if['entries'] }}</span>@endforeach</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

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
