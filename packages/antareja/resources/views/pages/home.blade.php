@extends('layouts.librenmsv1')

@section('title', 'ANTAREJA NMS')

@php
    $statusStyle = [
        'native' => ['label' => 'Tersedia', 'class' => 'tw:bg-emerald-100 tw:text-emerald-800 tw:dark:bg-emerald-900 tw:dark:text-emerald-100'],
        'partial' => ['label' => 'Sebagian', 'class' => 'tw:bg-amber-100 tw:text-amber-800 tw:dark:bg-amber-900 tw:dark:text-amber-100'],
        'planned' => ['label' => 'Direncanakan', 'class' => 'tw:bg-gray-200 tw:text-gray-700 tw:dark:bg-dark-gray-200 tw:dark:text-gray-200'],
    ];
@endphp

@section('content')
<div class="container-fluid">
    <div class="tw:flex tw:flex-wrap tw:items-end tw:justify-between tw:gap-4 tw:mt-2 tw:mb-6">
        <div>
            <h1 class="tw:text-3xl tw:font-bold tw:m-0">ANTAREJA NMS</h1>
            <p class="tw:text-base tw:text-gray-500 tw:dark:text-gray-400 tw:m-0 tw:mt-1">
                Mining ICT Infrastructure Management &amp; Unified Network Monitoring Platform
            </p>
        </div>
        <div class="tw:text-sm tw:text-gray-500 tw:dark:text-gray-400">
            Versi {{ $version }} · Fase 0 (Fondasi)
        </div>
    </div>

    @if($stats)
    <div class="tw:grid tw:grid-cols-2 tw:lg:grid-cols-4 tw:gap-4 tw:mb-6">
        @foreach([
            ['Total perangkat', $stats['total'], 'tw:text-gray-900 tw:dark:text-white', url('devices')],
            ['Online', $stats['up'], 'tw:text-emerald-600 tw:dark:text-emerald-400', url('devices/state=up')],
            ['Offline', $stats['down'], 'tw:text-red-600 tw:dark:text-red-400', url('devices/state=down')],
            ['Disabled / ignored', $stats['disabled'], 'tw:text-gray-500 tw:dark:text-gray-400', url('devices/disabled=1')],
        ] as [$label, $value, $color, $link])
            <a href="{{ $link }}" class="tw:block tw:no-underline tw:hover:no-underline tw:rounded-lg tw:border tw:border-gray-200 tw:dark:border-dark-gray-200 tw:bg-white tw:dark:bg-dark-gray-300 tw:p-4">
                <div class="tw:text-sm tw:text-gray-500 tw:dark:text-gray-400">{{ $label }}</div>
                <div class="tw:text-3xl tw:font-semibold {{ $color }}">{{ number_format($value) }}</div>
            </a>
        @endforeach
    </div>
    @endif

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
            <div class="tw:flex tw:flex-col tw:rounded-lg tw:border tw:border-gray-200 tw:dark:border-dark-gray-200 tw:bg-white tw:dark:bg-dark-gray-300 tw:p-4">
                <div class="tw:flex tw:items-start tw:justify-between tw:gap-2">
                    <div class="tw:flex tw:items-center tw:gap-2">
                        <i class="fa fa-{{ $module['icon'] }} fa-fw fa-lg tw:text-gray-500 tw:dark:text-gray-400" aria-hidden="true"></i>
                        <span class="tw:text-base tw:font-semibold">{{ $loop->iteration }}. {{ $module['label'] }}</span>
                    </div>
                    <span class="tw:shrink-0 tw:rounded-full tw:px-2.5 tw:py-0.5 tw:text-xs tw:font-medium {{ $style['class'] }}">{{ $style['label'] }}</span>
                </div>
                <p class="tw:text-sm tw:text-gray-600 tw:dark:text-gray-300 tw:mt-2 tw:mb-3 tw:grow">{{ $module['description'] }}</p>
                <div class="tw:flex tw:items-center tw:justify-between tw:text-xs tw:text-gray-500 tw:dark:text-gray-400">
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
        <li><strong>Fase 0 — Fondasi:</strong> fork LibreNMS, paket ANTAREJA, branding, role, Docker Compose. <em>(sedang berjalan)</em></li>
        <li><strong>Fase 1 — Inventaris tambang:</strong> Site/Area, detail device, verifikasi discovery, device mapping, audit log.</li>
        <li><strong>Fase 2 — IPAM &amp; Kualitas:</strong> IPAM, Quality Probe + grafik smoke, Wireless &amp; CCTV.</li>
        <li><strong>Fase 3 — Problem &amp; Incident:</strong> korelasi event, incident, eskalasi, kalender maintenance.</li>
        <li><strong>Fase 4 — SLA &amp; Laporan:</strong> service tree, trigger expression, SLA bulanan, dashboard manajemen, export.</li>
        <li><strong>Fase 5 — Lanjutan:</strong> work order, change &amp; config tracking, capacity, fiber optic, AI.</li>
    </ol>
</div>
@endsection
