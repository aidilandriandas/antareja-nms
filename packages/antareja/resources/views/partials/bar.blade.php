{{-- Bar persentase. $value (0-100), $warn (default 80), $crit (default 90) --}}
@php($v = max(0, min(100, (float) $value)))
@php($color = $v >= ($crit ?? 90) ? 'tw:bg-red-500' : ($v >= ($warn ?? 80) ? 'tw:bg-amber-500' : 'tw:bg-emerald-500'))
<div class="tw:flex tw:items-center tw:gap-2">
    <div class="tw:w-24 tw:h-2 tw:rounded-full tw:bg-gray-200 tw:dark:bg-dark-gray-200 tw:overflow-hidden">
        <div class="tw:h-full {{ $color }}" style="width: {{ $v }}%"></div>
    </div>
    <span class="tw:text-xs tw:tabular-nums">{{ number_format($v, 1) }}%</span>
</div>
