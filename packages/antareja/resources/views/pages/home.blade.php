@extends('layouts.librenmsv1')

@section('title', 'Pusat Operasi')

@php
    $toneVar = ['ok' => 'ok', 'info' => 'info', 'warn' => 'warn', 'crit' => 'crit', 'down' => 'down', 'unknown' => 'unknown'];
    $down = $stats['down'] ?? 0;
    $problemCount = $ops['problems'] ?? 0;
    $allClear = $stats !== null && $down === 0 && $problemCount === 0;
    $availTone = $stats && $stats['availability'] !== null ? ($stats['availability'] >= 99 ? 'ok' : ($stats['availability'] >= 95 ? 'warn' : 'crit')) : 'unknown';
    $maxSeverity = $severity ? max(1, $severity->max('n')) : 1;
@endphp

@section('content')
<div class="ant-ops">
    @if(session('antareja_success'))
        <x-antareja::alert tone="ok">{{ session('antareja_success') }}</x-antareja::alert>
    @endif
    @if(session('antareja_warning'))
        <x-antareja::alert tone="warn">{{ session('antareja_warning') }}</x-antareja::alert>
    @endif

    {{-- Kepala halaman --}}
    <header class="ant-ops__head">
        <div>
            <p class="ant-eyebrow">Pusat Operasi</p>
            <h1 class="ant-ops__title">{{ $greeting }}, {{ Auth::user()->realname ?: Auth::user()->username }}</h1>
            <p class="ant-ops__sub">
                Kondisi infrastruktur ICT per {{ now()->locale('id')->translatedFormat('l, j F Y · H:i') }}
                @if($stats && $stats['last_polled'])
                    · data monitoring terakhir {{ \Illuminate\Support\Carbon::parse($stats['last_polled'])->locale('id')->diffForHumans() }}
                @endif
            </p>
        </div>
        <div class="ant-ops__actions">
            <span class="ant-ops__refresh" id="ant-refresh" title="Halaman diperbarui otomatis setiap 60 detik">
                <i class="fa-solid fa-rotate" aria-hidden="true"></i> Otomatis 60 dtk
            </span>
            <a href="{{ route('antareja.noc') }}" class="ant-btn ant-btn--secondary" target="_blank" rel="noopener"><i class="fa-solid fa-display" aria-hidden="true"></i> Layar NOC</a>
            @can('ant-incident.create')
                <a href="{{ route('antareja.incidents.create') }}" class="ant-btn ant-btn--secondary"><i class="fa-solid fa-fire-extinguisher" aria-hidden="true"></i> Buat insiden</a>
            @endcan
            @can('device.create')
                <a href="{{ route('antareja.devices.add') }}" class="ant-btn ant-btn--primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> Tambah perangkat</a>
            @endcan
        </div>
    </header>

    {{-- Status keseluruhan: satu kalimat yang langsung menjawab "aman atau tidak" --}}
    @if($stats !== null)
        @if($stats['total'] === 0)
            <div class="ant-banner ant-banner--info">
                <i class="fa-solid fa-satellite-dish" aria-hidden="true"></i>
                <div><strong>Belum ada perangkat yang dipantau.</strong> Mulai dengan menambahkan site tambang, lalu daftarkan perangkat lewat SNMP.</div>
                <div class="ant-banner__cta">
                    @can('ant-site.view')<a href="{{ route('antareja.sites.index') }}">Atur Site &amp; Area</a>@endcan
                    @can('device.create')<a href="{{ route('antareja.devices.add') }}">Tambah perangkat &rarr;</a>@endcan
                </div>
            </div>
        @elseif($allClear)
            <div class="ant-banner ant-banner--ok">
                <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                <div><strong>Semua normal.</strong> {{ $stats['up'] }} perangkat online, tidak ada problem aktif.</div>
            </div>
        @else
            <div @class(['ant-banner', 'ant-banner--crit' => $down > 0 || ($ops['problems'] ?? 0) > 0])>
                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                <div>
                    <strong>Perlu perhatian:</strong>
                    @if($down > 0) {{ $down }} perangkat down @endif
                    @if($down > 0 && $problemCount > 0) · @endif
                    @if($problemCount > 0) {{ $problemCount }} problem aktif ({{ $ops['unacked'] }} belum di-acknowledge) @endif
                </div>
                @can('ant-problem.view')
                    <div class="ant-banner__cta"><a href="{{ route('antareja.problems.index') }}">Tangani sekarang &rarr;</a></div>
                @endcan
            </div>
        @endif
    @endif

    {{-- KPI --}}
    <section class="ant-kpis" aria-label="Ringkasan">
        @if($stats !== null)
            <a class="ant-kpi" href="{{ route('devices') }}">
                <span class="ant-kpi__label">Ketersediaan perangkat</span>
                <span class="ant-kpi__value">{{ $stats['availability'] !== null ? number_format($stats['availability'], 1, ',', '.') : '–' }}<small>%</small></span>
                <span class="ant-meter ant-meter--{{ $availTone }}" role="img" aria-label="Ketersediaan {{ $stats['availability'] ?? 0 }} persen">
                    <span style="width: {{ $stats['availability'] ?? 0 }}%"></span>
                </span>
                <span class="ant-kpi__hint">{{ $stats['up'] }} dari {{ $stats['up'] + $stats['down'] }} perangkat online</span>
            </a>
            <a @class(['ant-kpi', 'ant-kpi--crit' => $down > 0]) href="{{ route('devices') }}">
                <span class="ant-kpi__label">Perangkat down</span>
                <span class="ant-kpi__value">{{ $down }}</span>
                <span class="ant-kpi__hint">
                    @if($down > 0)<x-antareja::status status="down" size="sm">Putus</x-antareja::status>@else<x-antareja::status status="ok" size="sm">Semua online</x-antareja::status>@endif
                    @if($stats['disabled']) <span class="ant-muted">· {{ $stats['disabled'] }} nonaktif</span>@endif
                </span>
            </a>
        @endif
        @if($ops !== null)
            <a @class(['ant-kpi', 'ant-kpi--crit' => $problemCount > 0]) href="{{ route('antareja.problems.index') }}">
                <span class="ant-kpi__label">Problem aktif</span>
                <span class="ant-kpi__value">{{ $problemCount }}</span>
                <span class="ant-kpi__hint">
                    @if($problemCount > 0)
                        {{ $ops['unacked'] }} belum di-ack
                    @else
                        Tidak ada problem
                    @endif
                    @if($ops['suppressed']) · {{ $ops['suppressed'] }} diredam @endif
                </span>
            </a>
            @if($ops['incidents'] !== null)
                <a @class(['ant-kpi', 'ant-kpi--warn' => $ops['incidents'] > 0]) href="{{ route('antareja.incidents.index') }}">
                    <span class="ant-kpi__label">Insiden aktif</span>
                    <span class="ant-kpi__value">{{ $ops['incidents'] }}</span>
                    <span class="ant-kpi__hint">{{ $ops['incidents'] ? 'Sedang ditangani tim' : 'Tidak ada insiden' }}</span>
                </a>
            @endif
            @if($ops['maintenance'] !== null)
                <a class="ant-kpi" href="{{ route('antareja.maintenance.index') }}">
                    <span class="ant-kpi__label">Maintenance berjalan</span>
                    <span class="ant-kpi__value">{{ $ops['maintenance'] }}</span>
                    <span class="ant-kpi__hint">{{ $ops['maintenance'] ? 'Alert di area terkait diredam' : 'Tidak ada jendela aktif' }}</span>
                </a>
            @endif
        @endif
    </section>

    <div class="ant-grid">
        {{-- Problem aktif --}}
        @if($problems !== null)
            <section class="ant-card ant-grid__wide">
                <header class="ant-card__head">
                    <h2>Problem aktif</h2>
                    <a href="{{ route('antareja.problems.index') }}">Semua problem &rarr;</a>
                </header>
                @if($problems->isEmpty())
                    <div class="ant-empty">
                        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                        <p><strong>Tidak ada problem aktif.</strong><br>Problem dari perangkat down, alert, trigger, kualitas link, dan CCTV akan muncul di sini.</p>
                    </div>
                @else
                    <ul class="ant-plist">
                        @foreach($problems as $p)
                            @php($sev = $p->severityMeta())
                            <li>
                                <a href="{{ route('antareja.problems.show', $p) }}" class="ant-plist__row">
                                    <span class="ant-plist__sev ant-plist__sev--{{ $sev['tone'] }}" aria-hidden="true"></span>
                                    <span class="ant-plist__main">
                                        <span class="ant-plist__title">{{ $p->title }}</span>
                                        <span class="ant-plist__meta">
                                            <x-antareja::status :status="$sev['tone']" size="sm">{{ $sev['label'] }}</x-antareja::status>
                                            @if($p->device) <span>{{ $p->device->displayName() }}</span>@endif
                                            @if($p->site) <span>· {{ $p->site->code }}</span>@endif
                                        </span>
                                    </span>
                                    <span class="ant-plist__side">
                                        <span class="ant-plist__age" title="Mulai {{ $p->started_at->format('d/m H:i') }}">{{ $p->duration() }}</span>
                                        @if($p->acknowledged_at)
                                            <span class="ant-plist__ack is-ack"><i class="fa-solid fa-check" aria-hidden="true"></i> Ack</span>
                                        @else
                                            <span class="ant-plist__ack">Belum ack</span>
                                        @endif
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            {{-- Sebaran severity: panjang batang = jumlah; warna status selalu disertai nama severity & angka --}}
            <section class="ant-card">
                <header class="ant-card__head"><h2>Sebaran severity</h2></header>
                <ul class="ant-sevbars">
                    @foreach($severity as $s)
                        <li>
                            <span class="ant-sevbars__label">{{ $s['label'] }}</span>
                            <span class="ant-sevbars__track">
                                @if($s['n'] > 0)<span class="ant-sevbars__fill ant-fill--{{ $s['tone'] }}" style="width: {{ max(4, $s['n'] / $maxSeverity * 100) }}%"></span>@endif
                            </span>
                            <span @class(['ant-sevbars__n', 'ant-muted' => $s['n'] === 0])>{{ $s['n'] }}</span>
                        </li>
                    @endforeach
                </ul>
                @if(($ops['suppressed'] ?? 0) > 0)
                    <p class="ant-card__note">{{ $ops['suppressed'] }} problem lain diredam (akibat perangkat induk down atau dalam jendela maintenance).</p>
                @endif
            </section>
        @endif

        {{-- Status per site --}}
        @if($sites !== null)
            <section class="ant-card ant-grid__wide">
                <header class="ant-card__head">
                    <h2>Status per site</h2>
                    <span class="ant-legend" aria-hidden="true">
                        <span><i class="ant-fill--ok"></i>Online</span><span><i class="ant-fill--down"></i>Down</span><span><i class="ant-fill--unknown"></i>Nonaktif</span>
                    </span>
                </header>
                @if($sites->isEmpty())
                    <div class="ant-empty">
                        <i class="fa-solid fa-map-location-dot" aria-hidden="true"></i>
                        <p><strong>Belum ada perangkat yang ditempatkan di site.</strong><br>Tetapkan site tiap perangkat di Inventaris Perangkat agar status per lokasi tambang terlihat.</p>
                    </div>
                @else
                    <ul class="ant-sites">
                        @foreach($sites as $s)
                            <li>
                                <a href="{{ route('antareja.sites.show', $s->id) }}" class="ant-sites__row">
                                    <span class="ant-sites__name"><b>{{ $s->code }}</b> {{ $s->name }}</span>
                                    <span class="ant-stack" role="img" aria-label="{{ $s->up }} online, {{ $s->down }} down, {{ $s->off }} nonaktif">
                                        @if($s->up)<span class="ant-fill--ok" style="flex-grow: {{ $s->up }}"></span>@endif
                                        @if($s->down)<span class="ant-fill--down" style="flex-grow: {{ $s->down }}"></span>@endif
                                        @if($s->off)<span class="ant-fill--unknown" style="flex-grow: {{ $s->off }}"></span>@endif
                                    </span>
                                    <span class="ant-sites__nums">
                                        @if($s->down)<b class="ant-text-crit">{{ $s->down }} down</b> · @endif{{ $s->up }}/{{ $s->total }} online
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                    @if($sites->unassigned > 0)
                        <p class="ant-card__note">{{ $sites->unassigned }} perangkat belum punya site — @if(\Antareja\Support\ModuleRegistry::enabled('device-inventory'))<a href="{{ route('antareja.inventory.index', ['issue' => 'missing_site']) }}">lengkapi di inventaris</a>@else atur site-nya di halaman pengaturan perangkat (<a href="{{ route('devices') }}">daftar perangkat</a>)@endif.</p>
                    @endif
                @endif
            </section>
        @endif

        {{-- Pekerjaan --}}
        @if($work !== null)
            <section class="ant-card">
                <header class="ant-card__head"><h2>Pekerjaan tim</h2></header>
                <div class="ant-minis">
                    @if($work['wo_active'] !== null)
                        <a href="{{ route('antareja.workorders.index') }}" class="ant-mini"><b>{{ $work['wo_active'] }}</b><span>Work order aktif</span></a>
                        <a href="{{ route('antareja.workorders.index') }}" @class(['ant-mini', 'ant-mini--crit' => $work['wo_overdue'] > 0])><b>{{ $work['wo_overdue'] }}</b><span>Lewat tenggat</span></a>
                    @endif
                    @if($work['cr_pending'] !== null)
                        <a href="{{ route('antareja.changes.index') }}" @class(['ant-mini', 'ant-mini--warn' => $work['cr_pending'] > 0])><b>{{ $work['cr_pending'] }}</b><span>Change menunggu persetujuan</span></a>
                    @endif
                </div>
                @if($work['cr_upcoming']->isNotEmpty())
                    <h3 class="ant-card__sub">Jadwal change 7 hari ke depan</h3>
                    <ul class="ant-simple">
                        @foreach($work['cr_upcoming'] as $c)
                            <li>
                                <a href="{{ route('antareja.changes.show', $c) }}">{{ $c->title }}</a>
                                <span class="ant-muted">{{ $c->planned_start?->locale('id')->translatedFormat('D j M, H:i') }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endif

        {{-- CCTV --}}
        @if($cctv !== null && $cctv['total'] > 0)
            <section class="ant-card">
                <header class="ant-card__head">
                    <h2>Kesehatan gambar CCTV</h2>
                    <a href="{{ route('antareja.cctv.live') }}">Live view &rarr;</a>
                </header>
                <p class="ant-card__lead">
                    <b class="ant-text-ok">{{ $cctv['ok'] }}</b> normal dari {{ $cctv['total'] }} kamera yang dicek
                    @if($cctv['issues']->isNotEmpty()) · <b class="ant-text-crit">{{ $cctv['issues']->count() }}{{ $cctv['issues']->count() >= 5 ? '+' : '' }}</b> bermasalah @endif
                </p>
                @if($cctv['issues']->isNotEmpty())
                    <ul class="ant-simple">
                        @foreach($cctv['issues'] as $st)
                            @php($hm = $st->healthMeta())
                            <li>
                                <a href="{{ route('antareja.cctv.streams.show', $st) }}">{{ $st->device?->displayName() ?? 'Kamera #' . $st->id }}</a>
                                <x-antareja::status :status="$hm['tone']" size="sm">{{ $hm['label'] }}</x-antareja::status>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endif

        {{-- Kualitas jaringan --}}
        @if($qualityProblems !== null && $qualityProblems->isNotEmpty())
            <section class="ant-card">
                <header class="ant-card__head">
                    <h2>Kualitas link bermasalah</h2>
                    <a href="{{ route('antareja.quality.index') }}">Semua target &rarr;</a>
                </header>
                <ul class="ant-simple">
                    @foreach($qualityProblems as $qt)
                        <li>
                            <a href="{{ route('antareja.quality.show', $qt) }}">{{ $qt->name }}</a>
                            <x-antareja::status :status="$qt->statusMeta()['tone']" size="sm">{{ $qt->statusMeta()['label'] }}</x-antareja::status>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- Kualitas data inventaris: hanya masalah yang ada --}}
        @if($issues !== null)
            @php($open = collect(\Antareja\Services\DataQuality::ISSUES)->map(fn ($i, $k) => $i + ['key' => $k, 'n' => $issues[$k] ?? 0])->filter(fn ($i) => $i['n'] > 0))
            <section class="ant-card">
                <header class="ant-card__head">
                    <h2>Kelengkapan data inventaris</h2>
                    <a href="{{ route('antareja.inventory.index') }}">Inventaris &rarr;</a>
                </header>
                @if($open->isEmpty() && ($issues['ip_conflicts'] ?? 0) === 0)
                    <p class="ant-card__lead"><i class="fa-solid fa-circle-check ant-text-ok" aria-hidden="true"></i> Data inventaris lengkap.</p>
                @else
                    <ul class="ant-simple">
                        @if(($issues['ip_conflicts'] ?? 0) > 0)
                            <li><a href="{{ route('antareja.ipam.conflicts') }}">Konflik IP</a><b class="ant-text-crit">{{ $issues['ip_conflicts'] }}</b></li>
                        @endif
                        @foreach($open->sortByDesc('n')->take(6) as $i)
                            <li>
                                <a href="{{ route('antareja.inventory.index', ['issue' => $i['key']]) }}">{{ $i['label'] }}</a>
                                <b @class(['ant-text-crit' => $i['severity'] === 'danger', 'ant-text-warn' => $i['severity'] === 'warning'])>{{ $i['n'] }}</b>
                            </li>
                        @endforeach
                        @if($issues['suggested_links'] > 0)
                            <li><a href="{{ route('antareja.mapping.index', ['status' => 'suggested']) }}">Saran mapping menunggu verifikasi</a><b>{{ $issues['suggested_links'] }}</b></li>
                        @endif
                    </ul>
                @endif
            </section>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Muat ulang otomatis tiap 60 detik selama tab terlihat (tidak saat user sedang membaca di tab lain)
    (function () {
        var due = Date.now() + 60000;
        setInterval(function () {
            if (!document.hidden && Date.now() >= due) location.reload();
        }, 5000);
    })();
</script>
@endpush
