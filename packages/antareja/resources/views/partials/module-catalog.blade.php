{{-- Katalog modul & roadmap ANTAREJA (dipindah dari beranda lama) --}}
@php
    $statusStyle = [
        'native' => ['label' => 'Tersedia', 'class' => 'ant-badge ant-badge--ok'],
        'partial' => ['label' => 'Sebagian', 'class' => 'ant-badge ant-badge--warn'],
        'planned' => ['label' => 'Direncanakan', 'class' => 'ant-badge ant-badge--unknown'],
    ];
@endphp
    <div class="tw:flex tw:flex-wrap tw:items-center tw:gap-3 tw:mb-3">
        <h2 class="tw:text-xl tw:font-semibold tw:m-0">Modul Platform</h2>
        @foreach($statusStyle as $key => $style)
            <span class="tw:inline-flex tw:items-center tw:rounded-full tw:px-2.5 tw:py-0.5 tw:text-xs tw:font-medium {{ $style['class'] }}">
                {{ $style['label'] }}: {{ $counts[$key] ?? 0 }}
            </span>
        @endforeach
    </div>

    <div class="tw:grid tw:grid-cols-1 tw:md:grid-cols-2 tw:xl:grid-cols-3 tw:gap-4 tw:mb-8">
        @foreach($modules as $module)
            @php($style = $statusStyle[$module['status']])
            <div class="tw:flex tw:flex-col tw:rounded-lg tw:border ant-card tw:p-4">
                <div class="tw:flex tw:items-start tw:justify-between tw:gap-2">
                    <div class="tw:flex tw:items-center tw:gap-2">
                        <i class="fa fa-{{ $module['icon'] }} fa-fw fa-lg ant-muted" aria-hidden="true"></i>
                        <span class="tw:text-base tw:font-semibold">{{ $loop->iteration }}. {{ $module['label'] }}</span>
                    </div>
                    <span class="tw:shrink-0 tw:rounded-full tw:px-2.5 tw:py-0.5 tw:text-xs tw:font-medium {{ $style['class'] }}">{{ $style['label'] }}</span>
                </div>
                <p class="tw:text-sm ant-muted tw:mt-2 tw:mb-3 tw:grow">{{ $module['description'] }}</p>
                <div class="tw:flex tw:items-center tw:justify-between tw:text-xs ant-muted">
                    <span>{{ $module['phase'] === 0 ? 'Sudah dari inti' : 'Target: Fase ' . $module['phase'] }}</span>
                    @if($module['url'])
                        <a href="{{ $module['url'] }}" class="tw:font-medium">Buka &rarr;</a>
                    @elseif($module['status'] !== 'planned')
                        <span>Belum ada data</span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <h2 class="tw:text-xl tw:font-semibold tw:mb-3">Roadmap</h2>
    <ol class="tw:list-decimal tw:pl-6 tw:text-sm tw:space-y-1 tw:mb-8">
        <li><strong>Fase 0 — Fondasi:</strong> fork LibreNMS, paket ANTAREJA, branding, role, Docker Compose. <em>(selesai)</em></li>
        <li><strong>Fase 1 — Inventaris tambang:</strong> Site/Area, detail device, verifikasi discovery, device mapping, audit log, data quality. <em>(selesai)</em></li>
        <li><strong>Fase 2 — IPAM &amp; Kualitas:</strong> IPAM, Quality Probe + grafik smoke, Wireless &amp; CCTV. <em>(selesai)</em></li>
        <li><strong>Fase 3 — Problem &amp; Incident:</strong> korelasi event, incident, eskalasi, kalender maintenance. <em>(selesai)</em></li>
        <li><strong>Fase 4 — SLA &amp; Laporan:</strong> service tree, trigger expression, SLA bulanan, dashboard manajemen, export. <em>(selesai)</em></li>
        <li><strong>Fase 5 — Lanjutan:</strong> work order, change request &amp; backup konfigurasi, capacity planning, fiber optic, deteksi anomali &amp; asisten AI. <em>(selesai)</em></li>
    </ol>
