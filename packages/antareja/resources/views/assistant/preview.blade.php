@extends('antareja::layouts.page')

@section('title', $title)
@section('breadcrumb')
    <li><a href="{{ route('antareja.assistant.index') }}">Asisten</a></li>
    <li class="active">Pratinjau data</li>
@endsection
@section('heading', $title)
@section('subheading', 'Persis teks inilah yang dikirim ke model saat membuat draf. Tidak ada yang dikirim dari halaman ini.')

@section('page')
    <div class="panel panel-default"><pre class="ant-config-text tw:p-4 tw:whitespace-pre-wrap">{{ $context }}</pre></div>
    @unless($enabled)<p class="tw:text-sm ant-muted">Asisten AI belum aktif, jadi draf belum bisa dibuat — pratinjau tetap bisa dipakai sebagai ringkasan data mentah.</p>@endunless
@endsection
