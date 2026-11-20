<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $r->title() }}</title>
    <style>
        @page { size: A4; margin: 14mm 12mm; }
        * { box-sizing: border-box; }
        body { font: 10.5pt/1.45 "IBM Plex Sans", "Segoe UI", Arial, sans-serif; color: #1c1917; background: #fff; margin: 0; padding: 24px; }
        header { display: flex; align-items: center; gap: 14px; border-bottom: 3px solid #FE0002; padding-bottom: 10px; margin-bottom: 14px; }
        header img { width: 48px; height: 48px; }
        header h1 { font-size: 17pt; margin: 0; }
        header p { margin: 2px 0 0; color: #57534e; font-size: 9.5pt; }
        h2 { font-size: 12.5pt; margin: 18px 0 6px; padding-bottom: 3px; border-bottom: 1px solid #d6d3d1; break-after: avoid; }
        table { width: 100%; border-collapse: collapse; font-size: 9pt; margin-bottom: 4px; }
        th, td { text-align: left; padding: 3px 6px; border-bottom: 1px solid #e7e5e4; vertical-align: top; }
        thead th { background: #f5f5f4; font-weight: 600; }
        tr { break-inside: avoid; }
        .text-right { text-align: right; }
        .ant-report-kv th { width: 34%; font-weight: 600; background: none; }
        .ant-report-muted, .ant-report-empty { color: #57534e; font-size: 8.5pt; }
        .ant-report-nowrap { white-space: nowrap; }
        .toolbar { position: fixed; top: 12px; right: 12px; }
        .toolbar button { font: inherit; padding: 6px 14px; border: 1px solid #1c1917; background: #1c1917; color: #fff; border-radius: 4px; cursor: pointer; }
        @media print { .toolbar { display: none; } body { padding: 0; } }
    </style>
</head>
<body>
    <div class="toolbar"><button type="button" onclick="window.print()">Cetak / Simpan PDF</button></div>
    <header>
        <img src="{{ asset('images/antareja/antareja-mark-128.png') }}" alt="">
        <div>
            <h1>{{ $r->title() }}</h1>
            <p>ANTAREJA NMS · Mining ICT Infrastructure Management · dicetak {{ now()->format('d M Y H:i') }} oleh {{ auth()->user()->realname ?: auth()->user()->username }}</p>
        </div>
    </header>
    @include('antareja::reports._body', ['deviceLimit' => 40])
    <script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 300); });</script>
</body>
</html>
