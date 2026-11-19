{{-- Kotak statistik → komponen StatTile design system. $label, $value, $color (opsional, nada diturunkan), $tone, $href, $hint --}}
@php
    $c = $color ?? '';
    $t = $tone ?? (str_contains($c, 'emerald') ? 'ok' : (str_contains($c, 'amber') ? 'warn' : (str_contains($c, 'red') ? 'crit' : 'neutral')));
@endphp
<x-antareja::stat :label="$label" :value="$value" :hint="$hint ?? null" :tone="$t" :href="$href ?? null" />
