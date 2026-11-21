@php
    use Illuminate\Support\Facades\Vite;
    $font = fn (string $f) => Vite::asset('packages/antareja/resources/fonts/' . $f);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light dark">
    <title>@yield('title') · ANTAREJA NMS</title>
    <link rel="icon" type="image/png" href="{{ asset('images/antareja/favicon-32.png') }}">
    <style>
        @font-face { font-family: 'Barlow Semi Condensed'; src: url('{{ $font('BarlowSemiCondensed-Bold.woff2') }}') format('woff2'); font-weight: 700; font-display: swap; }
        @font-face { font-family: 'Barlow Semi Condensed'; src: url('{{ $font('BarlowSemiCondensed-SemiBold.woff2') }}') format('woff2'); font-weight: 600; font-display: swap; }
        @font-face { font-family: 'IBM Plex Sans'; src: url('{{ $font('IBMPlexSans-Variable.woff2') }}') format('woff2'); font-weight: 400 600; font-display: swap; }
        @font-face { font-family: 'IBM Plex Mono'; src: url('{{ $font('IBMPlexMono-Medium.woff2') }}') format('woff2'); font-weight: 500; font-display: swap; }
        :root {
            --surface: #f6f5f4; --raised: #ffffff; --sunken: #eceae8; --border: #d9d5d2; --border-strong: #857e79;
            --ink: #1a1716; --muted: #5a534e; --brand: #fe0002; --brand-strong: #cf0002; --on-brand: #fff;
            --crit: #a8101a; --crit-tint: #fbe3e3; --ok: #0b6e63; --ok-tint: #e1f3f0; --info-tint: #e5ecfc; --focus: #1f4fc4;
            --display: "Barlow Semi Condensed", "Arial Narrow", sans-serif;
            --sans: "IBM Plex Sans", "Segoe UI", system-ui, sans-serif; --mono: "IBM Plex Mono", ui-monospace, Consolas, monospace;
        }
        @media (prefers-color-scheme: dark) {
            :root { --surface: #121110; --raised: #1c1a19; --sunken: #0b0a0a; --border: #34302d; --border-strong: #736c67;
                --ink: #f4f2f0; --muted: #aba49f; --crit: #ff8a82; --crit-tint: #3a1414; --ok: #4cc9b8; --ok-tint: #0e2d29; --info-tint: #16223f; --focus: #8fb0ff; }
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; }
        body { background: var(--surface); color: var(--ink); font: 400 15px/1.5 var(--sans); }
        .shell { min-height: 100vh; display: grid; grid-template-columns: minmax(320px, 5fr) 7fr; }
        /* Panel merek: arang + garis kontur seperti peta topografi tambang */
        .brand { position: relative; overflow: hidden; background: #141211; color: #f4f2f0; padding: 48px 48px 32px; display: flex; flex-direction: column; }
        .brand::before { content: ""; position: absolute; inset: -20%; opacity: .16; pointer-events: none;
            background: repeating-radial-gradient(circle at 78% 82%, transparent 0 22px, #fe0002 22px 23px); }
        .brand::after { content: ""; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: var(--brand); }
        .brand > * { position: relative; }
        .mark { display: flex; align-items: center; gap: 14px; }
        .mark img { width: 56px; height: 56px; }
        .mark b { font: 700 30px/1 var(--display); letter-spacing: .03em; }
        .mark small { display: block; font: 600 13px/1.2 var(--display); letter-spacing: .14em; color: #aba49f; margin-top: 4px; }
        .brand h2 { font: 700 34px/1.12 var(--display); margin: auto 0 16px; max-width: 15ch; padding-top: 48px; }
        .brand p.lead { color: #c9c3be; margin: 0 0 28px; max-width: 42ch; }
        .feats { list-style: none; margin: 0 0 32px; padding: 0; display: grid; gap: 10px; }
        .feats li { display: flex; gap: 10px; align-items: baseline; color: #e6e1dd; font-size: 14px; }
        .feats li::before { content: ""; flex: none; width: 8px; height: 8px; background: var(--brand); transform: translateY(-1px); }
        .brand footer { font-size: 12px; color: #8d8580; border-top: 1px solid #2c2826; padding-top: 16px; }
        .brand footer a { color: inherit; }
        .main { display: flex; align-items: center; justify-content: center; padding: 48px 24px; }
        .card { width: 100%; max-width: 460px; }
        .eyebrow { font: 600 12px/1 var(--mono); letter-spacing: .12em; text-transform: uppercase; color: var(--brand-strong); margin: 0 0 10px; }
        h1 { font: 700 32px/1.1 var(--display); margin: 0 0 8px; letter-spacing: .01em; }
        .sub { color: var(--muted); margin: 0 0 28px; }
        label { display: block; font-weight: 600; font-size: 13px; margin: 0 0 6px; }
        .field { margin-bottom: 16px; }
        .row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        input[type=text], input[type=password], input[type=email] { width: 100%; height: 44px; padding: 0 12px; font: inherit; color: var(--ink);
            background: var(--raised); border: 1px solid var(--border-strong); border-radius: 6px; }
        input:focus-visible, button:focus-visible, a:focus-visible { outline: 2px solid var(--focus); outline-offset: 2px; }
        input.token { font: 500 18px/1 var(--mono); letter-spacing: .14em; text-transform: uppercase; }
        .invalid input, input[aria-invalid=true] { border-color: var(--crit); }
        .hint { font-size: 12.5px; color: var(--muted); margin-top: 6px; }
        .err { font-size: 12.5px; color: var(--crit); margin-top: 6px; font-weight: 500; }
        code { font: 500 12.5px var(--mono); background: var(--sunken); padding: 1px 5px; border-radius: 4px; }
        .check { display: flex; align-items: center; gap: 8px; font-weight: 400; font-size: 14px; margin: 4px 0 20px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; width: 100%; height: 46px; border: 0; border-radius: 6px;
            font: 600 15px var(--sans); cursor: pointer; background: var(--brand-strong); color: var(--on-brand); text-decoration: none; }
        .btn:hover { filter: brightness(.9); }
        .btn.secondary { background: var(--raised); color: var(--ink); border: 1px solid var(--border-strong); margin-top: 10px; }
        .alert { border-radius: 6px; padding: 12px 14px; margin-bottom: 20px; font-size: 14px; background: var(--crit-tint); color: var(--crit); border-left: 3px solid var(--crit); }
        .alert.ok { background: var(--ok-tint); color: var(--ok); border-color: var(--ok); }
        .note { background: var(--info-tint); border-radius: 6px; padding: 12px 14px; font-size: 13.5px; margin-bottom: 20px; }
        .note pre { margin: 8px 0 0; font: 500 13px var(--mono); white-space: pre-wrap; }
        .divider { height: 1px; background: var(--border); margin: 8px 0 20px; }
        .message { margin-top: 24px; font-size: 13px; color: var(--muted); text-align: center; }
        @media (max-width: 900px) {
            .shell { grid-template-columns: 1fr; }
            .brand { padding: 24px 20px; }
            .brand h2, .brand p.lead, .feats, .brand footer { display: none; }
            .main { padding: 32px 16px 48px; align-items: flex-start; }
            .row2 { grid-template-columns: 1fr; gap: 0; }
        }
    </style>
</head>
<body>
<div class="shell">
    <aside class="brand">
        <div class="mark">
            <img src="{{ asset('images/antareja/antareja-mark-128.png') }}" alt="">
            <div><b>ANTAREJA</b><small>NETWORK MANAGEMENT SYSTEM</small></div>
        </div>
        <h2>Satu login untuk seluruh infrastruktur ICT tambang.</h2>
        <p class="lead">{{ config('antareja.full_name') }}</p>
        <ul class="feats">
            <li>Monitoring jaringan, wireless &amp; kualitas link</li>
            <li>Problem, insiden, eskalasi &amp; SLA</li>
            <li>CCTV live view, fiber optic &amp; kapasitas</li>
            <li>Work order, change request &amp; backup konfigurasi</li>
        </ul>
        <footer>
            ANTAREJA NMS v{{ \Antareja\AntarejaServiceProvider::VERSION }} ·
            <a href="https://www.gnu.org/licenses/gpl-3.0.html" rel="noopener">Lisensi GPL-3.0</a>
        </footer>
    </aside>
    <main class="main">
        <div class="card">@yield('content')</div>
    </main>
</div>
</body>
</html>
