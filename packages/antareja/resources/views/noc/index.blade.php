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
            --bg: #06090d; --panel: rgba(13, 19, 26, .88); --line: rgba(110, 160, 190, .14); --line-2: rgba(110, 160, 190, .28);
            --ink: #e6edf3; --muted: #8b98a5; --dim: #5b6772; --ok: #2ee6c5; --warn: #f5a524; --orange: #ff8a3d; --crit: #ff4d4f; --info: #5ea2ff;
            --display: "Barlow Semi Condensed", "Arial Narrow", sans-serif; --sans: "IBM Plex Sans", system-ui, sans-serif; --mono: "IBM Plex Mono", ui-monospace, monospace;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; height: 100%; }
        body { background: var(--bg); color: var(--ink); font: 400 14px/1.4 var(--sans); overflow: hidden;
            background-image: linear-gradient(var(--line) 1px, transparent 1px), linear-gradient(90deg, var(--line) 1px, transparent 1px),
                radial-gradient(ellipse at 50% 40%, rgba(46, 230, 197, .05), transparent 65%);
            background-size: 48px 48px, 48px 48px, 100% 100%; }
        body::after { content: ""; position: fixed; inset: 0; pointer-events: none; background: radial-gradient(ellipse at center, transparent 55%, rgba(0,0,0,.55)); }
        .noc { position: relative; z-index: 1; display: grid; height: 100vh; padding: 12px; gap: 10px;
            grid-template-columns: 300px minmax(0, 1fr) 380px; grid-template-rows: 60px minmax(0, 1fr) 150px;
            grid-template-areas: "head head head" "left center right" "foot foot foot"; }
        .panel { position: relative; background: var(--panel); border: 1px solid var(--line-2); border-radius: 6px; min-height: 0; display: flex; flex-direction: column; }
        .panel::before, .panel::after { content: ""; position: absolute; width: 12px; height: 12px; border: 2px solid var(--ok); opacity: .5; pointer-events: none; }
        .panel::before { top: -1px; left: -1px; border-right: 0; border-bottom: 0; border-top-left-radius: 6px; }
        .panel::after { bottom: -1px; right: -1px; border-left: 0; border-top: 0; border-bottom-right-radius: 6px; }
        .panel h2 { margin: 0; padding: 8px 12px; display: flex; align-items: center; justify-content: space-between; gap: 8px; flex: none;
            font: 600 12px/1.2 var(--mono); letter-spacing: .14em; text-transform: uppercase; color: var(--muted); border-bottom: 1px solid var(--line); }
        .panel h2 b { color: var(--ink); font-weight: 500; letter-spacing: 0; }
        .scroll { overflow-y: auto; flex: 1; min-height: 0; scrollbar-width: thin; scrollbar-color: #26323d transparent; }

        /* Header */
        .head { grid-area: head; flex-direction: row; align-items: center; gap: 14px; padding: 0 14px; }
        .brand { display: flex; align-items: center; gap: 10px; }
        .brand img { width: 36px; height: 36px; }
        .brand b { font: 700 24px/1 var(--display); letter-spacing: .04em; }
        .brand small { display: block; font: 500 10.5px/1 var(--mono); letter-spacing: .22em; color: var(--muted); margin-top: 4px; }
        .overall { display: inline-flex; align-items: center; gap: 10px; height: 36px; padding: 0 14px; border-radius: 4px; border: 1px solid;
            font: 600 15px var(--display); letter-spacing: .05em; text-transform: uppercase; white-space: nowrap; max-width: 46vw; overflow: hidden; text-overflow: ellipsis; }
        .overall.ok { color: var(--ok); border-color: rgba(46,230,197,.45); background: rgba(46,230,197,.08); }
        .overall.warn { color: var(--warn); border-color: rgba(245,165,36,.55); background: rgba(245,165,36,.10); }
        .overall.crit { color: #fff; border-color: var(--crit); background: rgba(255,77,79,.24); animation: alarm 1.4s ease-in-out infinite; }
        @keyframes alarm { 50% { background: rgba(255,77,79,.45); box-shadow: 0 0 26px rgba(255,77,79,.5); } }
        .spacer { flex: 1; }
        .head select { height: 32px; padding: 0 8px; border-radius: 4px; border: 1px solid var(--line-2); background: #0b1117; color: var(--ink); font: 500 12.5px var(--mono); }
        .clock { text-align: right; font-family: var(--mono); }
        .clock b { display: block; font: 500 28px/1 var(--mono); letter-spacing: .04em; font-variant-numeric: tabular-nums; }
        .clock small { color: var(--muted); font-size: 11px; letter-spacing: .06em; }
        .iconbtn { height: 34px; min-width: 34px; padding: 0 8px; border-radius: 4px; border: 1px solid var(--line-2); background: transparent; color: var(--muted);
            cursor: pointer; font: 500 12px var(--mono); display: inline-flex; align-items: center; justify-content: center; gap: 6px; text-decoration: none; }
        .iconbtn:hover { color: var(--ink); border-color: var(--ok); }
        .iconbtn.on { color: var(--ok); border-color: rgba(46,230,197,.5); }
        .iconbtn.hint { color: var(--warn); border-color: rgba(245,165,36,.6); }
        .live { display: inline-flex; align-items: center; gap: 7px; font: 500 11.5px var(--mono); color: var(--muted); letter-spacing: .1em; }
        .live i { width: 8px; height: 8px; border-radius: 50%; background: var(--ok); box-shadow: 0 0 10px var(--ok); animation: blink 2s infinite; }
        .live.stale i { background: var(--warn); box-shadow: 0 0 10px var(--warn); }
        .live.lost i { background: var(--crit); box-shadow: 0 0 10px var(--crit); animation: none; }
        @keyframes blink { 50% { opacity: .35; } }

        /* Kiri */
        .left { grid-area: left; display: flex; flex-direction: column; gap: 10px; min-height: 0; }
        .kpi { padding: 12px 14px; }
        .kpi .lbl { font: 500 11px var(--mono); letter-spacing: .16em; text-transform: uppercase; color: var(--muted); }
        .kpi .val { font: 700 42px/1.05 var(--display); font-variant-numeric: tabular-nums; margin-top: 4px; }
        .kpi .val small { font-size: 20px; color: var(--muted); margin-left: 2px; }
        .kpi .sub { font: 400 12px var(--mono); color: var(--muted); margin-top: 4px; }
        .kpi.crit .val { color: var(--crit); text-shadow: 0 0 18px rgba(255,77,79,.45); }
        .kpi.ok .val { color: var(--ok); }
        .meter { height: 6px; margin-top: 8px; border-radius: 3px; background: rgba(255,255,255,.07); overflow: hidden; }
        .meter span { display: block; height: 100%; background: var(--ok); box-shadow: 0 0 10px var(--ok); transition: width .6s; }
        .meter.warn span { background: var(--warn); } .meter.crit span { background: var(--crit); }
        .duo { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .duo .kpi .val { font-size: 34px; }
        .sev { list-style: none; margin: 0; padding: 8px 14px 10px; display: grid; gap: 7px; }
        .sev li { display: grid; grid-template-columns: 76px 1fr 26px; align-items: center; gap: 8px; font: 400 12px var(--mono); color: var(--muted); }
        .bar { height: 7px; border-radius: 4px; background: rgba(255,255,255,.06); overflow: hidden; }
        .bar span { display: block; height: 100%; border-radius: 4px; transition: width .6s; }
        .sev b { text-align: right; color: var(--ink); font-weight: 500; } .sev b.zero { color: var(--dim); }
        .chips { display: flex; gap: 6px; font: 500 11px var(--mono); }
        .chips span { padding: 1px 6px; border-radius: 3px; background: rgba(255,255,255,.05); color: var(--muted); letter-spacing: 0; }
        .chips span.bad { color: var(--crit); background: rgba(255,77,79,.12); }
        .lat { flex: 1; }
        .lat li { display: grid; grid-template-columns: 1fr 72px 62px; gap: 8px; align-items: center; padding: 6px 12px; }
        .lat .n { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 13px; }
        .lat .n small { display: block; font: 400 10.5px var(--mono); color: var(--muted); }
        .lat .v { text-align: right; font: 500 12.5px var(--mono); font-variant-numeric: tabular-nums; }
        .lat .v small { display: block; font-size: 10.5px; color: var(--muted); }
        .lat svg { width: 72px; height: 24px; }
        .t-ok { color: var(--ok); } .t-warn { color: var(--warn); } .t-crit { color: var(--crit); } .t-dim { color: var(--dim); }

        /* Tengah */
        .center { grid-area: center; overflow: hidden; }
        .center h2 { position: relative; z-index: 3; background: var(--panel); }
        .tabs { display: inline-flex; gap: 2px; padding: 2px; border-radius: 4px; background: rgba(255,255,255,.04); }
        .tabs button { height: 24px; padding: 0 10px; border: 0; border-radius: 3px; background: none; color: var(--muted); font: 600 11px var(--mono); letter-spacing: .08em; cursor: pointer; }
        .tabs button.on { background: rgba(46,230,197,.14); color: var(--ok); }
        .views { position: relative; flex: 1; min-height: 0; }
        .view { position: absolute; inset: 0; transition: opacity .5s; }
        .view.hidden { opacity: 0; visibility: hidden; }
        #noc-map { position: absolute; inset: 0; }
        .legend { position: absolute; left: 12px; bottom: 8px; z-index: 2; display: flex; gap: 12px; flex-wrap: wrap; font: 400 11px var(--mono); color: var(--muted); }
        .legend i.l { display: inline-block; width: 18px; height: 3px; border-radius: 2px; vertical-align: middle; margin-right: 4px; }
        .legend i.s { display: inline-block; width: 10px; height: 10px; border-radius: 2px; vertical-align: middle; margin-right: 4px; }
        .mapstat { position: absolute; right: 12px; bottom: 8px; z-index: 2; font: 400 11.5px var(--mono); color: var(--muted); }
        .mapstat b { color: var(--ink); font-weight: 500; }
        div.vis-tooltip { font: 400 12px/1.45 var(--mono) !important; white-space: pre !important; padding: 8px 10px !important; border-radius: 4px !important;
            background: #0b1117 !important; color: var(--ink) !important; border: 1px solid var(--line-2) !important; }
        /* Dinding status */
        .wall { position: absolute; inset: 0; overflow-y: auto; padding: 10px 12px 34px; scrollbar-width: thin; scrollbar-color: #26323d transparent; }
        .wsec { margin-bottom: 12px; }
        .wsec h3 { margin: 0 0 6px; display: flex; gap: 10px; align-items: baseline; font: 600 13px var(--display); letter-spacing: .06em; text-transform: uppercase; color: var(--muted); }
        .wsec h3 b { color: var(--ink); } .wsec h3 em { font: 400 11px var(--mono); font-style: normal; }
        .tiles { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 6px; }
        .wall.dense .tiles { grid-template-columns: repeat(auto-fill, minmax(112px, 1fr)); gap: 4px; }
        .tile { position: relative; display: block; padding: 6px 8px 6px 11px; border-radius: 4px; background: rgba(46,230,197,.06); border: 1px solid rgba(46,230,197,.18);
            color: var(--ink); text-decoration: none; overflow: hidden; }
        .tile::before { content: ""; position: absolute; left: 0; top: 0; bottom: 0; width: 3px; background: var(--ok); }
        .tile .tn { display: block; font-size: 12.5px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .tile .ts { display: block; font: 400 10.5px var(--mono); color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .wall.dense .tile .tn { font-size: 11.5px; } .wall.dense .tile .ts { display: none; }
        .tile.slow { background: rgba(245,165,36,.10); border-color: rgba(245,165,36,.45); } .tile.slow::before { background: var(--warn); }
        .tile.noping { background: rgba(255,138,61,.16); border-color: rgba(255,138,61,.7); } .tile.noping::before { background: var(--orange); }
        .tile.down { background: rgba(255,77,79,.22); border-color: var(--crit); animation: tilepulse 1.4s ease-in-out infinite; } .tile.down::before { background: var(--crit); }
        .tile.off { opacity: .45; } .tile.off::before { background: var(--dim); }
        @keyframes tilepulse { 50% { background: rgba(255,77,79,.42); box-shadow: 0 0 14px rgba(255,77,79,.45); } }

        /* Kanan */
        .right { grid-area: right; display: flex; flex-direction: column; gap: 10px; min-height: 0; }
        .right .panel { flex: 1 1 0; }
        .right .panel.small { flex: 0 1 auto; max-height: 24%; }
        .list { list-style: none; margin: 0; padding: 2px 0; }
        .list li + li { border-top: 1px solid var(--line); }
        .list a, .list .row { display: grid; grid-template-columns: 4px 1fr auto; gap: 9px; padding: 7px 12px; color: var(--ink); text-decoration: none; align-items: center; }
        .list a:hover { background: rgba(255,255,255,.03); }
        .list .sbar { align-self: stretch; border-radius: 2px; }
        .list .t { font-weight: 500; font-size: 13px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .list .m { font: 400 11px var(--mono); color: var(--muted); margin-top: 1px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .list .age { font: 500 12px var(--mono); color: var(--muted); text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .list .age small { display: block; font-size: 10px; letter-spacing: .08em; }
        .list .age small.nack { color: var(--warn); }
        .list li.dim { opacity: .5; }
        .list li.new { animation: flash 2.4s ease-out; }
        .list .tag { font: 600 9.5px var(--mono); letter-spacing: .08em; padding: 1px 5px; border-radius: 3px; margin-right: 6px; vertical-align: 1px; }
        .tag.new { background: var(--crit); color: #fff; } .tag.old { background: rgba(255,255,255,.08); color: var(--muted); }
        @keyframes flash { 0% { background: rgba(255,77,79,.35); } 100% { background: transparent; } }
        .ev li { display: grid; grid-template-columns: 22px 1fr auto; gap: 8px; align-items: center; padding: 6px 12px; }
        .ev .ic { width: 22px; height: 22px; border-radius: 4px; display: inline-flex; align-items: center; justify-content: center; font-size: 11px; }
        .ev .ic.down { background: rgba(255,77,79,.18); color: var(--crit); } .ev .ic.up { background: rgba(46,230,197,.14); color: var(--ok); }
        .ev .ic.warn { background: rgba(245,165,36,.16); color: var(--warn); }
        .ev .msg { font-size: 12.5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .ev .msg small { display: block; font: 400 10.5px var(--mono); color: var(--muted); }
        .ev .tm { font: 500 11.5px var(--mono); color: var(--muted); text-align: right; }
        .empty { padding: 18px 14px; text-align: center; color: var(--muted); font: 400 12.5px var(--mono); }
        .empty i { display: block; font-size: 22px; color: var(--ok); margin-bottom: 8px; text-shadow: 0 0 14px rgba(46,230,197,.5); }

        /* Bawah */
        .foot { grid-area: foot; display: grid; grid-template-columns: 1.1fr 1fr 1.1fr; gap: 10px; min-height: 0; }
        .tilesrow { display: flex; gap: 8px; padding: 10px 12px; overflow-x: auto; scrollbar-width: thin; scrollbar-color: #26323d transparent; }
        .stile { flex: 0 0 auto; min-width: 136px; padding: 8px 10px; border-radius: 4px; border: 1px solid var(--line); background: rgba(255,255,255,.02); }
        .stile.bad { border-color: rgba(255,77,79,.6); background: rgba(255,77,79,.08); box-shadow: inset 3px 0 0 var(--crit); }
        .stile .n { font: 600 14px var(--display); letter-spacing: .02em; white-space: nowrap; }
        .stile .n i { color: var(--muted); margin-right: 6px; font-size: 12px; }
        .stile .c { font: 500 18px var(--mono); margin-top: 2px; font-variant-numeric: tabular-nums; }
        .stile .c small { font-size: 11px; color: var(--muted); } .stile .c .dn { color: var(--crit); }
        .stile .stack { display: flex; gap: 2px; height: 4px; margin-top: 6px; } .stile .stack span { border-radius: 2px; }
        .up li { padding: 5px 12px; }
        .up .r1 { display: flex; justify-content: space-between; gap: 8px; font-size: 12px; }
        .up .r1 span:first-child { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .up .r1 b { font: 500 12px var(--mono); }
        .up .bar { margin-top: 3px; height: 5px; }
        .up .r2 { font: 400 10.5px var(--mono); color: var(--muted); margin-top: 2px; }

        .overlay { position: fixed; inset: 0; z-index: 10; display: flex; align-items: center; justify-content: center; background: rgba(6,9,13,.85); }
        .overlay[hidden] { display: none; }
        .overlay div { text-align: center; font: 400 15px var(--mono); color: var(--muted); }
        .overlay b { display: block; font: 700 28px var(--display); color: var(--ink); margin-bottom: 8px; }
        .overlay a { color: var(--ok); }

        @media (max-width: 1400px) { .noc { grid-template-columns: 260px minmax(0,1fr) 320px; } .kpi .val { font-size: 36px; } }
        @media (max-width: 1000px) {
            body { overflow: auto; }
            .noc { height: auto; grid-template-columns: 1fr; grid-template-rows: auto; grid-template-areas: "head" "left" "center" "right" "foot"; }
            .center { height: 70vh; } .right .panel { min-height: 260px; } .foot { grid-template-columns: 1fr; } .head { flex-wrap: wrap; padding: 10px; height: auto; }
        }
        @media (prefers-reduced-motion: reduce) { .overall.crit, .live i, .list li.new, .tile.down { animation: none; } }
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
        <span class="live" id="live" title="Data diperbarui tiap 15 detik; ping cepat ke perangkat tiap 1 menit"><i></i><span id="live-text">LIVE</span></span>
        <button type="button" class="iconbtn" id="sound" title="Bunyi alarm saat ada perangkat down / problem High baru"><i class="fa-solid fa-volume-xmark"></i><span>SUARA MATI</span></button>
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
            <div class="panel kpi" id="k-down"><div class="lbl">Down</div><div class="val">–</div><div class="sub" id="k-down-sub"></div></div>
        </div>
        <div class="panel" id="k-sev-wrap">
            <h2>Problem <span class="chips" id="k-chips"></span></h2>
            <ul class="sev" id="k-sev"></ul>
        </div>
        <div class="panel lat">
            <h2>Latensi &amp; loss tertinggi <b id="lat-n"></b></h2>
            <ul class="list scroll" id="lat"></ul>
        </div>
    </section>

    <section class="panel center">
        <h2>
            <span id="view-title">Dinding status</span>
            <span class="tabs" role="tablist">
                <button type="button" data-view="wall">Dinding</button>
                <button type="button" data-view="map">Topologi</button>
                <button type="button" data-view="auto" title="Bergantian tiap 30 detik">Otomatis ↻</button>
            </span>
        </h2>
        <div class="views">
            <div class="view" id="v-wall">
                <div class="wall" id="wall"></div>
                <div class="legend" aria-hidden="true">
                    <span><i class="s" style="background:var(--ok)"></i>Normal</span><span><i class="s" style="background:var(--warn)"></i>Lambat / loss</span>
                    <span><i class="s" style="background:var(--orange)"></i>Tidak menjawab ping</span><span><i class="s" style="background:var(--crit)"></i>Down</span>
                    <span><i class="s" style="background:var(--dim)"></i>Nonaktif</span>
                </div>
            </div>
            <div class="view hidden" id="v-map">
                <div id="noc-map" aria-label="Peta topologi"></div>
                <div class="legend" aria-hidden="true">
                    <span><i class="l" style="background:var(--ok)"></i>Normal</span><span><i class="l" style="background:var(--warn)"></i>&gt;70%</span>
                    <span><i class="l" style="background:var(--crit)"></i>&gt;90% / putus</span><span><i class="fa-solid fa-circle" style="color:var(--crit)"></i> Perangkat down</span>
                </div>
                <div class="mapstat" id="mapstat"></div>
            </div>
        </div>
    </section>

    <section class="right">
        <div class="panel"><h2>Live problem <b id="feed-n"></b></h2><ul class="list scroll" id="feed" aria-live="polite"></ul></div>
        <div class="panel"><h2>Kejadian 1 jam <b id="ev-n"></b></h2><ul class="list ev scroll" id="events"></ul></div>
        <div class="panel small"><h2>Perangkat down <b id="down-n"></b></h2><ul class="list scroll" id="downs"></ul></div>
    </section>

    <section class="foot">
        <div class="panel"><h2>Status per site</h2><div class="tilesrow" id="sites"></div></div>
        <div class="panel"><h2>Status per grup</h2><div class="tilesrow" id="groups"></div></div>
        <div class="panel"><h2>Uplink tersibuk</h2><ul class="list up scroll" id="uplinks"></ul></div>
    </section>
</div>
<div class="overlay" id="overlay" hidden><div><b>Sesi berakhir</b>Login ulang (centang "Ingat saya") untuk melanjutkan tampilan NOC.<br><br><a href="{{ route('login') }}">Login</a></div></div>

@include('antareja::topology.graph-script')
<script>
(function () {
    var $ = function (id) { return document.getElementById(id); };
    var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };
    var store = { get: function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } }, set: function (k, v) { try { localStorage.setItem(k, v); } catch (e) {} } };
    var site = $('site'), seen = null, seenDown = null, lastOk = Date.now(), serverOffset = 0, last = null;
    var COLORS = { info: 'var(--info)', warn: 'var(--warn)', crit: 'var(--crit)', down: 'var(--crit)', ok: 'var(--ok)', unknown: 'var(--dim)' };
    site.value = store.get('ant-noc-site') || '';

    function ago(iso) {
        var s = Math.max(0, Math.floor((Date.now() + serverOffset - new Date(iso).getTime()) / 1000));
        if (s < 60) return s + 'd'; if (s < 3600) return Math.floor(s / 60) + 'm';
        if (s < 86400) return Math.floor(s / 3600) + 'j ' + Math.floor(s % 3600 / 60) + 'm'; return Math.floor(s / 86400) + 'h ' + Math.floor(s % 86400 / 3600) + 'j';
    }
    function hhmm(iso) { return new Date(iso).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', hour12: false }); }
    function bps(b) { if (b == null) return '–'; var u = ['bps', 'K', 'M', 'G'], i = 0; while (b >= 1000 && i < 3) { b /= 1000; i++; } return (b >= 100 ? b.toFixed(0) : b.toFixed(1)) + (i ? u[i] : ' bps'); }
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

    // ---- Suara alarm (WebAudio, tanpa file). Browser hanya mengizinkan suara setelah satu klik di halaman.
    var audio = null, soundOn = store.get('ant-noc-sound') === 'on';
    function renderSound() {
        var b = $('sound');
        b.className = 'iconbtn' + (soundOn ? ' on' : ' hint');
        b.innerHTML = soundOn ? '<i class="fa-solid fa-volume-high"></i><span>SUARA</span>' : '<i class="fa-solid fa-volume-xmark"></i><span>SUARA MATI · KLIK</span>';
    }
    function beep(level) {
        if (!soundOn) return;
        try {
            audio = audio || new (window.AudioContext || window.webkitAudioContext)();
            if (audio.state === 'suspended') audio.resume();
            var t = audio.currentTime, tones = level === 'crit' ? [880, 660, 880, 660, 880, 660] : [740, 740];
            tones.forEach(function (f, i) {
                var o = audio.createOscillator(), g = audio.createGain();
                o.type = 'square'; o.frequency.value = f; g.gain.setValueAtTime(0.0001, t + i * 0.22);
                g.gain.exponentialRampToValueAtTime(0.18, t + i * 0.22 + 0.02); g.gain.exponentialRampToValueAtTime(0.0001, t + i * 0.22 + 0.2);
                o.connect(g); g.connect(audio.destination); o.start(t + i * 0.22); o.stop(t + i * 0.22 + 0.21);
            });
        } catch (e) {}
    }
    $('sound').addEventListener('click', function () {
        soundOn = !soundOn; store.set('ant-noc-sound', soundOn ? 'on' : 'off'); renderSound();
        if (soundOn) beep('warn'); // bunyi uji sekaligus membuka izin audio browser
    });
    renderSound();

    // ---- Tampilan tengah: dinding / topologi / otomatis
    var mode = store.get('ant-noc-view') || 'wall', showing = 'wall', rot = null;
    function show(v) {
        showing = v;
        $('v-wall').classList.toggle('hidden', v !== 'wall'); $('v-map').classList.toggle('hidden', v !== 'map');
        $('view-title').textContent = v === 'wall' ? 'Dinding status' : 'Topologi jaringan';
        if (v === 'map') setTimeout(function () { topo.fit(); }, 60);
    }
    function setMode(m) {
        mode = m; store.set('ant-noc-view', m);
        document.querySelectorAll('[data-view]').forEach(function (b) { b.classList.toggle('on', b.dataset.view === m); });
        clearInterval(rot);
        if (m === 'auto') { show('wall'); rot = setInterval(function () { show(showing === 'wall' ? 'map' : 'wall'); }, 30000); } else { show(m); }
    }
    document.querySelectorAll('[data-view]').forEach(function (b) { b.addEventListener('click', function () { setMode(b.dataset.view); }); });

    function spark(vals) {
        var pts = vals.filter(function (v) { return v != null; });
        if (pts.length < 2) return '<svg viewBox="0 0 72 24"></svg>';
        var max = Math.max.apply(null, pts.concat([1])), n = vals.length, d = '', pen = false;
        vals.forEach(function (v, i) {
            var x = (i / Math.max(1, n - 1)) * 70 + 1;
            if (v == null) { pen = false; return; }
            var y = 22 - (v / max) * 20; d += (pen ? 'L' : 'M') + x.toFixed(1) + ' ' + y.toFixed(1) + ' '; pen = true;
        });
        var lossMarks = vals.map(function (v, i) { return v == null ? '<line x1="' + ((i / Math.max(1, n - 1)) * 70 + 1).toFixed(1) + '" y1="2" x2="' + ((i / Math.max(1, n - 1)) * 70 + 1).toFixed(1) + '" y2="22" stroke="#ff4d4f" stroke-width="1"/>' : ''; }).join('');
        return '<svg viewBox="0 0 72 24">' + lossMarks + '<path d="' + d + '" fill="none" stroke="#2ee6c5" stroke-width="1.4"/></svg>';
    }

    function render(d) {
        last = d;
        serverOffset = new Date(d.time).getTime() - Date.now();
        var dev = d.devices, probs = d.problems || [], al = d.alarm || { level: 'ok', text: '' };
        var ov = $('overall');
        ov.className = 'overall ' + al.level;
        ov.innerHTML = '<i class="fa-solid ' + (al.level === 'ok' ? 'fa-shield-halved' : 'fa-triangle-exclamation') + '"></i><span>' + esc(al.text) + '</span>';
        document.title = (al.level === 'crit' ? '⚠ ' : '') + 'NOC · ANTAREJA NMS';

        // Bunyi: perangkat down baru, atau problem High/Disaster baru yang belum ACK
        var downIds = d.wall.filter(function (w) { return w.state === 'down' || w.state === 'noping'; }).map(function (w) { return w.id; });
        var probIds = probs.map(function (p) { return p.id; });
        if (seenDown !== null) {
            var newDown = downIds.some(function (id) { return seenDown.indexOf(id) < 0; });
            var newHigh = probs.some(function (p) { return seen.indexOf(p.id) < 0 && !p.ack && p.severity.rank >= 4; });
            if (newDown || newHigh) beep('crit');
        }
        var av = dev.availability, k = $('k-avail');
        k.querySelector('.val').innerHTML = (av === null ? '–' : av.toFixed(av === 100 ? 0 : 2).replace('.', ',')) + '<small>%</small>';
        k.querySelector('.meter').className = 'meter ' + (av === null ? '' : (av >= 99 ? '' : (av >= 95 ? 'warn' : 'crit')));
        k.querySelector('.meter span').style.width = (av || 0) + '%';
        k.querySelector('.sub').textContent = dev.up + ' / ' + (dev.up + dev.down) + ' perangkat dipantau' + (dev.off ? ' · ' + dev.off + ' nonaktif' : '');
        $('k-up').querySelector('.val').textContent = dev.up;
        var totalDown = dev.down + (d.ping ? d.ping.down : 0);
        $('k-down').querySelector('.val').textContent = totalDown;
        $('k-down').className = 'panel kpi ' + (totalDown ? 'crit' : 'ok');
        $('k-down-sub').textContent = d.ping && d.ping.down ? d.ping.down + ' tidak menjawab ping' : (d.ping && d.ping.fresh ? 'ping cepat aktif' : '');

        if (d.severity) {
            var max = Math.max.apply(null, d.severity.map(function (s) { return s.n; }).concat([1]));
            $('k-sev').innerHTML = d.severity.map(function (s) {
                return '<li><span>' + esc(s.label) + '</span><span class="bar"><span style="width:' + (s.n ? Math.max(5, s.n / max * 100) : 0) + '%;background:' + COLORS[s.tone] + '"></span></span><b class="' + (s.n ? '' : 'zero') + '">' + s.n + '</b></li>';
            }).join('');
        } else { $('k-sev-wrap').hidden = true; }
        var chips = [];
        if (d.incidents !== null) chips.push('<span class="' + (d.incidents ? 'bad' : '') + '">INC ' + d.incidents + '</span>');
        if (d.cctv && d.cctv.total) chips.push('<span class="' + (d.cctv.ok < d.cctv.total ? 'bad' : '') + '">CCTV ' + d.cctv.ok + '/' + d.cctv.total + '</span>');
        $('k-chips').innerHTML = chips.join('');

        // Latensi & loss
        $('lat-n').textContent = d.latency.length || '';
        $('lat').innerHTML = d.latency.length ? d.latency.map(function (l) {
            var tone = l.loss >= 20 || l.rtt >= 300 ? 't-crit' : (l.loss > 0 || l.rtt >= 150 ? 't-warn' : 't-ok');
            return '<li><span class="n">' + esc(l.name) + '<small>' + esc(l.site || '') + '</small></span>' + spark(l.spark)
                + '<span class="v ' + tone + '">' + (l.rtt != null ? l.rtt.toFixed(l.rtt >= 100 ? 0 : 1) + ' ms' : '–') + '<small>loss ' + l.loss + '%</small></span></li>';
        }).join('') : '<li><div class="empty"><i class="fa-solid fa-gauge-high"></i>' + (d.ping && d.ping.fresh ? 'SEMUA RESPONS NORMAL' : 'MENUNGGU DATA PING CEPAT') + '</div></li>';

        // Dinding status
        var bySite = {}, order = { down: 0, noping: 1, slow: 2, ok: 3, off: 4 };
        d.wall.forEach(function (w) { (bySite[w.site] = bySite[w.site] || []).push(w); });
        var wallEl = $('wall'); wallEl.classList.toggle('dense', d.wall.length > 120);
        wallEl.innerHTML = Object.keys(bySite).sort().map(function (s) {
            var list = bySite[s].sort(function (a, b) { return order[a.state] - order[b.state] || a.name.localeCompare(b.name); });
            var bad = list.filter(function (w) { return w.state === 'down' || w.state === 'noping'; }).length;
            var up = list.filter(function (w) { return w.state !== 'off' && w.state !== 'down' && w.state !== 'noping'; }).length;
            return '<div class="wsec"><h3><b>' + esc(s) + '</b><em>' + up + '/' + list.length + ' online' + (bad ? ' · <span class="t-crit">' + bad + ' bermasalah</span>' : '') + '</em></h3><div class="tiles">'
                + list.map(function (w) {
                    var sub = w.state === 'down' ? 'DOWN' : (w.state === 'noping' ? 'tidak menjawab ping' : (w.rtt != null ? w.rtt.toFixed(w.rtt >= 100 ? 0 : 1) + ' ms' + (w.loss ? ' · loss ' + w.loss + '%' : '') : w.group));
                    return '<a class="tile ' + w.state + '" href="{{ url('device') }}/' + w.id + '" target="_blank" rel="noopener" title="' + esc(w.name + ' · ' + w.ip + ' · ' + w.group) + '">'
                        + '<span class="tn">' + esc(w.name) + '</span><span class="ts">' + esc(sub) + '</span></a>';
                }).join('') + '</div></div>';
        }).join('') || '<div class="empty"><i class="fa-solid fa-server"></i>BELUM ADA PERANGKAT</div>';

        // Live problem: belum ACK dulu; problem lama diredupkan
        $('feed-n').textContent = d.problem_count != null ? d.problem_count : probs.length || '';
        $('feed').innerHTML = probs.length ? probs.map(function (p) {
            var isNew = seen && seen.indexOf(p.id) < 0, cls = (isNew ? 'new ' : '') + (p.ack || p.old ? 'dim' : '');
            var tag = !p.ack && p.new ? '<span class="tag new">BARU</span>' : (p.old ? '<span class="tag old">LAMA</span>' : '');
            return '<li class="' + cls + '"><a href="' + p.url + '" target="_blank" rel="noopener"><span class="sbar" style="background:' + COLORS[p.severity.tone] + '"></span>'
                + '<span style="min-width:0"><div class="t">' + tag + esc(p.title) + '</div><div class="m">' + esc(p.severity.label.toUpperCase()) + (p.device ? ' · ' + esc(p.device) : '') + (p.site ? ' · ' + esc(p.site) : '') + '</div></span>'
                + '<span class="age" data-since="' + p.since + '">' + ago(p.since) + '<small class="' + (p.ack ? '' : 'nack') + '">' + (p.ack ? 'ACK' : 'BELUM ACK') + '</small></span></a></li>';
        }).join('') : '<li><div class="empty"><i class="fa-solid fa-circle-check"></i>TIDAK ADA PROBLEM AKTIF</div></li>';
        seen = probIds; seenDown = downIds;

        // Kejadian 1 jam
        var IC = { down: ['down', 'fa-arrow-down'], 'poll-down': ['down', 'fa-arrow-down'], up: ['up', 'fa-arrow-up'], 'poll-up': ['up', 'fa-arrow-up'],
            reboot: ['warn', 'fa-rotate'], flapping: ['down', 'fa-wave-square'], latency: ['warn', 'fa-gauge-high'] };
        $('ev-n').textContent = d.events.length || '';
        $('events').innerHTML = d.events.length ? d.events.map(function (e) {
            var ic = IC[e.kind] || ['warn', 'fa-circle-info'];
            return '<li><span class="ic ' + ic[0] + '"><i class="fa-solid ' + ic[1] + '"></i></span><span class="msg">' + esc(e.message)
                + '<small>' + esc((e.kind.indexOf('poll-') === 0 ? 'polling · ' : '') + (e.site || '')) + '</small></span><span class="tm">' + hhmm(e.at) + '</span></li>';
        }).join('') : '<li><div class="empty"><i class="fa-solid fa-clock-rotate-left"></i>TIDAK ADA KEJADIAN 1 JAM TERAKHIR</div></li>';

        // Perangkat down
        $('down-n').textContent = d.down_list.length || '';
        $('downs').innerHTML = d.down_list.length ? d.down_list.map(function (x) {
            return '<li><a href="{{ url('device') }}/' + x.id + '" target="_blank" rel="noopener"><span class="sbar" style="background:' + (x.ping ? 'var(--orange)' : 'var(--crit)') + '"></span><span style="min-width:0"><div class="t">' + esc(x.name) + '</div>'
                + '<div class="m">' + esc(x.ip) + (x.site ? ' · ' + esc(x.site) : '') + (x.reason ? ' · ' + esc(x.reason) : '') + '</div></span><span class="age" style="color:' + (x.ping ? 'var(--orange)' : 'var(--crit)') + '">' + (x.ping ? 'PING ✕' : 'DOWN') + '</span></a></li>';
        }).join('') : '<li><div class="empty"><i class="fa-solid fa-circle-check"></i>SEMUA PERANGKAT ONLINE</div></li>';

        function tile(name, icon, up, down, total) {
            var off = Math.max(0, total - up - down);
            return '<div class="stile' + (down ? ' bad' : '') + '"><div class="n">' + (icon ? '<i class="fa-solid fa-' + icon + '"></i>' : '') + esc(name) + '</div>'
                + '<div class="c">' + (down ? '<span class="dn">' + down + '↓</span> ' : '') + up + '<small>/' + total + '</small></div>'
                + '<div class="stack">' + (up ? '<span style="flex:' + up + ';background:var(--ok)"></span>' : '') + (down ? '<span style="flex:' + down + ';background:var(--crit)"></span>' : '') + (off ? '<span style="flex:' + off + ';background:var(--dim)"></span>' : '') + '</div></div>';
        }
        $('sites').innerHTML = d.sites.filter(function (s) { return s.total > 0; }).map(function (s) { return tile(s.code + ' · ' + s.name, 'location-dot', s.up, s.down, s.total); }).join('') || '<div class="empty">Belum ada perangkat ber-site</div>';
        $('groups').innerHTML = d.groups.length ? d.groups.map(function (g) { return tile(g.name, g.icon, g.up, g.down, g.total); }).join('') : '<div class="empty">Belum ada grup</div>';
        $('uplinks').innerHTML = d.uplinks.length ? d.uplinks.map(function (u) {
            var c = u.util >= 90 ? 'var(--crit)' : (u.util >= 70 ? 'var(--warn)' : 'var(--ok)');
            return '<li><a href="' + u.url + '" target="_blank" rel="noopener" style="display:block;padding:0;color:inherit;text-decoration:none"><div class="r1"><span>' + esc(u.device) + ' · ' + esc(u.port) + '</span><b style="color:' + c + '">' + u.util.toFixed(0) + '%</b></div>'
                + '<div class="bar"><span style="width:' + Math.max(2, u.util) + '%;background:' + c + '"></span></div>'
                + '<div class="r2">↓ ' + bps(u.in) + ' · ↑ ' + bps(u.out) + ' / ' + bps(u.speed) + (u.alias ? ' · ' + esc(u.alias) : '') + '</div></a></li>';
        }).join('') : '<li><div class="empty"><i class="fa-solid fa-network-wired"></i>BELUM ADA DATA TRAFIK UPLINK</div></li>';

        var s = d.graph.stats;
        $('mapstat').innerHTML = '<b>' + s.nodes + '</b> node · <b>' + s.edges + '</b> link' + (s.down ? ' · <b style="color:var(--crit)">' + s.down + ' down</b>' : '');
    }

    var topo = AntTopology.create($('noc-map'), {
        theme: 'dark', flow: true, readonly: true, live: true, liveUrl: '{{ route('antareja.topology.live') }}',
        dataUrl: function () { return '{{ route('antareja.noc.data') }}?site_id=' + site.value; },
        onData: function (d) { lastOk = Date.now(); render(d); },
        onSelect: function (n) { if (n) window.open(n.url, '_blank', 'noopener'); }
    });
    function refresh() {
        topo.load().catch(function (e) { if (String(e).indexOf('401') >= 0 || String(e).indexOf('419') >= 0) $('overlay').hidden = false; });
    }
    setMode(mode); refresh(); topo.every(15); tick();
    site.addEventListener('change', function () { store.set('ant-noc-site', site.value); seen = null; seenDown = null; topo.reload().catch(function () {}); });

    function fs() { if (!document.fullscreenElement) document.documentElement.requestFullscreen().catch(function () {}); else document.exitFullscreen(); }
    $('fs').addEventListener('click', fs);
    document.addEventListener('keydown', function (e) { if ((e.key === 'f' || e.key === 'F') && e.target === document.body) fs(); });
})();
</script>
</body>
</html>
