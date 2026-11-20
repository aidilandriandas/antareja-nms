@php
    use Illuminate\Support\Facades\Vite;
    $font = fn (string $f) => Vite::asset('packages/antareja/resources/fonts/' . $f);
@endphp
<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">
    <title>NOC · ANTAREJA NMS</title>
    <link rel="icon" type="image/png" href="{{ asset('images/antareja/favicon-32.png') }}">
    <link href="{{ asset('css/fontawesome.min.css') }}" rel="stylesheet">
    <style>
        @font-face { font-family: 'Barlow Semi Condensed'; src: url('{{ $font('BarlowSemiCondensed-Bold.woff2') }}') format('woff2'); font-weight: 700; font-display: swap; }
        @font-face { font-family: 'Barlow Semi Condensed'; src: url('{{ $font('BarlowSemiCondensed-SemiBold.woff2') }}') format('woff2'); font-weight: 600; font-display: swap; }
        @font-face { font-family: 'IBM Plex Sans'; src: url('{{ $font('IBMPlexSans-Variable.woff2') }}') format('woff2'); font-weight: 400 600; font-display: swap; }
        @font-face { font-family: 'IBM Plex Mono'; src: url('{{ $font('IBMPlexMono-Medium.woff2') }}') format('woff2'); font-weight: 500; font-display: swap; }
        @font-face { font-family: 'IBM Plex Mono'; src: url('{{ $font('IBMPlexMono-Regular.woff2') }}') format('woff2'); font-weight: 400; font-display: swap; }
        :root {
            --bg: #06090d; --panel: rgba(13, 19, 26, .86); --line: rgba(110, 160, 190, .16); --line-2: rgba(110, 160, 190, .28);
            --ink: #e6edf3; --muted: #8b98a5; --dim: #5b6772; --ok: #2ee6c5; --ok-2: #1fb89a; --warn: #f5a524; --crit: #ff4d4f; --info: #5ea2ff; --brand: #fe0002;
            --display: "Barlow Semi Condensed", "Arial Narrow", sans-serif; --sans: "IBM Plex Sans", system-ui, sans-serif; --mono: "IBM Plex Mono", ui-monospace, monospace;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; height: 100%; }
        body { background: var(--bg); color: var(--ink); font: 400 14px/1.4 var(--sans); overflow: hidden;
            background-image: linear-gradient(var(--line) 1px, transparent 1px), linear-gradient(90deg, var(--line) 1px, transparent 1px),
                radial-gradient(ellipse at 50% 40%, rgba(46, 230, 197, .06), transparent 65%);
            background-size: 48px 48px, 48px 48px, 100% 100%; }
        body::after { content: ""; position: fixed; inset: 0; pointer-events: none; background: radial-gradient(ellipse at center, transparent 55%, rgba(0,0,0,.55)); }
        .noc { position: relative; z-index: 1; display: grid; height: 100vh; padding: 14px; gap: 12px;
            grid-template-columns: 300px minmax(0, 1fr) 360px; grid-template-rows: 64px minmax(0, 1fr) 132px;
            grid-template-areas: "head head head" "left map right" "foot foot foot"; }
        .panel { position: relative; background: var(--panel); border: 1px solid var(--line-2); border-radius: 6px; backdrop-filter: blur(4px); min-height: 0; }
        /* sudut "bracket" ala layar kendali */
        .panel::before, .panel::after { content: ""; position: absolute; width: 14px; height: 14px; border: 2px solid var(--ok); opacity: .55; pointer-events: none; }
        .panel::before { top: -1px; left: -1px; border-right: 0; border-bottom: 0; border-top-left-radius: 6px; }
        .panel::after { bottom: -1px; right: -1px; border-left: 0; border-top: 0; border-bottom-right-radius: 6px; }
        .panel h2 { margin: 0; padding: 10px 14px; display: flex; align-items: center; justify-content: space-between; gap: 8px;
            font: 600 13px/1 var(--mono); letter-spacing: .14em; text-transform: uppercase; color: var(--muted); border-bottom: 1px solid var(--line); }
        .panel h2 b { color: var(--ink); font-weight: 500; letter-spacing: 0; }

        /* Header */
        .head { grid-area: head; display: flex; align-items: center; gap: 18px; padding: 0 16px; }
        .brand { display: flex; align-items: center; gap: 12px; }
        .brand img { width: 40px; height: 40px; }
        .brand b { font: 700 26px/1 var(--display); letter-spacing: .04em; }
        .brand small { display: block; font: 500 11px/1 var(--mono); letter-spacing: .22em; color: var(--muted); margin-top: 5px; }
        .overall { display: inline-flex; align-items: center; gap: 10px; height: 38px; padding: 0 16px; border-radius: 4px; font: 600 15px var(--display); letter-spacing: .06em; text-transform: uppercase;
            border: 1px solid; }
        .overall.ok { color: var(--ok); border-color: rgba(46,230,197,.45); background: rgba(46,230,197,.08); }
        .overall.bad { color: #fff; border-color: var(--crit); background: rgba(255,77,79,.22); animation: alarm 1.6s ease-in-out infinite; }
        @keyframes alarm { 50% { background: rgba(255,77,79,.42); box-shadow: 0 0 24px rgba(255,77,79,.45); } }
        .overall i { font-size: 16px; }
        .head .spacer { flex: 1; }
        .head select { height: 34px; padding: 0 10px; border-radius: 4px; border: 1px solid var(--line-2); background: #0b1117; color: var(--ink); font: 500 13px var(--mono); }
        .clock { text-align: right; font-family: var(--mono); }
        .clock b { display: block; font: 500 30px/1 var(--mono); letter-spacing: .04em; font-variant-numeric: tabular-nums; }
        .clock small { color: var(--muted); font-size: 12px; letter-spacing: .06em; }
        .iconbtn { width: 36px; height: 36px; border-radius: 4px; border: 1px solid var(--line-2); background: transparent; color: var(--muted); cursor: pointer; font-size: 15px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; }
        .iconbtn:hover { color: var(--ink); border-color: var(--ok); }
        .live { display: inline-flex; align-items: center; gap: 7px; font: 500 12px var(--mono); color: var(--muted); letter-spacing: .1em; }
        .live i { width: 8px; height: 8px; border-radius: 50%; background: var(--ok); box-shadow: 0 0 10px var(--ok); animation: blink 2s infinite; }
        .live.stale i { background: var(--warn); box-shadow: 0 0 10px var(--warn); }
        .live.lost i { background: var(--crit); box-shadow: 0 0 10px var(--crit); animation: none; }
        @keyframes blink { 50% { opacity: .35; } }

        /* Kolom kiri: KPI */
        .left { grid-area: left; display: flex; flex-direction: column; gap: 12px; min-height: 0; overflow: hidden; }
        .kpi { padding: 14px 16px; }
        .kpi .lbl { font: 500 11.5px var(--mono); letter-spacing: .16em; text-transform: uppercase; color: var(--muted); }
        .kpi .val { font: 700 46px/1.05 var(--display); font-variant-numeric: tabular-nums; margin-top: 6px; }
        .kpi .val small { font-size: 22px; color: var(--muted); margin-left: 2px; }
        .kpi .sub { font: 400 12.5px var(--mono); color: var(--muted); margin-top: 6px; }
        .kpi.crit .val { color: var(--crit); text-shadow: 0 0 18px rgba(255,77,79,.45); }
        .kpi.ok .val { color: var(--ok); text-shadow: 0 0 18px rgba(46,230,197,.3); }
        .meter { height: 6px; margin-top: 10px; border-radius: 3px; background: rgba(255,255,255,.07); overflow: hidden; }
        .meter span { display: block; height: 100%; background: var(--ok); box-shadow: 0 0 10px var(--ok); transition: width .6s; }
        .meter.warn span { background: var(--warn); box-shadow: 0 0 10px var(--warn); }
        .meter.crit span { background: var(--crit); box-shadow: 0 0 10px var(--crit); }
        .duo { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .duo .kpi .val { font-size: 36px; }
        .sev { list-style: none; margin: 0; padding: 10px 16px 14px; display: grid; gap: 9px; }
        .sev li { display: grid; grid-template-columns: 78px 1fr 28px; align-items: center; gap: 10px; font: 400 12.5px var(--mono); color: var(--muted); }
        .sev .bar { height: 8px; border-radius: 4px; background: rgba(255,255,255,.06); overflow: hidden; }
        .sev .bar span { display: block; height: 100%; border-radius: 4px; transition: width .6s; }
        .sev b { text-align: right; color: var(--ink); font-weight: 500; }
        .sev b.zero { color: var(--dim); }

        /* Peta */
        .map { grid-area: map; overflow: hidden; }
        .map h2 { position: absolute; top: 0; left: 0; right: 0; z-index: 2; background: linear-gradient(var(--panel), transparent); border-bottom: 0; }
        #noc-map { position: absolute; inset: 0; }
        .legend { position: absolute; left: 12px; bottom: 10px; z-index: 2; display: flex; gap: 14px; flex-wrap: wrap; font: 400 11.5px var(--mono); color: var(--muted); }
        .legend i.l { display: inline-block; width: 20px; height: 3px; border-radius: 2px; vertical-align: middle; margin-right: 4px; }
        .mapstat { position: absolute; right: 12px; bottom: 10px; z-index: 2; font: 400 12px var(--mono); color: var(--muted); }
        .mapstat b { color: var(--ink); font-weight: 500; }
        div.vis-tooltip { font: 400 12.5px/1.45 var(--mono) !important; white-space: pre !important; padding: 8px 10px !important; border-radius: 4px !important;
            background: #0b1117 !important; color: var(--ink) !important; border: 1px solid var(--line-2) !important; box-shadow: 0 8px 24px rgba(0,0,0,.5) !important; }

        /* Kanan: feed */
        .right { grid-area: right; display: flex; flex-direction: column; gap: 12px; min-height: 0; }
        .feed { flex: 1 1 60%; display: flex; flex-direction: column; min-height: 0; }
        .downs { flex: 1 1 40%; display: flex; flex-direction: column; min-height: 0; }
        .list { list-style: none; margin: 0; padding: 4px 0; overflow-y: auto; flex: 1; scrollbar-width: thin; scrollbar-color: #26323d transparent; }
        .list li a, .list li > div { display: grid; grid-template-columns: 4px 1fr auto; gap: 10px; padding: 9px 14px; color: var(--ink); text-decoration: none; align-items: center; }
        .list li a:hover { background: rgba(255,255,255,.03); }
        .list li + li { border-top: 1px solid var(--line); }
        .list .sbar { align-self: stretch; border-radius: 2px; }
        .list .t { font-weight: 500; font-size: 13.5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .list .m { font: 400 11.5px var(--mono); color: var(--muted); margin-top: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .list .age { font: 500 13px var(--mono); color: var(--muted); text-align: right; font-variant-numeric: tabular-nums; }
        .list .age small { display: block; font-size: 10.5px; letter-spacing: .08em; }
        .list .age small.nack { color: var(--warn); }
        .list li.new { animation: flash 2.4s ease-out; }
        @keyframes flash { 0% { background: rgba(255,77,79,.35); } 100% { background: transparent; } }
        .empty { padding: 26px 16px; text-align: center; color: var(--muted); font: 400 13px var(--mono); }
        .empty i { display: block; font-size: 26px; color: var(--ok); margin-bottom: 10px; text-shadow: 0 0 14px rgba(46,230,197,.5); }

        /* Bawah: site & grup */
        .foot { grid-area: foot; display: grid; grid-template-columns: 1.4fr 1fr; gap: 12px; min-height: 0; }
        .tiles { display: flex; gap: 10px; padding: 12px 14px; overflow-x: auto; scrollbar-width: thin; scrollbar-color: #26323d transparent; }
        .tile { flex: 0 0 auto; min-width: 150px; padding: 10px 12px; border-radius: 4px; border: 1px solid var(--line); background: rgba(255,255,255,.02); }
        .tile.bad { border-color: rgba(255,77,79,.6); background: rgba(255,77,79,.08); box-shadow: inset 3px 0 0 var(--crit); }
        .tile .n { font: 600 15px var(--display); letter-spacing: .02em; white-space: nowrap; }
        .tile .n i { color: var(--muted); margin-right: 6px; font-size: 13px; }
        .tile .c { font: 500 20px var(--mono); margin-top: 4px; font-variant-numeric: tabular-nums; }
        .tile .c small { font-size: 12px; color: var(--muted); }
        .tile .c .dn { color: var(--crit); }
        .tile .stack { display: flex; gap: 2px; height: 5px; margin-top: 7px; }
        .tile .stack span { border-radius: 2px; }
        .foot .panel { display: flex; flex-direction: column; min-height: 0; }

        .overlay { position: fixed; inset: 0; z-index: 10; display: flex; align-items: center; justify-content: center; background: rgba(6,9,13,.85); }
        .overlay[hidden] { display: none; }
        .overlay div { text-align: center; font: 400 15px var(--mono); color: var(--muted); }
        .overlay b { display: block; font: 700 28px var(--display); color: var(--ink); margin-bottom: 8px; }
        .overlay a { color: var(--ok); }

        @media (max-width: 1300px) { .noc { grid-template-columns: 250px minmax(0,1fr) 300px; } .kpi .val { font-size: 38px; } }
        @media (max-width: 1000px) {
            body { overflow: auto; }
            .noc { height: auto; grid-template-columns: 1fr; grid-template-rows: auto; grid-template-areas: "head" "left" "map" "right" "foot"; }
            .map { height: 70vh; } .foot { grid-template-columns: 1fr; } .head { flex-wrap: wrap; padding: 10px 0; }
        }
        @media (prefers-reduced-motion: reduce) { .overall.bad, .live i, .list li.new { animation: none; } }
    </style>
</head>
<body>
<div class="noc">
    <header class="head panel">
        <div class="brand">
            <img src="{{ asset('images/antareja/antareja-mark-128.png') }}" alt="">
            <div><b>ANTAREJA NOC</b><small>NETWORK OPERATIONS CENTER</small></div>
        </div>
        <div class="overall ok" id="overall" role="status" aria-live="polite"><i class="fa-solid fa-shield-halved"></i><span>Memuat…</span></div>
        <span class="spacer"></span>
        <span class="live" id="live" title="Diperbarui otomatis tiap 15 detik"><i></i><span id="live-text">LIVE</span></span>
        <select id="site" aria-label="Filter site">
            <option value="">SEMUA SITE</option>
            @foreach($sites as $s)<option value="{{ $s->id }}">{{ $s->code }} — {{ $s->name }}</option>@endforeach
        </select>
        <div class="clock"><b id="clock">--:--:--</b><small id="date"></small></div>
        <button type="button" class="iconbtn" id="fs" title="Layar penuh (F)"><i class="fa-solid fa-expand"></i></button>
        <a href="{{ route('antareja.home') }}" class="iconbtn" title="Kembali ke aplikasi"><i class="fa-solid fa-right-from-bracket"></i></a>
    </header>

    <section class="left">
        <div class="panel kpi" id="k-avail"><div class="lbl">Ketersediaan</div><div class="val">–<small>%</small></div><div class="meter"><span style="width:0"></span></div><div class="sub"></div></div>
        <div class="duo">
            <div class="panel kpi ok" id="k-up"><div class="lbl">Online</div><div class="val">–</div></div>
            <div class="panel kpi" id="k-down"><div class="lbl">Down</div><div class="val">–</div></div>
        </div>
        <div class="panel" id="k-sev-wrap">
            <h2>Problem aktif <b id="k-prob">–</b></h2>
            <ul class="sev" id="k-sev"></ul>
        </div>
        <div class="duo">
            <div class="panel kpi" id="k-inc"><div class="lbl">Insiden</div><div class="val">–</div></div>
            <div class="panel kpi" id="k-cctv"><div class="lbl">CCTV OK</div><div class="val">–</div></div>
        </div>
    </section>

    <section class="panel map">
        <h2>Topologi jaringan <span id="map-note"></span></h2>
        <div id="noc-map" aria-label="Peta topologi"></div>
        <div class="legend" aria-hidden="true">
            <span><i class="l" style="background:var(--ok)"></i>Normal</span><span><i class="l" style="background:var(--warn)"></i>&gt;70%</span>
            <span><i class="l" style="background:var(--crit)"></i>&gt;90% / putus</span><span><i class="fa-solid fa-circle" style="color:var(--crit)"></i> Perangkat down</span>
        </div>
        <div class="mapstat" id="mapstat"></div>
    </section>

    <section class="right">
        <div class="panel feed"><h2>Live problem <b id="feed-n"></b></h2><ul class="list" id="feed" aria-live="polite"></ul></div>
        <div class="panel downs"><h2>Perangkat down <b id="down-n"></b></h2><ul class="list" id="downs"></ul></div>
    </section>

    <section class="foot">
        <div class="panel"><h2>Status per site</h2><div class="tiles" id="sites"></div></div>
        <div class="panel"><h2>Status per grup</h2><div class="tiles" id="groups"></div></div>
    </section>
</div>
<div class="overlay" id="overlay" hidden><div><b>Sesi berakhir</b>Login ulang untuk melanjutkan tampilan NOC.<br><br><a href="{{ route('login') }}">Login</a></div></div>

@include('antareja::topology.graph-script')
<script>
(function () {
    var $ = function (id) { return document.getElementById(id); };
    var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };
    var site = $('site'), seen = null, lastOk = Date.now(), serverOffset = 0;
    var COLORS = { info: 'var(--info)', warn: 'var(--warn)', crit: 'var(--crit)', down: 'var(--crit)', ok: 'var(--ok)', unknown: 'var(--dim)' };
    try { site.value = localStorage.getItem('ant-noc-site') || ''; } catch (e) {}

    function ago(iso) {
        var s = Math.max(0, Math.floor((Date.now() + serverOffset - new Date(iso).getTime()) / 1000));
        if (s < 60) return s + 'd'; if (s < 3600) return Math.floor(s / 60) + 'm';
        if (s < 86400) return Math.floor(s / 3600) + 'j ' + Math.floor(s % 3600 / 60) + 'm'; return Math.floor(s / 86400) + 'h ' + Math.floor(s % 86400 / 3600) + 'j';
    }
    function tick() {
        var now = new Date(Date.now() + serverOffset);
        $('clock').textContent = now.toLocaleTimeString('id-ID', { hour12: false });
        $('date').textContent = now.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).toUpperCase();
        var age = (Date.now() - lastOk) / 1000, live = $('live');
        live.className = 'live' + (age > 90 ? ' lost' : (age > 40 ? ' stale' : ''));
        $('live-text').textContent = age > 90 ? 'TERPUTUS' : (age > 40 ? 'TERTUNDA' : 'LIVE');
        document.querySelectorAll('[data-since]').forEach(function (el) { el.firstChild.textContent = ago(el.dataset.since); });
    }
    setInterval(tick, 1000);

    function render(d) {
        serverOffset = new Date(d.time).getTime() - Date.now();
        var dev = d.devices, probs = d.problems || [], bad = dev.down > 0 || probs.some(function (p) { return p.severity.rank >= 4; });
        var ov = $('overall');
        ov.className = 'overall ' + (bad ? 'bad' : 'ok');
        ov.innerHTML = bad ? '<i class="fa-solid fa-triangle-exclamation"></i><span>Gangguan · ' + dev.down + ' down' + (probs.length ? ' · ' + probs.length + ' problem' : '') + '</span>'
            : '<i class="fa-solid fa-shield-halved"></i><span>Semua sistem normal</span>';
        document.title = (bad ? '⚠ ' + dev.down + ' down · ' : '') + 'NOC · ANTAREJA NMS';

        var av = dev.availability, k = $('k-avail');
        k.querySelector('.val').innerHTML = (av === null ? '–' : av.toFixed(av === 100 ? 0 : 2).replace('.', ',')) + '<small>%</small>';
        k.querySelector('.meter').className = 'meter ' + (av === null ? '' : (av >= 99 ? '' : (av >= 95 ? 'warn' : 'crit')));
        k.querySelector('.meter span').style.width = (av || 0) + '%';
        k.querySelector('.sub').textContent = dev.up + ' / ' + (dev.up + dev.down) + ' perangkat dipantau' + (dev.off ? ' · ' + dev.off + ' nonaktif' : '');
        $('k-up').querySelector('.val').textContent = dev.up;
        $('k-down').querySelector('.val').textContent = dev.down;
        $('k-down').className = 'panel kpi ' + (dev.down ? 'crit' : 'ok');

        if (d.severity) {
            var max = Math.max.apply(null, d.severity.map(function (s) { return s.n; }).concat([1]));
            $('k-prob').textContent = probs.length >= 14 ? '14+' : d.severity.reduce(function (a, s) { return a + s.n; }, 0);
            $('k-sev').innerHTML = d.severity.map(function (s) {
                return '<li><span>' + esc(s.label) + '</span><span class="bar"><span style="width:' + (s.n ? Math.max(5, s.n / max * 100) : 0) + '%;background:' + COLORS[s.tone] + '"></span></span><b class="' + (s.n ? '' : 'zero') + '">' + s.n + '</b></li>';
            }).join('');
        } else { $('k-sev-wrap').hidden = true; }
        var inc = $('k-inc');
        if (d.incidents === null) inc.hidden = true; else { inc.querySelector('.val').textContent = d.incidents; inc.className = 'panel kpi ' + (d.incidents ? 'crit' : ''); }
        var cc = $('k-cctv');
        if (!d.cctv || !d.cctv.total) cc.hidden = true; else { cc.querySelector('.val').innerHTML = d.cctv.ok + '<small>/' + d.cctv.total + '</small>'; cc.className = 'panel kpi ' + (d.cctv.ok < d.cctv.total ? 'crit' : 'ok'); }

        // Feed problem: problem baru berkedip merah
        var ids = probs.map(function (p) { return p.id; });
        $('feed-n').textContent = probs.length || '';
        $('feed').innerHTML = probs.length ? probs.map(function (p) {
            var isNew = seen && seen.indexOf(p.id) < 0;
            return '<li class="' + (isNew ? 'new' : '') + '"><a href="' + p.url + '" target="_blank" rel="noopener"><span class="sbar" style="background:' + COLORS[p.severity.tone] + '"></span>'
                + '<span style="min-width:0"><div class="t">' + esc(p.title) + '</div><div class="m">' + esc(p.severity.label.toUpperCase()) + (p.device ? ' · ' + esc(p.device) : '') + (p.site ? ' · ' + esc(p.site) : '') + '</div></span>'
                + '<span class="age" data-since="' + p.since + '">' + ago(p.since) + '<small class="' + (p.ack ? '' : 'nack') + '">' + (p.ack ? 'ACK' : 'BELUM ACK') + '</small></span></a></li>';
        }).join('') : '<li><div class="empty" style="display:block"><i class="fa-solid fa-circle-check"></i>TIDAK ADA PROBLEM AKTIF</div></li>';
        seen = ids;

        $('down-n').textContent = dev.down || '';
        $('downs').innerHTML = d.down_list.length ? d.down_list.map(function (x) {
            return '<li><a href="{{ url('device') }}/' + x.id + '" target="_blank" rel="noopener"><span class="sbar" style="background:var(--crit)"></span><span style="min-width:0"><div class="t">' + esc(x.name) + '</div>'
                + '<div class="m">' + esc(x.ip) + (x.group ? ' · ' + esc(x.group) : '') + (x.site ? ' · ' + esc(x.site) : '') + '</div></span><span class="age" style="color:var(--crit)">DOWN</span></a></li>';
        }).join('') : '<li><div class="empty" style="display:block"><i class="fa-solid fa-circle-check"></i>SEMUA PERANGKAT ONLINE</div></li>';

        function tile(name, icon, up, down, total) {
            var off = Math.max(0, total - up - down);
            return '<div class="tile' + (down ? ' bad' : '') + '"><div class="n">' + (icon ? '<i class="fa-solid fa-' + icon + '"></i>' : '') + esc(name) + '</div>'
                + '<div class="c">' + (down ? '<span class="dn">' + down + '↓</span> ' : '') + up + '<small>/' + total + '</small></div>'
                + '<div class="stack">' + (up ? '<span style="flex:' + up + ';background:var(--ok)"></span>' : '') + (down ? '<span style="flex:' + down + ';background:var(--crit)"></span>' : '') + (off ? '<span style="flex:' + off + ';background:var(--dim)"></span>' : '') + '</div></div>';
        }
        $('sites').innerHTML = d.sites.length ? d.sites.filter(function (s) { return s.total > 0; }).map(function (s) { return tile(s.code + ' · ' + s.name, 'location-dot', s.up, s.down, s.total); }).join('') || '<div class="empty">Belum ada perangkat ber-site</div>' : '<div class="empty">Belum ada site</div>';
        $('groups').innerHTML = d.groups.length ? d.groups.map(function (g) { return tile(g.name, g.icon, g.up, g.down, g.total); }).join('') : '<div class="empty">Belum ada grup</div>';

        var s = d.graph.stats;
        $('mapstat').innerHTML = '<b>' + s.nodes + '</b> node · <b>' + s.edges + '</b> link' + (s.down ? ' · <b style="color:var(--crit)">' + s.down + ' down</b>' : '');
        $('map-note').textContent = s.edges ? '' : 'BELUM ADA SAMBUNGAN — AKTIFKAN LLDP/CDP ATAU BUAT MAPPING';
    }

    var topo = AntTopology.create($('noc-map'), {
        theme: 'dark', flow: true, readonly: true,
        dataUrl: function () { return '{{ route('antareja.noc.data') }}?site_id=' + site.value; },
        onData: function (d) { lastOk = Date.now(); render(d); },
        onSelect: function (n) { if (n) window.open(n.url, '_blank', 'noopener'); }
    });
    function refresh() {
        topo.load().catch(function (e) { if (String(e).indexOf('401') >= 0 || String(e).indexOf('419') >= 0) $('overlay').hidden = false; });
    }
    refresh(); topo.every(15); tick();
    site.addEventListener('change', function () { try { localStorage.setItem('ant-noc-site', site.value); } catch (e) {} seen = null; topo.reload().catch(function () {}); });

    function fs() { if (!document.fullscreenElement) document.documentElement.requestFullscreen().catch(function () {}); else document.exitFullscreen(); }
    $('fs').addEventListener('click', fs);
    document.addEventListener('keydown', function (e) { if ((e.key === 'f' || e.key === 'F') && e.target === document.body) fs(); });
})();
</script>
</body>
</html>
