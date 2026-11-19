@extends('antareja::layouts.page')

@section('title', 'Tambah Target dari Inventaris')
@section('breadcrumb')
    <li><a href="{{ route('antareja.quality.index') }}">Network Quality</a></li>
    <li class="active">Tambah dari inventaris</li>
@endsection
@section('heading', 'Tambah target dari inventaris')
@section('subheading', 'Perangkat infrastruktur (router, core/distribution switch, firewall, PTP/PTMP, controller, NVR, server) yang belum diukur.')

@section('page')
<form method="post" action="{{ route('antareja.quality.bulk.store') }}">
    @csrf
    <div class="panel panel-default">
        <div class="panel-body form-inline tw:flex tw:flex-wrap tw:gap-2 tw:items-center">
            <input name="group_name" class="form-control" maxlength="64" placeholder="Grup (kosong = per kategori)">
            <button class="btn btn-primary" @disabled($candidates->isEmpty())><i class="fa fa-plus"></i> Tambahkan yang dicentang</button>
            <span class="tw:text-xs ant-muted">Ambang default: latency 100/250 ms, loss 5/20%, jitter 30 ms. Bisa diubah per target.</span>
        </div>
    </div>
    <table class="table table-condensed table-hover">
        <thead><tr><th><input type="checkbox" onclick="document.querySelectorAll('.dev-check').forEach(c => c.checked = this.checked)"></th><th>Perangkat</th><th>IP</th><th>Kategori</th><th>Site</th><th>Status</th></tr></thead>
        <tbody>
        @forelse($candidates as $d)
            <tr>
                <td><input type="checkbox" class="dev-check" name="devices[]" value="{{ $d->device_id }}" checked></td>
                <td>{{ $d->device->displayName() }}</td>
                <td class="tw:font-mono tw:text-xs">{{ $d->device->overwrite_ip ?: $d->device->ip }}</td>
                <td>{{ $d->categoryLabel() }}</td>
                <td>{{ $d->site?->code ?? '—' }}</td>
                <td><x-antareja::device-status :device="$d->device" /></td>
            </tr>
        @empty
            <tr><td colspan="6" class="tw:text-center tw:py-6 ant-muted">Tidak ada kandidat. Pastikan kategori perangkat sudah diisi di Device Inventory.</td></tr>
        @endforelse
        </tbody>
    </table>
</form>
@endsection
