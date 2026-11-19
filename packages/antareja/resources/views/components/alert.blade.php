{{-- Alert design system. tone: info|ok|warn|crit --}}
@props(['tone' => 'info', 'title' => null])
<div {{ $attributes->class(['ant-alert', 'ant-alert--' . $tone]) }} role="{{ $tone === 'crit' ? 'alert' : 'status' }}">
    <x-antareja::status-icon :status="$tone" />
    <div class="ant-alert__body">
        @if($title)<div class="tw:font-semibold">{{ $title }}</div>@endif
        {{ $slot }}
    </div>
</div>
