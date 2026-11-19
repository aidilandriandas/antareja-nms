@extends('layouts.librenmsv1')

@section('title', 'ANTAREJA NMS')

@php
    $statusStyle = [
        'native' => ['label' => 'Tersedia', 'class' => 'ant-badge ant-badge--ok'],
        'partial' => ['label' => 'Sebagian', 'class' => 'ant-badge ant-badge--warn'],
        'planned' => ['label' => 'Direncanakan', 'class' => 'ant-badge ant-badge--unknown'],
    ];
@endphp

@section('content')
<div class="container-fluid antareja-page">
    <div class="tw:flex tw:flex-wrap tw:items-end tw:justify-between tw:gap-4 tw:mt-2 tw:mb-6">
        <div>
            <h1 class="tw:text-3xl tw:font-bold tw:m-0">ANTAREJA NMS</h1>
            <p class="tw:text-base ant-muted tw:m-0 tw:mt-1">
                Mining ICT Infrastructure Management &amp; Unified Network Monitoring Platform
            </p>
        </div>
        <div class="tw:text-sm ant-muted">
            Versi {{ $version }} · Fase 3 (Problem &amp; Incident)
        </div>
    </div>

    @if($stats)
    <div class="tw:grid tw:grid-cols-2 tw:lg:grid-cols-4 tw:gap-3 tw:mb-6">
        <x-antareja::stat label="Total perangkat" :value="number_format($stats['total'], 0, ',', '.')" :href="url('devices')" />
        <x-antareja::stat label="Online" :value="number_format($stats['up'], 0, ',', '.')" tone="ok" :href="url('devices/state=up')" />
        <x-antareja::stat label="Offline" :value="number_format($stats['down'], 0, ',', '.')" :tone="$stats['down'] > 0 ? 'crit' : 'neutral'" :href="url('devices/state=down')" />
        <x-antareja::stat label="Disabled / ignored" :value="number_format($stats['disabled'], 0, ',', '.')" :href="url('devices/disabled=1')" />
    </div>
    @endif

    @if($ops)
    <div class="tw:grid tw:grid-cols-2 tw:lg:grid-cols-5 tw:gap-3 tw:mb-6">
        <x-antareja::stat label="Problem aktif" :value="$ops['problems']" :tone="$ops['problems'] ? 'warn' : 'neutral'" :href="route('antareja.problems.index')" />
        <x-antareja::stat label="High / Disaster" :value="$ops['high']" :tone="$ops['high'] ? 'crit' : 'neutral'" :href="route('antareja.problems.index', ['severity' => 'high'])" />
        <x-antareja::stat label="Belum di-ack" :value="$ops['unacked']" :tone="$ops['unacked'] ? 'warn' : 'neutral'" :href="route('antareja.problems.index', ['ack' => 'no'])" />
        <x-antareja::stat label="Incident aktif" :value="$ops['incidents']" :tone="$ops['incidents'] ? 'warn' : 'neutral'" :href="route('antareja.incidents.index')" />
        <x-antareja::stat label="Maintenance berlangsung" :value="$ops['maintenance']" :href="route('antareja.maintenance.index')" />
    </div>
    @endif

    @if($qualityProblems !== null && $qualityProblems->isNotEmpty())
        <div class="panel panel-default tw:mb-6">
            <div class="panel-heading tw:flex tw:justify-between"><strong><i class="fa fa-signal"></i> Kualitas jaringan bermasalah</strong>
                <a href="{{ route('antareja.quality.index') }}" class="tw:text-sm">Semua target &rarr;</a></div>
            <table class="table table-condensed tw:m-0">
                @foreach($qualityProblems as $qt)
                    <tr>
                        <td><a href="{{ route('antareja.quality.show', $qt) }}">{{ $qt->name }}</a></td>
                        <td class="tw:text-xs ant-muted">{{ $qt->group_name }} {{ $qt->site?->code }}</td>
                        <td><x-antareja::status :status="$qt->statusMeta()['tone']">{{ $qt->statusMeta()['label'] }}</x-antareja::status></td>
                        <td class="tw:text-xs">{{ $qt->last_checked_at?->diffForHumans() }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endif

    @if($issues !== null)
    @php($sevClass = ['danger' => 'ant-text-crit', 'warning' => 'ant-text-warn', 'info' => 'ant-text-info'])
    <div class="tw:flex tw:flex-wrap tw:items-center tw:justify-between tw:gap-3 tw:mb-3">
        <h2 class="tw:text-xl tw:font-semibold tw:m-0">Kualitas Data Inventaris</h2>
        <span class="tw:flex tw:flex-wrap tw:gap-4 tw:text-sm">
            @if(($issues['ip_conflicts'] ?? 0) > 0)
                <a href="{{ route('antareja.ipam.conflicts') }}" class="ant-text-crit"><i class="fa fa-exclamation-triangle"></i> {{ $issues['ip_conflicts'] }} konflik IP &rarr;</a>
            @endif
            @if($issues['suggested_links'] > 0)
                <a href="{{ route('antareja.mapping.index', ['status' => 'suggested']) }}">{{ $issues['suggested_links'] }} saran mapping menunggu verifikasi &rarr;</a>
            @endif
        </span>
    </div>
    <div class="tw:grid tw:grid-cols-2 tw:md:grid-cols-3 tw:xl:grid-cols-4 tw:gap-3 tw:mb-8">
        @foreach(\Antareja\Services\DataQuality::ISSUES as $key => $issue)
            @php($n = $issues[$key] ?? 0)
            <a href="{{ route('antareja.inventory.index', ['issue' => $key]) }}"
               class="tw:flex tw:items-center tw:gap-3 tw:no-underline tw:hover:no-underline tw:rounded-lg tw:border ant-card tw:px-3 tw:py-2">
                <i class="fa fa-{{ $issue['icon'] }} fa-fw fa-lg {{ $n > 0 ? $sevClass[$issue['severity']] : 'ant-muted' }}" aria-hidden="true"></i>
                <span class="tw:grow tw:text-sm ">{{ $issue['label'] }}</span>
                <span class="tw:text-lg tw:font-semibold tw:tabular-nums {{ $n > 0 ? $sevClass[$issue['severity']] : 'ant-muted' }}">{{ $n }}</span>
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
        <li><strong>Fase 4 — SLA &amp; Laporan:</strong> service tree, trigger expression, SLA bulanan, dashboard manajemen, export.</li>
        <li><strong>Fase 5 — Lanjutan:</strong> work order, change &amp; config tracking, capacity, fiber optic, AI.</li>
    </ol>
</div>
@endsection
