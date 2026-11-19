{{-- ANTAREJA NMS: menu modul terpadu. Disisipkan di resources/views/layouts/menu.blade.php --}}
<li class="dropdown">
    <a href="{{ route('antareja.home') }}" class="dropdown-toggle" data-hover="dropdown" data-toggle="dropdown">
        <i class="fa fa-th-large fa-fw fa-lg fa-nav-icons" aria-hidden="true"></i>
        <span class="tw:md:hidden tw:2xl:inline-block">ANTAREJA</span>
    </a>
    <ul class="dropdown-menu" role="menu">
        <li><a href="{{ route('antareja.home') }}"><i class="fa fa-th-large fa-fw fa-lg" aria-hidden="true"></i> Beranda ANTAREJA</a></li>
        <li role="presentation" class="divider"></li>
        @foreach($antareja_modules as $module)
            <li>
                <a href="{{ $module['url'] }}">
                    <i class="fa fa-{{ $module['icon'] }} fa-fw fa-lg" aria-hidden="true"></i> {{ $module['label'] }}
                </a>
            </li>
        @endforeach
        <li role="presentation" class="divider"></li>
        <li><a href="{{ route('antareja.about') }}"><i class="fa fa-info-circle fa-fw fa-lg" aria-hidden="true"></i> Tentang &amp; Lisensi</a></li>
    </ul>
</li>
