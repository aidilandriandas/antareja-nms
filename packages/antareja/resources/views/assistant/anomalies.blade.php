@extends('antareja::layouts.page')

@php($A = \Antareja\Models\Anomaly::class)
@php($fmt = fn ($v, $u) => $v === null ? '—' : number_format($v, $v >= 10 ? 0 : 1, ',', '.') . ' ' . $u)

@section('title', 'Anomali')
@section('breadcrumb')
    <li><a href="{{ route('antareja.assistant.index') }}">Asisten &amp; Anomali</a></li>
    <li class="active">Anomali</li>
@endsection
@section('heading', 'Deteksi anomali')
@section('subheading', 'Nilai 30 menit terakhir dibanding jam yang sama (±1 jam) dari hari-hari sebelumnya dengan median & MAD. Berjalan tiap 15 menit, offline, tanpa AI.')

@section('page')
    <div class="panel panel-default">
        <div class="panel-heading"><strong>Aktif ({{ $open->count() }})</strong></div>
        <div class="table-responsive">
            <table class="table table-condensed table-hover tw:m-0">
                <thead><tr><th>Jenis</th><th>Keterangan</th><th class="tw:text-right">Nilai</th><th class="tw:text-right">Normal</th><th class="tw:text-right">Skor</th><th>Sejak</th></tr></thead>
                <tbody>
                @forelse($open as $a)
                    <tr>
                        <td><x-antareja::status :status="$a->kind === 'flapping' ? 'crit' : 'warn'" size="sm">{{ $A::KINDS[$a->kind] ?? $a->kind }}</x-antareja::status></td>
                        <td>@if($a->url())<a href="{{ $a->url() }}">{{ $a->label }}</a>@else{{ $a->label }}@endif</td>
                        <td class="tw:text-right ant-data">{{ $fmt($a->value, $a->unit) }}</td>
                        <td class="tw:text-right ant-data">{{ $fmt($a->baseline, $a->unit) }}</td>
                        <td class="tw:text-right ant-data">{{ $a->score !== null ? number_format($a->score, 1, ',', '.') : '—' }}</td>
                        <td class="tw:text-xs">{{ $a->detected_at->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="ant-muted tw:text-center tw:py-4">Tidak ada anomali aktif.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="panel panel-default">
        <div class="panel-heading"><strong>Selesai 7 hari terakhir</strong></div>
        <ul class="list-group tw:text-sm">
            @forelse($closed as $a)
                <li class="list-group-item">{{ $A::KINDS[$a->kind] ?? $a->kind }} · {{ $a->label }} <span class="tw:text-xs ant-muted">{{ $a->detected_at->format('d M H:i') }} – {{ $a->closed_at->format('d M H:i') }}</span></li>
            @empty
                <li class="list-group-item ant-muted">Belum ada.</li>
            @endforelse
        </ul>
    </div>
    <p class="tw:text-xs ant-muted">Butuh minimal {{ \Antareja\Services\AnomalyDetector::MIN_BASELINE }} sampel baseline di jam yang sama; target baru mulai dinilai setelah beberapa hari data terkumpul.</p>
@endsection
