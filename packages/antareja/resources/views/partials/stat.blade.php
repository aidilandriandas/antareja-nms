{{-- Kotak statistik. $label, $value, $color (opsional), $href (opsional), $hint (opsional) --}}
@php($tag = isset($href) ? 'a' : 'div')
<{{ $tag }} @isset($href) href="{{ $href }}" @endisset class="tw:block tw:no-underline tw:hover:no-underline tw:rounded-lg tw:border tw:border-gray-200 tw:dark:border-dark-gray-200 tw:bg-white tw:dark:bg-dark-gray-300 tw:p-3">
    <div class="tw:text-xs tw:text-gray-500 tw:dark:text-gray-400">{{ $label }}</div>
    <div class="tw:text-2xl tw:font-semibold tw:tabular-nums {{ $color ?? 'tw:text-gray-900 tw:dark:text-white' }}">{{ $value }}</div>
    @isset($hint)<div class="tw:text-xs tw:text-gray-500 tw:dark:text-gray-400">{{ $hint }}</div>@endisset
</{{ $tag }}>
