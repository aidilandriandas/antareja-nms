{{--
    ANTAREJA NMS — kerangka aplikasi (menggantikan navbar atas LibreNMS).
    Sidebar berkelompok per pekerjaan + topbar (pencarian global, status problem, notifikasi, tema, akun).
    Elemen yang dipakai skrip LibreNMS tetap ada: #gsearch (pencarian), #countdown_timer* (refresh otomatis halaman).
--}}
@php($antUser = Auth::user())
<script>
    try { if (localStorage.getItem('ant-nav') === 'collapsed') document.documentElement.classList.add('ant-nav-collapsed'); } catch (e) {}
</script>

<aside class="ant-side" id="ant-side" aria-label="Navigasi utama">
    <a class="ant-side__brand" href="{{ route('antareja.home') }}">
        <img src="{{ asset('images/antareja/antareja-mark-64.png') }}" alt="" width="32" height="32">
        <span class="ant-side__wordmark">ANTAREJA<small>NMS</small></span>
    </a>
    <nav class="ant-side__nav">
        @foreach($ant_nav as $group)
            @php($collapsible = $group['title'] !== null)
            @php($open = ! ($group['collapsed'] ?? false))
            <div class="ant-navgroup" data-group="{{ $group['key'] }}" @if($collapsible && ! $open) data-closed @endif>
                @if($collapsible)
                    <button type="button" class="ant-navgroup__title" aria-expanded="{{ $open ? 'true' : 'false' }}" aria-controls="ant-group-{{ $group['key'] }}">
                        <span>{{ $group['title'] }}</span>
                        <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                    </button>
                @endif
                <ul id="ant-group-{{ $group['key'] }}">
                    @foreach($group['items'] as $item)
                        <li>
                            <a href="{{ $item['url'] }}" @class(['ant-navlink', 'is-active' => $item['active']]) @if($item['active']) aria-current="page" @endif title="{{ $item['label'] }}">
                                <i class="fa-solid fa-{{ $item['icon'] }} fa-fw" aria-hidden="true"></i>
                                <span>{{ $item['label'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </nav>
    <button type="button" class="ant-side__collapse" id="ant-nav-collapse" title="Ciutkan / lebarkan menu">
        <i class="fa-solid fa-angles-left" aria-hidden="true"></i><span>Ciutkan menu</span>
    </button>
</aside>
<div class="ant-scrim" id="ant-scrim" hidden></div>

<header class="ant-top">
    <button type="button" class="ant-iconbtn ant-top__menu" id="ant-nav-open" aria-label="Buka menu" aria-controls="ant-side" aria-expanded="false">
        <i class="fa-solid fa-bars" aria-hidden="true"></i>
    </button>
    <a class="ant-top__brand" href="{{ route('antareja.home') }}">
        <img src="{{ asset('images/antareja/antareja-mark-64.png') }}" alt="" width="26" height="26"><span>ANTAREJA</span>
    </a>

    <div class="ant-search global-search" x-data="globalSearch()" @keydown.escape="close()" @click.outside="close()">
        <i class="fa-solid fa-magnifying-glass ant-search__icon" aria-hidden="true"></i>
        <input class="ant-search__input" type="search" id="gsearch" name="gsearch" autocomplete="off"
               placeholder="Cari perangkat, IP, MAC, port…" aria-label="Pencarian global"
               x-model="query" x-ref="input"
               @input.debounce.250ms="run()" @focus="open = flat.length > 0"
               @keydown.down.prevent="move(1)" @keydown.up.prevent="move(-1)" @keydown.enter.prevent="goOrRun()">
        <kbd class="ant-search__kbd" aria-hidden="true">/</kbd>
        <div x-show="open" x-cloak x-ref="dropdown" class="ant-search__results">
            <div x-show="loading && flat.length === 0" class="ant-search__state"><i class="fa fa-spinner fa-spin"></i> Mencari…</div>
            <div x-show="!loading && flat.length === 0" class="ant-search__state">Tidak ada hasil.</div>
            <template x-for="group in groups" :key="group.type">
                <div>
                    <div class="ant-search__group" x-text="group.label"></div>
                    <template x-for="(item, index) in group.results" :key="index">
                        <a :href="item.url" @mouseenter="activeIndex = flat.findIndex(i => i === item)" class="ant-search__item"
                           :class="(flat[activeIndex] === item ? 'is-active ' : '') + (item.status ? 'tw:border-l-5 ' + item.status : '')">
                            <template x-if="item.image"><img :src="item.image" alt=""></template>
                            <template x-if="!item.image"><i class="fa fa-fw" :class="item.icon"></i></template>
                            <span class="ant-search__text">
                                <span x-text="item.name"></span>
                                <small x-text="item.subtitle"></small>
                            </span>
                        </a>
                    </template>
                </div>
            </template>
        </div>
    </div>

    <div class="ant-top__actions">
        @if($ant_problems !== null)
            <a href="{{ url('antareja/problems') }}" @class(['ant-pill', 'ant-pill--' . ($ant_problems['count'] ? $ant_problems['tone'] : 'ok')])
               title="Problem aktif">
                <i class="fa-solid {{ $ant_problems['count'] ? 'fa-triangle-exclamation' : 'fa-circle-check' }}" aria-hidden="true"></i>
                <span>{{ $ant_problems['count'] ? $ant_problems['count'] . ' problem' : 'Normal' }}</span>
            </a>
        @endif
        <a href="{{ route('notifications.index') }}" class="ant-iconbtn" title="Notifikasi" aria-label="Notifikasi{{ $notification_count ? ', ' . $notification_count . ' belum dibaca' : '' }}">
            <i class="fa-solid fa-bell" aria-hidden="true"></i>
            <span class="ant-dot count-notif" @if(! $notification_count) hidden @endif>{{ $notification_count ?: '' }}</span>
        </a>
        <button type="button" class="ant-iconbtn" id="ant-theme" title="Ganti tema terang / gelap" aria-label="Ganti tema">
            <i class="fa-solid fa-circle-half-stroke" aria-hidden="true"></i>
        </button>
        <div class="dropdown ant-user">
            <button type="button" class="ant-user__btn dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <span class="ant-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($antUser->realname ?: $antUser->username, 0, 1)) }}</span>
                <span class="ant-user__name">{{ $antUser->realname ?: $antUser->username }}<small>{{ $ant_role ?: $antUser->username }}</small></span>
                <i class="fa-solid fa-chevron-down ant-user__caret" aria-hidden="true"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-right ant-menu">
                <li class="ant-menu__head">{{ $antUser->username }}@if($antUser->email)<small>{{ $antUser->email }}</small>@endif</li>
                <li><a href="{{ route('preferences.index') }}"><i class="fa-solid fa-user-gear fa-fw"></i> Preferensi & 2FA</a></li>
                <li><a href="{{ route('notifications.index') }}"><i class="fa-solid fa-bell fa-fw"></i> Notifikasi</a></li>
                <li class="ant-menu__theme"><a href="#" id="ant-theme-menu"><i class="fa-solid fa-circle-half-stroke fa-fw"></i> Ganti tema terang / gelap</a></li>
                @can('api.access')
                    <li><a href="{{ route('api-access.index') }}"><i class="fa-solid fa-key fa-fw"></i> Akses API</a></li>
                @endcan
                <li id="countdown_timer_menu" class="dropdown-submenu" style="display: none">
                    <a href="#"><i class="fa-solid fa-clock fa-fw"></i> Refresh: <span id="countdown_timer"></span></a>
                    <ul class="dropdown-menu">
                        <li><a href="#" id="countdown_timer_pause"><i class="fa-solid fa-pause fa-fw"></i> Jeda</a></li>
                        <li><a href="#" id="countdown_timer_refresh"><i class="fa-solid fa-arrows-rotate fa-fw"></i> Refresh</a></li>
                    </ul>
                </li>
                <li role="presentation" class="divider" id="countdown_timer_divider" style="display: none"></li>
                <li role="presentation" class="divider"></li>
                <li>
                    <a href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                        <i class="fa-solid fa-right-from-bracket fa-fw"></i> Keluar
                    </a>
                    <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">@csrf</form>
                </li>
            </ul>
        </div>
    </div>
