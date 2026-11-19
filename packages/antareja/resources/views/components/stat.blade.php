{{-- StatTile design system. tone: neutral|ok|warn|crit (mewarnai angka saja). --}}
@props(['label', 'value', 'unit' => null, 'hint' => null, 'tone' => 'neutral', 'href' => null])
@php($cls = ['ant-stat', 'ant-stat--' . $tone => $tone !== 'neutral'])
@if($href)
<a href="{{ $href }}" {{ $attributes->class($cls) }}>
@else
<div {{ $attributes->class($cls) }}>
@endif
    <span class="ant-stat__label">{{ $label }}</span>
    <span class="ant-stat__value">{{ $value }}@if($unit)<span class="ant-stat__unit">{{ $unit }}</span>@endif</span>
    @if($hint)<span class="ant-stat__hint">{{ $hint }}</span>@endif
@if($href)</a>@else</div>@endif
