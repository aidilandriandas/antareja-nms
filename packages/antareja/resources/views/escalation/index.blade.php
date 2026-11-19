@extends('antareja::layouts.page')

@section('title', 'Eskalasi & Notifikasi')
@section('breadcrumb')<li class="active">Eskalasi &amp; Notifikasi</li>@endsection
@section('heading', 'Eskalasi & Notifikasi')
@section('subheading', 'Siapa diberi tahu, lewat apa, dan kapan — per severity incident. Notifikasi dikirim per incident, bukan per event.')

@section('actions')
    @can('ant-escalation.update')<a href="{{ route('antareja.escalation.create') }}" class="btn btn-primary"><i class="fa fa-plus"></i> Tambah kebijakan</a>@endcan
@endsection

@section('page')
    @if($transports->isEmpty())
        <x-antareja::alert tone="warn" title="Belum ada kanal notifikasi">
            Buat kanal (Telegram bot, email, Mattermost, …) di <a href="{{ url('alert-transports') }}">Alerts → Alert Transports</a>, lalu pilih kanal itu di setiap tahap eskalasi.
        </x-antareja::alert>
    @endif

    <x-antareja::alert tone="info">
        Alur: problem aktif yang tidak ditekan dan belum di-acknowledge → incident otomatis setelah N menit → tahap eskalasi dikirim sesuai jadwal sampai incident di-acknowledge.
        Untuk menghindari pesan ganda, matikan notifikasi bawaan di alert rule poller bila kanal yang sama sudah dipakai di sini.
    </x-antareja::alert>

    <div class="table-responsive">
        <table class="table table-condensed table-hover">
            <thead><tr><th>Kebijakan</th><th>Berlaku untuk</th><th>Incident otomatis</th><th>Tahap eskalasi</th><th>Pemulihan</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($policies as $p)
                <tr>
                    <td><strong>{{ $p->name }}</strong></td>
                    <td class="tw:text-sm">severity ≥ {{ \Antareja\Support\Severity::incident($p->min_severity)['label'] }}<br><span class="tw:text-xs ant-muted">{{ $p->site ? 'site ' . $p->site->code : 'semua site' }}</span></td>
                    <td class="tw:text-sm">{{ $p->auto_create_incident ? 'setelah ' . $p->auto_create_after_minutes . ' menit' : 'tidak' }}</td>
                    <td class="tw:text-xs">
                        @foreach($p->steps ?? [] as $n => $step)
                            <div>Tahap {{ $n + 1 }} · menit ke-{{ $step['after_minutes'] }} → {{ collect($step['transport_ids'])->map(fn ($id) => $transports[$id]->transport_name ?? "#{$id}")->implode(', ') ?: '—' }}</div>
                        @endforeach
                    </td>
                    <td class="tw:text-sm">{{ $p->notify_resolved ? 'ya' : 'tidak' }}</td>
                    <td><x-antareja::status :status="$p->active ? 'ok' : 'unknown'" size="sm">{{ $p->active ? 'Aktif' : 'Nonaktif' }}</x-antareja::status></td>
                    <td class="tw:whitespace-nowrap">
                        @can('ant-escalation.update')
                            <form method="post" action="{{ route('antareja.escalation.test', $p) }}" class="tw:inline">@csrf<button class="btn btn-xs btn-default">Uji kirim</button></form>
                            <a href="{{ route('antareja.escalation.edit', $p) }}" class="btn btn-xs btn-default">Edit</a>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="tw:text-center tw:py-6 ant-muted">Belum ada kebijakan eskalasi. Tanpa kebijakan, incident tidak dibuat otomatis dan tidak ada notifikasi.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