</header>

@include('antareja::shell.search-script')
<script>
    var hideDashboardEditor = {{ (int) $hide_dashboard_editor }};
    function toggleDashboardEditor() {
        $.ajax({
            url: '{{ route('preferences.store') }}', dataType: 'json', type: 'POST',
            data: { pref: 'hide_dashboard_editor', value: hideDashboardEditor ? 0 : 1 },
            success: function () {
                hideDashboardEditor = hideDashboardEditor ? 0 : 1;
                $('#toggle-dashboard-editor-text').text(hideDashboardEditor ? '{{ __('Show Dashboard Editor') }}' : '{{ __('Hide Dashboard Editor') }}');
                $('#dashboard-editor').collapse(hideDashboardEditor ? 'hide' : 'show');
            }
        });
    }
    @if($browser_push)
    if (localStorage.getItem('notifications') !== 'disabled') {
        Notification.requestPermission().then(function (p) { if (p === 'denied') { localStorage.setItem('notifications', 'disabled'); } });
    }
    @endif
    (function () {
        var root = document.documentElement, side = document.getElementById('ant-side'), scrim = document.getElementById('ant-scrim'),
            openBtn = document.getElementById('ant-nav-open');
        function store(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
        function read(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }

        // Sidebar di layar kecil: laci yang bisa dibuka/tutup
        function setDrawer(open, viaKeyboard) {
            root.classList.toggle('ant-nav-open', open);
            scrim.hidden = !open;
            openBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open && viaKeyboard) { var a = side.querySelector('.ant-navlink'); if (a) a.focus(); }
        }
        openBtn.addEventListener('click', function (e) { setDrawer(!root.classList.contains('ant-nav-open'), e.detail === 0); });
        scrim.addEventListener('click', function () { setDrawer(false); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && root.classList.contains('ant-nav-open')) { setDrawer(false); openBtn.focus(); } });

        // Sidebar desktop: lebar penuh / hanya ikon
        document.getElementById('ant-nav-collapse').addEventListener('click', function () {
            var c = root.classList.toggle('ant-nav-collapsed');
            store('ant-nav', c ? 'collapsed' : 'open');
            window.dispatchEvent(new Event('resize')); // grafik & peta LibreNMS menyesuaikan lebar
        });

        // Kelompok menu: buka/tutup, diingat per browser (kelompok yang berisi halaman aktif selalu terbuka)
        var saved = {};
        try { saved = JSON.parse(read('ant-nav-groups') || '{}'); } catch (e) {}
        side.querySelectorAll('.ant-navgroup').forEach(function (g) {
            var btn = g.querySelector('.ant-navgroup__title'), key = g.dataset.group;
            if (!btn) return;
            var hasActive = !!g.querySelector('.is-active');
            if (!hasActive && key in saved) { g.toggleAttribute('data-closed', saved[key] === 0); btn.setAttribute('aria-expanded', saved[key] === 0 ? 'false' : 'true'); }
            btn.addEventListener('click', function () {
                var closed = g.toggleAttribute('data-closed');
                btn.setAttribute('aria-expanded', closed ? 'false' : 'true');
                saved[key] = closed ? 0 : 1; store('ant-nav-groups', JSON.stringify(saved));
            });
        });
        var active = side.querySelector('.is-active');
        if (active) active.scrollIntoView({ block: 'nearest' });

        // Tema: terang ↔ gelap (disimpan sebagai preferensi user LibreNMS "site_style")
        function toggleTheme() {
            var next = root.classList.contains('dark') ? 'light' : 'dark';
            window.siteStylePreference = next;
            if (typeof applySiteStyle === 'function') applySiteStyle(next);
            $.post('{{ route('preferences.store') }}', { pref: 'site_style', value: next });
        }
        document.getElementById('ant-theme').addEventListener('click', toggleTheme);
        document.getElementById('ant-theme-menu').addEventListener('click', function (e) { e.preventDefault(); toggleTheme(); });

        // "/" memfokuskan pencarian global
        window.addEventListener('keydown', function (e) {
            if (e.key !== '/' || e.ctrlKey || e.metaKey || e.altKey) return;
            var el = document.activeElement;
            if (el && (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.tagName === 'SELECT' || el.isContentEditable)) return;
            e.preventDefault(); document.getElementById('gsearch').focus();
        });
    })();
</script>
