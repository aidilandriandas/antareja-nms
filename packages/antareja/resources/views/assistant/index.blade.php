@extends('antareja::layouts.page')

@section('title', 'Asisten')
@section('breadcrumb')<li class="active">Asisten &amp; Anomali</li>@endsection
@section('heading', 'Asisten AI & Anomali')
@section('subheading', 'Draf laporan serah terima shift dan analisis incident oleh AI (opsional, butuh internet), serta deteksi anomali statistik yang berjalan offline.')

@section('page')
    <div class="row">
        <div class="col-lg-6">
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Status asisten AI</strong></div>
                <div class="panel-body">
                    @if($enabled)
                        <x-antareja::status status="ok">Aktif</x-antareja::status> <span class="tw:text-sm">model <span class="ant-data">{{ $model }}</span></span>
                    @else
                        <x-antareja::status status="unknown">Nonaktif</x-antareja::status>
                        <p class="tw:text-sm tw:mt-2 tw:mb-0">{{ $reason }}</p>
                    @endif
                    <ul class="tw:text-xs ant-muted tw:mt-3 tw:mb-0 tw:pl-4">
                        <li>AI hanya menulis draf teks. Tidak ada akses ke perangkat dan tidak pernah mengubah konfigurasi; root cause tidak diisi otomatis.</li>
                        <li>Yang dikirim: ringkasan problem, incident, work order, change, maintenance, anomali. Tidak pernah: isi konfigurasi, kredensial, email. Alamat IP &amp; MAC disamarkan.</li>
                        <li>Tekan "Lihat data" untuk memeriksa persis apa yang akan dikirim — tanpa mengirim apa pun.</li>
                    </ul>
                </div>
            </div>
            <form method="post" action="{{ route('antareja.assistant.shift') }}" class="panel panel-default">
                @csrf
                <div class="panel-heading"><strong>Laporan serah terima shift</strong></div>
                <div class="panel-body tw:flex tw:flex-wrap tw:items-end tw:gap-2">
                    <div><label for="hours">Periode</label>
                        <select id="hours" name="hours" class="form-control">@foreach([8 => '8 jam', 12 => '12 jam (1 shift)', 24 => '24 jam'] as $h => $l)<option value="{{ $h }}" @selected($h === 12)>{{ $l }}</option>@endforeach</select></div>
                    <button name="preview" value="1" class="btn btn-default">Lihat data</button>
                    <button class="btn btn-primary" @disabled(! $enabled)><i class="fa fa-magic"></i> Buat draf</button>
                </div>
            </form>
        </div>
        <div class="col-lg-6">
            <a href="{{ route('antareja.anomalies.index') }}" class="tw:block tw:no-underline! tw:mb-5">
                <x-antareja::stat label="Anomali aktif" :value="$anomalies" :tone="$anomalies ? 'warn' : 'ok'" hint="latency, loss, traffic, flapping — dibanding jam yang sama hari-hari sebelumnya" />
            </a>
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Draf terbaru</strong></div>
                <div class="list-group">
                    @forelse($reports as $r)
                        <a href="{{ route('antareja.assistant.show', $r) }}" class="list-group-item tw:text-sm">{{ $r->title }} <span class="tw:text-xs ant-muted">· {{ $r->created_at->format('d M H:i') }} · {{ $r->creator?->username }}</span></a>
                    @empty
                        <div class="list-group-item ant-muted tw:text-sm">Belum ada.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
