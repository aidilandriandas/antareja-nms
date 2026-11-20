{{-- Navigasi bulan. $month: CarbonImmutable awal bulan; $route: nama route; $params: parameter route tambahan --}}
@php($params = $params ?? [])
<div class="tw:flex tw:items-center tw:gap-2">
    <a href="{{ route($route, array_merge($params, request()->except('month'), ['month' => $month->subMonth()->format('Y-m')])) }}" class="btn btn-default btn-sm" aria-label="Bulan sebelumnya"><i class="fa fa-chevron-left"></i></a>
    <h2 class="tw:text-xl tw:m-0 tw:min-w-44 tw:text-center">{{ $month->locale('id')->translatedFormat('F Y') }}</h2>
    @if($month->addMonth()->lte(now()))
        <a href="{{ route($route, array_merge($params, request()->except('month'), ['month' => $month->addMonth()->format('Y-m')])) }}" class="btn btn-default btn-sm" aria-label="Bulan berikutnya"><i class="fa fa-chevron-right"></i></a>
    @else
        <span class="btn btn-default btn-sm disabled" aria-hidden="true"><i class="fa fa-chevron-right"></i></span>
    @endif
    @if(! $month->isSameMonth(now()))<a href="{{ route($route, array_merge($params, request()->except('month'))) }}" class="btn btn-link btn-sm">Bulan ini</a>@endif
</div>
