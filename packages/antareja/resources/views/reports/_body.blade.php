{{-- Isi laporan bulanan. Dipakai halaman aplikasi & versi cetak (PDF). Markup sederhana agar rapi saat dicetak. --}}
@php
    $fmt = fn ($s) => \Antareja\Services\SlaCalculator::fmtDuration($s);
    $pct = fn ($p, $d = 3) => \Antareja\Services\SlaCalculator::fmtPct($p, $d);
    $num = fn ($v, $d = 1) => $v === null ? '—' : number_format($v, $d, ',', '.');
    $S = \Antareja\Support\Severity::class;
    $s = $summary;
    $deviceLimit = $deviceLimit ?? 25;
@endphp

<section class="ant-report-section">
    <h2>1. Ringkasan</h2>
    <table class="table table-condensed ant-report-kv">
        <tr><th>Periode</th><td>{{ $s['period_from']->format('d M Y') }} – {{ $s['period_to']->format('d M Y H:i') }}{{ $month->isSameMonth(now()) ? ' (bulan berjalan)' : '' }}</td></tr>
        <tr><th>Perangkat aktif</th><td>{{ $s['devices'] }} ({{ $s['devices_with_outage'] }} pernah down, {{ $s['devices_down_now'] }} down saat laporan dibuat)</td></tr>
        <tr><th>Availability rata-rata perangkat</th><td><strong>{{ $pct($s['device_availability']) }}</strong> · total downtime {{ $fmt($s['downtime']) }}</td></tr>
        <tr><th>Layanan memenuhi SLA</th><td>{{ $s['services'] ? $s['services_met'] . ' dari ' . $s['services'] : 'belum ada layanan SLA' }}</td></tr>
        <tr><th>Incident</th><td>{{ $s['incidents'] }}
            @if($s['incidents'])({{ collect($s['incidents_by_severity'])->map(fn ($n, $k) => $n . ' ' . strtolower($S::incident($k)['label']))->join(', ') }})@endif
            · MTTA {{ $fmt($s['mtta']) }} · MTTR {{ $fmt($s['mttr']) }}</td></tr>
        <tr><th>Problem terdeteksi</th><td>{{ $s['problems'] }}</td></tr>
        <tr><th>Maintenance</th><td>{{ $s['maintenance'] }} kegiatan, {{ $num($s['maintenance_hours']) }} jam</td></tr>
    </table>
</section>

