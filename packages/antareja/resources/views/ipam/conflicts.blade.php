@extends('antareja::layouts.page')

@section('title', 'Konflik IP')
@section('breadcrumb')
    <li><a href="{{ route('antareja.ipam.index') }}">IPAM</a></li>
    <li class="active">Konflik & IP di luar subnet</li>
@endsection
@section('heading', 'Konflik IP')
@section('subheading', 'Konflik = satu router/L3 melihat IP yang sama dengan beberapa MAC di tabel ARP-nya, IP terpasang di interface beberapa perangkat, IP reserved tetapi aktif, atau MAC berbeda dari alokasi static.')
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
