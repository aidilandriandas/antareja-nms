@php($details = is_array($details ?? null) ? $details : [])
@foreach(['librenms_version' => 'Engine', 'php_version' => 'PHP', 'os_version' => 'OS'] as $key => $label)
    @if(! empty($details[$key]))
        <div class="tw:text-nowrap"><span class="tw:font-bold">{{ $label }}:</span> {{ $details[$key] }}</div>
    @endif
@endforeach
