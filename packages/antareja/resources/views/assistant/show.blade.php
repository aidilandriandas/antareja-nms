@extends('antareja::layouts.page')

@section('title', $report->title)
@section('breadcrumb')
    <li><a href="{{ route('antareja.assistant.index') }}">Asisten</a></li>
    @if($report->kind === 'incident' && $report->subject_id)<li><a href="{{ route('antareja.incidents.show', $report->subject_id) }}">Incident</a></li>@endif
    <li class="active">{{ $report->title }}</li>
@endsection
@section('heading', $report->title)
@section('subheading', 'Dibuat ' . $report->created_at->format('d M Y H:i') . ' oleh ' . ($report->creator?->username ?? '—') . ' · model ' . $report->model . ($report->fallback ? ' (cadangan)' : '') . ($report->output_tokens ? ' · ' . $report->input_tokens . '/' . $report->output_tokens . ' token' : ''))

@section('page')
    <x-antareja::alert tone="warn">Draf AI — periksa fakta dan dugaan sebelum dipakai atau dibagikan. AI tidak memeriksa perangkat secara langsung.</x-antareja::alert>
    <div class="panel panel-default">
        {{-- Markdown keluaran model dirender aman: HTML mentah dibuang, tautan berbahaya diblokir --}}
        <div class="panel-body ant-md tw:text-sm tw:leading-relaxed">{!! \Illuminate\Support\Str::markdown($report->content, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</div>
    </div>
    <details class="panel panel-default">
        <summary class="panel-heading tw:cursor-pointer"><strong>Data yang dikirim ke model</strong></summary>
        <pre class="ant-config-text tw:p-4 tw:whitespace-pre-wrap">{{ $report->context }}</pre>
    </details>
@endsection
