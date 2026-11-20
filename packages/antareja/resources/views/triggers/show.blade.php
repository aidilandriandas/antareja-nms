@extends('antareja::layouts.page')

@php
    $T = \Antareja\Models\Trigger::class;
    $sv = \Antareja\Support\Severity::problem($trigger->severity);
@endphp

@section('title', $trigger->name)
@section('breadcrumb')
    <li><a href="{{ route('antareja.triggers.index') }}">Trigger</a></li>
    <li class="active">{{ $trigger->name }}</li>
@endsection
@section('heading')
    {{ $trigger->name }}
    @if($trigger->enabled)<x-antareja::status :status="$trigger->stateMeta()['tone']">{{ $trigger->stateMeta()['label'] }}</x-antareja::status>@else<x-antareja::status status="unknown">Nonaktif</x-antareja::status>@endif
@endsection
@section('subheading')<span class="ant-data">{{ $trigger->expression($item) }}{{ $unit && ! $trigger->unit() ? ' ' . $unit : '' }}</span>@endsection

@section('actions')
    @can('ant-trigger.update')
        <form method="post" action="{{ route('antareja.triggers.evaluate', $trigger) }}">@csrf<button class="btn btn-default"><i class="fa fa-refresh"></i> Evaluasi sekarang</button></form>
        <a href="{{ route('antareja.triggers.edit', $trigger) }}" class="btn btn-default"><i class="fa fa-pencil"></i> Edit</a>
        <form method="post" action="{{ route('antareja.triggers.destroy', $trigger) }}" onsubmit="return confirm('Hapus trigger ini?')">@csrf @method('DELETE')<button class="btn btn-default">Hapus</button></form>
    @endcan
@endsection

@section('page')
    <div class="tw:grid tw:grid-cols-2 tw:md:grid-cols-4 tw:gap-3 tw:mb-5">
        <x-antareja::stat label="Nilai fungsi" :value="$T::fmt($trigger->last_value, $unit)" :tone="$trigger->state === 'problem' ? 'crit' : ($trigger->state === 'ok' ? 'ok' : 'neutral')" :hint="$trigger->last_evaluated_at ? 'dievaluasi ' . $trigger->last_evaluated_at->diffForHumans() : 'belum dievaluasi'" />
        <x-antareja::stat label="Ambang problem" :value="$T::OPERATORS[$trigger->operator] . ' ' . $T::fmt($trigger->threshold, $unit)" />
        <x-antareja::stat label="Ambang pemulihan" :value="$trigger->recovery_threshold !== null ? $T::fmt($trigger->recovery_threshold, $unit) : 'sama'" hint="histeresis" />
        <x-antareja::stat label="Severity" :value="$sv['label']" />
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="panel panel-default">
                <div class="panel-heading"><strong>{{ $trigger->metricLabel() }} — 24 jam terakhir</strong> <span class="tw:text-xs ant-muted">{{ $item }}</span></div>
                <div class="panel-body">
                    @if(count($points) < 2)
                        <p class="ant-muted tw:m-0">Belum cukup data. {{ $trigger->source === 'quality' ? 'Quality Probe mengukur tiap 5 menit.' : 'Nilai dicatat tiap menit saat trigger dievaluasi.' }}</p>
                    @else
                        @php
                            $w = 800; $h = 200; $pad = 36;
                            $t0 = $points[0][0]; $t1 = end($points)[0]; $span = max(1, $t1 - $t0);
                            $vals = array_column($points, 1);
                            $lo = min(min($vals), $trigger->threshold); $hi = max(max($vals), $trigger->threshold);
                            if ($hi == $lo) { $hi = $lo + 1; }
                            $range = $hi - $lo; $hi += $range * 0.1; $lo -= $range * 0.1;
                            if (min($vals) >= 0 && $lo < 0) { $lo = 0; }
                            $x = fn ($t) => $pad + ($t - $t0) / $span * ($w - $pad - 8);
                            $y = fn ($v) => 8 + (1 - ($v - $lo) / ($hi - $lo)) * ($h - 28);
                            $line = implode(' ', array_map(fn ($p) => round($x($p[0]), 1) . ',' . round($y($p[1]), 1), $points));
                        @endphp
                        <svg viewBox="0 0 {{ $w }} {{ $h }}" class="tw:w-full tw:h-auto" role="img" aria-label="Grafik {{ $trigger->metricLabel() }}">
                            <line x1="{{ $pad }}" x2="{{ $w - 8 }}" y1="{{ $y($trigger->threshold) }}" y2="{{ $y($trigger->threshold) }}" stroke="var(--ant-crit)" stroke-dasharray="6 4" stroke-width="1.5"/>
                            <text x="{{ $w - 10 }}" y="{{ $y($trigger->threshold) - 4 }}" text-anchor="end" font-size="11" fill="var(--ant-crit)">ambang {{ $T::fmt($trigger->threshold, $unit) }}</text>
                            @if($trigger->recovery_threshold !== null)
                                <line x1="{{ $pad }}" x2="{{ $w - 8 }}" y1="{{ $y($trigger->recovery_threshold) }}" y2="{{ $y($trigger->recovery_threshold) }}" stroke="var(--ant-ok)" stroke-dasharray="3 4" stroke-width="1"/>
                            @endif
                            <polyline points="{{ $line }}" fill="none" stroke="var(--ant-info)" stroke-width="1.75" stroke-linejoin="round"/>
                            <text x="2" y="14" font-size="11" fill="currentColor" opacity=".6">{{ $T::fmt($hi) }}</text>
                            <text x="2" y="{{ $h - 20 }}" font-size="11" fill="currentColor" opacity=".6">{{ $T::fmt($lo) }}</text>
                            <text x="{{ $pad }}" y="{{ $h - 4 }}" font-size="11" fill="currentColor" opacity=".6">{{ \Carbon\Carbon::createFromTimestamp($t0, config('app.timezone'))->format('d M H:i') }}</text>
                            <text x="{{ $w - 8 }}" y="{{ $h - 4 }}" text-anchor="end" font-size="11" fill="currentColor" opacity=".6">{{ \Carbon\Carbon::createFromTimestamp($t1, config('app.timezone'))->format('d M H:i') }}</text>
                        </svg>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Detail</strong></div>
                <table class="table table-condensed tw:m-0">
                    <tr><th>Sumber</th><td>{{ $T::SOURCES[$trigger->source] }}</td></tr>
                    <tr><th>Item</th><td>{{ $item }}</td></tr>
                    <tr><th>Fungsi</th><td>{{ $T::FUNCTIONS[$trigger->function] }}{{ $trigger->function === 'last' ? '' : ' selama ' . $trigger->window_minutes . ' menit' }}</td></tr>
                    @if($trigger->device)<tr><th>Perangkat</th><td><a href="{{ route('antareja.inventory.show', $trigger->device_id) }}">{{ $trigger->device->displayName() }}</a></td></tr>@endif
                    @if($trigger->description)<tr><th>Catatan</th><td class="tw:whitespace-pre-line">{{ $trigger->description }}</td></tr>@endif
                </table>
            </div>
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Problem dari trigger ini</strong></div>
                <div class="list-group">
                    @forelse($problems as $p)
                        <a href="{{ route('antareja.problems.show', $p) }}" class="list-group-item tw:text-sm">{{ $p->title }} <span class="tw:text-xs ant-muted">· {{ $p->started_at->format('d M H:i') }} · {{ $p->isOpen() ? 'aktif' : 'selesai' }}</span></a>
                    @empty
                        <div class="list-group-item ant-muted tw:text-sm">Belum pernah.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
