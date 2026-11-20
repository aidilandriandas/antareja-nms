@if(! $d)
    <span class="ant-muted">—</span>
@elseif($d->isToday() || $d->isPast())
    <x-antareja::status :status="($crit ?? false) ? 'crit' : 'warn'" size="sm">sudah</x-antareja::status>
@else
    <span @class(['ant-text-crit tw:font-semibold' => ($crit ?? false) && $d->diffInDays(now()) < 90])>{{ $d->format('d M Y') }}</span>
    <span class="ant-muted">({{ (int) now()->startOfDay()->diffInDays($d) }} hr)</span>
@endif
