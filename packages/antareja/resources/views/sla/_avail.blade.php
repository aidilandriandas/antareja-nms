{{-- Availability dengan warna terhadap target. $s: stats, $target: float|null --}}
@php($a = $s['availability'] ?? null)
@php($t = $target ?? null)
@if($a === null)
    <span class="ant-muted">—</span>
@else
    @php($tone = $t === null ? ($a >= 99.9 ? 'ok' : ($a >= 99 ? 'warn' : 'crit')) : ($a >= $t ? 'ok' : ($a >= $t - 0.5 ? 'warn' : 'crit')))
    <span class="ant-data tw:font-semibold ant-text-{{ $tone }}">{{ \Antareja\Services\SlaCalculator::fmtPct($a) }}</span>
@endif
