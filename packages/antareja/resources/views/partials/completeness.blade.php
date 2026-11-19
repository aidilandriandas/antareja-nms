@php($score = $completeness['score'])
@php($color = $score >= 100 ? 'tw:bg-emerald-500' : ($score >= 60 ? 'tw:bg-amber-500' : 'tw:bg-red-500'))
<div class="tw:flex tw:items-center tw:gap-2" title="{{ $completeness['missing'] ? 'Belum diisi: ' . implode(', ', $completeness['missing']) : 'Lengkap' }}">
    <div class="tw:w-16 tw:h-2 tw:rounded-full tw:bg-gray-200 tw:dark:bg-dark-gray-200 tw:overflow-hidden">
        <div class="tw:h-full {{ $color }}" style="width: {{ $score }}%"></div>
    </div>
    <span class="tw:text-xs tw:tabular-nums">{{ $score }}%</span>
</div>