<section class="ant-report-section">
    <h2>2. SLA layanan</h2>
    @if(count($services))
        <table class="table table-condensed">
            <thead><tr><th>Layanan</th><th>Aturan</th><th class="text-right">Target</th><th class="text-right">Availability</th><th>Hasil</th><th class="text-right">Downtime</th><th class="text-right">Gangguan</th><th class="text-right">MTTR</th></tr></thead>
            <tbody>
            @foreach($services as $x)
                <tr>
                    <td>{{ $x['parent'] ? $x['parent'] . ' › ' : '' }}{{ $x['name'] }}</td><td>{{ $x['calc'] }}</td>
                    <td class="text-right">{{ $pct($x['target'], 2) }}</td><td class="text-right"><strong>{{ $pct($x['availability']) }}</strong></td>
                    <td>{{ $x['met'] === null ? '—' : ($x['met'] ? '✓ Tercapai' : '✗ Di bawah target') }}</td>
                    <td class="text-right">{{ $fmt($x['downtime']) }}</td><td class="text-right">{{ $x['outages'] }}</td><td class="text-right">{{ $fmt($x['mttr']) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @else
        <p class="ant-report-empty">Belum ada layanan SLA yang didefinisikan.</p>
    @endif
</section>

<section class="ant-report-section">
    <h2>3. Availability per site</h2>
    <table class="table table-condensed">
        <thead><tr><th>Site</th><th class="text-right">Perangkat</th><th class="text-right">Availability</th><th class="text-right">Downtime</th><th class="text-right">Gangguan</th><th class="text-right">Incident</th></tr></thead>
        <tbody>
        @forelse($sites as $x)
            <tr><td><strong>{{ $x['code'] }}</strong> {{ $x['name'] }}</td><td class="text-right">{{ $x['devices'] }}</td><td class="text-right">{{ $pct($x['availability']) }}</td><td class="text-right">{{ $fmt($x['downtime']) }}</td><td class="text-right">{{ $x['outages'] }}</td><td class="text-right">{{ $x['incidents'] }}</td></tr>
        @empty
            <tr><td colspan="6" class="ant-report-empty">Belum ada site.</td></tr>
        @endforelse
        </tbody>
    </table>
</section>

<section class="ant-report-section">
    @php($withOutage = array_values(array_filter($devices, fn ($d) => $d['outages'] > 0)))
    <h2>4. Perangkat dengan gangguan ({{ count($withOutage) }} dari {{ count($devices) }})</h2>
    @if(count($withOutage))
        <table class="table table-condensed">
            <thead><tr><th>Perangkat</th><th>Site</th><th class="text-right">Availability</th><th class="text-right">Downtime</th><th class="text-right">Gangguan</th><th class="text-right">MTTR</th><th class="text-right">Dikecualikan</th></tr></thead>
            <tbody>
            @foreach(array_slice($withOutage, 0, $deviceLimit) as $x)
                <tr><td>{{ $x['name'] }} <span class="ant-report-muted">{{ $x['ip'] }}</span></td><td>{{ $x['site'] ?? '—' }}</td><td class="text-right">{{ $pct($x['availability']) }}</td><td class="text-right">{{ $fmt($x['downtime']) }}</td><td class="text-right">{{ $x['outages'] }}</td><td class="text-right">{{ $fmt($x['mttr']) }}</td><td class="text-right">{{ $x['excluded'] ? $fmt($x['excluded']) : '—' }}</td></tr>
            @endforeach
            </tbody>
        </table>
        @if(count($withOutage) > $deviceLimit)<p class="ant-report-muted">… dan {{ count($withOutage) - $deviceLimit }} perangkat lain (lengkap di export Excel/CSV).</p>@endif
    @else
        <p class="ant-report-empty">Tidak ada perangkat yang down pada periode ini.</p>
    @endif
</section>

<section class="ant-report-section">
    <h2>5. Incident ({{ count($incidents) }})</h2>
    @if(count($incidents))
        <table class="table table-condensed">
            <thead><tr><th>Nomor</th><th>Judul</th><th>Severity</th><th>Status</th><th>Mulai</th><th class="text-right">Respons</th><th class="text-right">Pulih</th><th>Root cause</th></tr></thead>
            <tbody>
            @foreach($incidents as $x)
                <tr>
                    <td class="ant-report-nowrap">{{ $x['number'] }}</td><td>{{ $x['title'] }}</td><td>{{ $S::incident($x['severity'])['label'] }}</td>
                    <td>{{ \Antareja\Models\Incident::STATUSES[$x['status']]['label'] ?? $x['status'] }}</td><td class="ant-report-nowrap">{{ $x['started_at']->format('d M H:i') }}</td>
                    <td class="text-right">{{ $fmt($x['tta']) }}</td><td class="text-right">{{ $fmt($x['ttr']) }}</td><td>{{ \Illuminate\Support\Str::limit($x['root_cause'] ?? '—', 120) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @else
        <p class="ant-report-empty">Tidak ada incident.</p>
    @endif
</section>

<section class="ant-report-section">
    <h2>6. Maintenance ({{ count($maintenance) }})</h2>
    @if(count($maintenance))
        <table class="table table-condensed">
            <thead><tr><th>Judul</th><th>Jenis</th><th>Jadwal</th><th class="text-right">Perangkat</th><th>Status</th><th>Hasil</th><th>SLA</th></tr></thead>
            <tbody>
            @foreach($maintenance as $x)
                <tr><td>{{ $x['title'] }}</td><td>{{ ucfirst($x['type']) }}</td><td class="ant-report-nowrap">{{ $x['starts_at']->format('d M H:i') }} – {{ $x['ends_at']->format('d M H:i') }}</td><td class="text-right">{{ $x['devices'] }}</td>
                    <td>{{ \Antareja\Models\MaintenanceWindow::STATUSES[$x['status']]['label'] ?? $x['status'] }}</td><td>{{ \Antareja\Models\MaintenanceWindow::RESULTS[$x['result']] ?? '—' }}</td><td>{{ $x['exclude_from_sla'] ? 'dikecualikan' : 'dihitung' }}</td></tr>
            @endforeach
            </tbody>
        </table>
    @else
        <p class="ant-report-empty">Tidak ada maintenance.</p>
    @endif
</section>

<section class="ant-report-section">
    <h2>7. Kualitas jaringan (Quality Probe)</h2>
    @if(count($quality))
        <table class="table table-condensed">
            <thead><tr><th>Link / target</th><th>Site</th><th class="text-right">Ketersediaan</th><th class="text-right">Loss rata-rata</th><th class="text-right">Latency median</th><th class="text-right">Latency maks</th><th class="text-right">Jitter</th></tr></thead>
            <tbody>
            @foreach($quality as $x)
                <tr><td>{{ $x['name'] }} <span class="ant-report-muted">{{ $x['host'] }}</span></td><td>{{ $x['site'] ?? '—' }}</td>
                    @if($x['samples'])
                        <td class="text-right">{{ $pct($x['availability'], 2) }}</td><td class="text-right">{{ $num($x['avg_loss'], 2) }}%</td><td class="text-right">{{ $x['avg_latency'] !== null ? $num($x['avg_latency']) . ' ms' : '—' }}</td><td class="text-right">{{ $x['max_latency'] !== null ? $num($x['max_latency']) . ' ms' : '—' }}</td><td class="text-right">{{ $x['avg_jitter'] !== null ? $num($x['avg_jitter']) . ' ms' : '—' }}</td>
                    @else
                        <td colspan="5" class="ant-report-muted">tidak ada pengukuran</td>
                    @endif
                </tr>
            @endforeach
            </tbody>
        </table>
    @else
        <p class="ant-report-empty">Belum ada target Quality Probe.</p>
    @endif
</section>

<section class="ant-report-section">
    <h2>8. Utilisasi IP</h2>
    @if(count($ipam))
        <table class="table table-condensed">
            <thead><tr><th>Subnet</th><th>Nama</th><th>Site</th><th class="text-right">Usable</th><th class="text-right">Teralokasi</th><th class="text-right">Tersedia</th><th class="text-right">Utilisasi</th><th class="text-right">Konflik</th></tr></thead>
            <tbody>
            @foreach($ipam as $x)
                <tr><td>{{ $x['cidr'] }}</td><td>{{ $x['name'] }}</td><td>{{ $x['site'] ?? '—' }}</td><td class="text-right">{{ $x['usable'] }}</td><td class="text-right">{{ $x['allocated'] }}</td><td class="text-right">{{ $x['available'] }}</td>
                    <td class="text-right">{{ $num($x['utilization']) }}%{{ $x['utilization'] >= 90 ? ' ⚠' : '' }}</td><td class="text-right">{{ $x['conflicts'] ?: '—' }}</td></tr>
            @endforeach
            </tbody>
        </table>
    @else
        <p class="ant-report-empty">Belum ada subnet IPv4.</p>
    @endif
</section>

<section class="ant-report-section">
    <h2>9. Problem per jenis</h2>
    @if(count($problems))
        <table class="table table-condensed ant-report-kv">
            <thead><tr><th>Jenis</th><th>Severity</th><th class="text-right">Jumlah</th></tr></thead>
            <tbody>
            @foreach(collect($problems)->sortByDesc('count') as $x)
                <tr><td>{{ \Antareja\Models\Problem::CLASSES[$x['class']] ?? $x['class'] }}</td><td>{{ $S::problem($x['severity'])['label'] }}</td><td class="text-right">{{ $x['count'] }}</td></tr>
            @endforeach
            </tbody>
        </table>
    @else
        <p class="ant-report-empty">Tidak ada problem.</p>
    @endif
</section>

<p class="ant-report-muted">
    Catatan perhitungan: availability = (waktu periode − downtime) ÷ waktu periode; downtime dari outage poller;
    downtime selama maintenance yang dikecualikan tidak dihitung; ketersediaan Quality Probe = persentase pengukuran yang tidak 100% loss.
    Dibuat oleh ANTAREJA NMS {{ \Antareja\AntarejaServiceProvider::VERSION }} pada {{ now()->format('d M Y H:i') }} ({{ config('app.timezone') }}).
</p>
