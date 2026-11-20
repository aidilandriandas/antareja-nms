{{--
  Bar chart satu seri (SVG). $bars: [['label' => 'Nov', 'value' => 3, 'text' => '3 incident'], ...]
  $name: nama seri (aria-label). $min: dasar skala (bawaan 0). Batang membulat 4px di ujung data, menempel ke garis dasar.
--}}
@php
    $w = 560; $h = 180; $padL = 8; $padB = 24; $padT = 18;
    $min = $min ?? 0;
    $vals = array_filter(array_column($bars, 'value'), fn ($v) => $v !== null);
    $max = $vals ? max($vals) : 1; if ($max <= $min) { $max = $min + 1; }
    $n = max(1, count($bars)); $slot = ($w - $padL * 2) / $n; $bw = min(44, $slot * 0.56);
    $base = $h - $padB;
    $yOf = fn ($v) => $base - (($v - $min) / ($max - $min)) * ($base - $padT);
@endphp
<svg viewBox="0 0 {{ $w }} {{ $h }}" class="tw:w-full tw:h-auto" role="img" aria-label="{{ $name }}">
    <line x1="{{ $padL }}" x2="{{ $w - $padL }}" y1="{{ $base }}" y2="{{ $base }}" stroke="currentColor" stroke-opacity=".25"/>
    @foreach($bars as $i => $b)
        @php($cx = $padL + $slot * $i + $slot / 2)
        <g>
            <title>{{ $b['label'] }}: {{ $b['text'] ?? $b['value'] }}</title>
            {{-- area hover lebih besar dari batang --}}
            <rect x="{{ $cx - $slot / 2 }}" y="0" width="{{ $slot }}" height="{{ $h }}" fill="transparent"/>
            @if($b['value'] !== null)
                @php($top = min($base - 1, $yOf(max($min, $b['value']))))
                @php($r = min(4, ($base - $top) / 2, $bw / 2))
                <path d="M{{ $cx - $bw / 2 }},{{ $base }} V{{ $top + $r }} Q{{ $cx - $bw / 2 }},{{ $top }} {{ $cx - $bw / 2 + $r }},{{ $top }} H{{ $cx + $bw / 2 - $r }} Q{{ $cx + $bw / 2 }},{{ $top }} {{ $cx + $bw / 2 }},{{ $top + $r }} V{{ $base }} Z" fill="var(--ant-info)"/>
                @if($loop->last)<text x="{{ $cx }}" y="{{ $top - 5 }}" text-anchor="middle" font-size="12" font-weight="600" fill="currentColor">{{ $b['text'] ?? $b['value'] }}</text>@endif
            @endif
            <text x="{{ $cx }}" y="{{ $h - 6 }}" text-anchor="middle" font-size="11" fill="currentColor" fill-opacity=".6">{{ $b['label'] }}</text>
        </g>
    @endforeach
</svg>
