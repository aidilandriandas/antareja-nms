{{-- Status monitoring satu device LibreNMS: Online / Offline / Disabled. --}}
@props(['device', 'size' => 'sm'])
@if(! $device)
    <x-antareja::status status="unknown" :size="$size">—</x-antareja::status>
@elseif($device->disabled)
    <x-antareja::status status="unknown" :size="$size">Disabled</x-antareja::status>
@elseif($device->status)
    <x-antareja::status status="ok" :size="$size">Online</x-antareja::status>
@else
    <x-antareja::status status="down" :size="$size" :title="$device->status_reason">Offline</x-antareja::status>
@endif
