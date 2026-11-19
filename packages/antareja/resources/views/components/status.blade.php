{{-- StatusBadge design system. status: ok|warn|crit|down|info|unknown. Selalu ikon + kata. --}}
@props(['status' => 'unknown', 'size' => null])
@php
    $status = in_array($status, ['ok', 'warn', 'crit', 'down', 'info', 'unknown'], true) ? $status : 'unknown';
    $default = ['ok' => 'Baik', 'warn' => 'Menurun', 'crit' => 'Buruk', 'down' => 'Putus', 'info' => 'Info', 'unknown' => 'Belum ada data'][$status];
@endphp
<span {{ $attributes->class(['ant-badge', 'ant-badge--' . $status, 'ant-badge--sm' => $size === 'sm']) }}><x-antareja::status-icon :status="$status" /><span>{{ $slot->isEmpty() ? $default : $slot }}</span></span>
