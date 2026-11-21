@extends('antareja::layouts.page')

@section('title', 'Modul Aktif')
@section('breadcrumb')<li class="active">Modul Aktif</li>@endsection
@section('heading', 'Modul Aktif')
@section('subheading', 'Pilih modul yang dipakai. Modul yang dimatikan hilang dari menu dan halamannya tidak bisa dibuka; datanya tetap tersimpan dan kembali bila dinyalakan lagi.')

@section('page')
<form method="post" action="{{ route('antareja.modules.update') }}">
    @csrf @method('PUT')
    <div class="panel panel-default">
        <div class="panel-heading tw:flex tw:flex-wrap tw:items-center tw:justify-between tw:gap-2">
            <strong>Modul opsional</strong>
            <span class="tw:flex tw:gap-2">
                <button type="submit" name="preset" value="noc" class="btn btn-default btn-sm"><i class="fa-solid fa-display"></i> Profil NOC (bawaan)</button>
                <button type="submit" name="preset" value="all" class="btn btn-default btn-sm"><i class="fa-solid fa-layer-group"></i> Nyalakan semua</button>
            </span>
        </div>
        <ul class="ant-modlist">
            @foreach($modules as $key => $desc)
                <li>
                    <label class="ant-check">
                        <input type="checkbox" name="enabled[]" value="{{ $key }}" @checked(! in_array($key, $disabled, true))>
                        <span><b>{{ \Illuminate\Support\Str::before($desc, ' —') }}</b>@if(str_contains($desc, ' — '))<small>{{ \Illuminate\Support\Str::after($desc, ' — ') }}</small>@endif</span>
                    </label>
                    @if(in_array($key, $noc, true))<span class="ant-badge ant-badge--sm ant-badge--unknown">mati di profil NOC</span>@endif
                </li>
            @endforeach
        </ul>
        <div class="panel-footer tw:flex tw:items-center tw:justify-between tw:gap-3">
            <span class="ant-muted tw:text-sm">Inti NOC selalu aktif: Pusat Operasi, Layar NOC, Peta Topologi, monitoring, problem & alert, insiden, maintenance, eskalasi, grup, site, mapping, backup NMS.</span>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Simpan</button>
        </div>
    </div>
</form>
@endsection
