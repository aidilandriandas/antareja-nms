{{-- ProgressBar design system. Bawaan: tinggi = buruk (utilisasi). inverted: tinggi = baik (kelengkapan). --}}
@props(['value' => 0, 'warnAt' => 80, 'critAt' => 90, 'inverted' => false, 'label' => ''])
@php
    $v = max(0, min(100, (float) $value));
    $tone = $inverted ? ($v < $critAt ? 'crit' : ($v < $warnAt ? 'warn' : null)) : ($v >= $critAt ? 'crit' : ($v >= $warnAt ? 'warn' : null));
@endphp
<div {{ $attributes->class(['ant-progress', 'ant-progress--' . $tone => $tone]) }} role="progressbar" aria-valuenow="{{ $v }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ $label }}">
    <div class="ant-progress__track"><div class="ant-progress__fill" style="width: {{ $v }}%"></div></div>
    <span class="ant-progress__value">{{ number_format($v, $v == floor($v) ? 0 : 1, ',', '.') }}%</span>
</div>
