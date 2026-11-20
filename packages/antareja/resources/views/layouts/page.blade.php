@extends('layouts.librenmsv1')

@section('content')
<div class="container-fluid antareja-page">
    <div class="tw:flex tw:flex-wrap tw:items-center tw:justify-between tw:gap-3 tw:mt-1 tw:mb-4">
        <div>
            <ol class="breadcrumb tw:mb-1! tw:p-0! tw:bg-transparent!">
                <li><a href="{{ route('antareja.home') }}">ANTAREJA</a></li>
                @yield('breadcrumb')
            </ol>
            <h1 class="tw:text-2xl tw:font-bold tw:m-0">@yield('heading')</h1>
            @hasSection('subheading')
                <p class="tw:text-sm ant-muted tw:m-0 tw:mt-1">@yield('subheading')</p>
            @endif
        </div>
        <div class="tw:flex tw:flex-wrap tw:gap-2">@yield('actions')</div>
    </div>

    @if(session('antareja_success'))
        <x-antareja::alert tone="ok">{{ session('antareja_success') }}</x-antareja::alert>
    @endif
    @if(session('antareja_warning'))
        <x-antareja::alert tone="warn">{{ session('antareja_warning') }}</x-antareja::alert>
    @endif
    @if(session('antareja_error'))
        <x-antareja::alert tone="crit">{{ session('antareja_error') }}</x-antareja::alert>
    @endif
    @if($errors->any())
        <x-antareja::alert tone="crit">
            @if($errors->count() === 1){{ $errors->first() }}@else
            <ul class="tw:m-0 tw:pl-5">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>@endif
        </x-antareja::alert>
    @endif

    @yield('page')
</div>
@endsection
