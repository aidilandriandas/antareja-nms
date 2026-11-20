@extends('antareja::layouts.page')

@section('title', 'Trigger')
@section('breadcrumb')
    <li><a href="{{ route('antareja.problems.index') }}">Event &amp; Alert Center</a></li>
    <li class="active">Trigger</li>
@endsection
@section('heading', 'Trigger expression')
@section('subheading', 'Kondisi ambang atas data yang sudah dikumpulkan (Quality Probe, port, sensor, wireless, perangkat). Dievaluasi tiap menit; trigger yang terpenuhi menjadi problem di Event & Alert Center.')

@section('actions')
    @can('ant-trigger.update')<a href="{{ route('antareja.triggers.create') }}" class="btn btn-primary"><i class="fa fa-plus"></i> Tambah trigger</a>@endcan
@endsection

@section('page')
    <div class="tw:flex tw:flex-wrap tw:gap-2 tw:mb-3">
        <a href="{{ route('antareja.triggers.index') }}" class="btn btn-sm {{ request('state') ? 'btn-default' : 'btn-primary' }}">Semua</a>
        <a href="{{ route('antareja.triggers.index', ['state' => 'problem']) }}" class="btn btn-sm {{ request('state') === 'problem' ? 'btn-primary' : 'btn-default' }}">Problem ({{ $counts['problem'] ?? 0 }})</a>
        <a href="{{ route('antareja.triggers.index', ['state' => 'ok']) }}" class="btn btn-sm {{ request('state') === 'ok' ? 'btn-primary' : 'btn-default' }}">OK ({{ $counts['ok'] ?? 0 }})</a>
        <a href="{{ route('antareja.triggers.index', ['state' => 'unknown']) }}" class="btn btn-sm {{ request('state') === 'unknown' ? 'btn-primary' : 'btn-default' }}">Tanpa data ({{ $counts['unknown'] ?? 0 }})</a>
    </div>
    <div class="panel panel-default">
        <div class="table-responsive">
            <table class="table table-condensed table-hover tw:m-0">
                <thead><tr><th>Status</th><th>Trigger</th><th>Ekspresi</th><th class="tw:text-right">Nilai</th><th>Severity</th><th>Sejak</th></tr></thead>
                <tbody>
                @forelse($triggers as $t)
                    <tr @class(['tw:opacity-60' => ! $t->enabled])>
                        <td>@if($t->enabled)<x-antareja::status :status="$t->stateMeta()['tone']" size="sm">{{ $t->stateMeta()['label'] }}</x-antareja::status>@else<x-antareja::status status="unknown" size="sm">Nonaktif</x-antareja::status>@endif</td>
                        <td><a href="{{ route('antareja.triggers.show', $t) }}">{{ $t->name }}</a></td>
                        <td class="ant-data tw:text-xs">{{ $t->expression($labels[$t->id]) }}{{ $units[$t->id] && ! $t->unit() ? ' ' . $units[$t->id] : '' }}</td>
                        <td class="tw:text-right ant-data">{{ \Antareja\Models\Trigger::fmt($t->last_value, $units[$t->id]) }}</td>
                        <td>@php($sv = \Antareja\Support\Severity::problem($t->severity))<x-antareja::status :status="$sv['tone']" size="sm">{{ $sv['label'] }}</x-antareja::status></td>
                        <td class="tw:text-xs ant-muted">{{ $t->state_changed_at?->diffForHumans() ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="tw:text-center tw:py-6 ant-muted">Belum ada trigger. Contoh: rata-rata packet loss link PTP 15 menit &gt; 5%, utilisasi uplink &gt; 80%, suhu switch &gt; 60 °C, uptime &lt; 600 detik (perangkat reboot).</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
