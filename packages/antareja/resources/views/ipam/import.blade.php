@extends('antareja::layouts.page')

@section('title', 'Impor Subnet')
@section('breadcrumb')
    <li><a href="{{ route('antareja.ipam.index') }}">IPAM</a></li>
    <li class="active">Impor dari perangkat</li>
@endsection
@section('heading', 'Impor subnet dari perangkat')
@section('subheading', 'Network berikut terlihat di interface perangkat yang dimonitor, tetapi belum terdaftar di IPAM.')

@section('page')
<form method="post" action="{{ route('antareja.ipam.import.store') }}">
    @csrf
    <div class="panel panel-default">
        <div class="panel-body form-inline tw:flex tw:flex-wrap tw:gap-2 tw:items-center">
            <span>Terapkan ke yang dicentang:</span>
            <select name="site_id" class="form-control">
                <option value="">Site…</option>
                @foreach($sites as $site)<option value="{{ $site->id }}">{{ $site->code }} · {{ $site->name }}</option>@endforeach
            </select>
            <select name="purpose" class="form-control">
                <option value="">Fungsi…</option>
                @foreach(\Antareja\Models\Subnet::PURPOSES as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
            </select>
            <button class="btn btn-primary" @disabled($candidates->isEmpty())><i class="fa fa-file-import"></i> Impor</button>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-condensed table-hover">
            <thead><tr><th><input type="checkbox" onclick="document.querySelectorAll('.cidr-check').forEach(c => c.checked = this.checked)"></th><th>Network</th><th>Versi</th><th>Jumlah IP interface</th><th>Perangkat</th></tr></thead>
            <tbody>
            @forelse($candidates as $c)
                <tr>
                    <td><input type="checkbox" class="cidr-check" name="cidrs[]" value="{{ $c['cidr'] }}"></td>
                    <td class="tw:font-mono">{{ $c['cidr'] }}</td>
                    <td>IPv{{ $c['family'] }}</td>
                    <td>{{ $c['addresses'] }}</td>
                    <td class="tw:text-xs">{{ $c['devices'] }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="tw:text-center tw:py-6 ant-muted">Tidak ada network baru. Semua network interface sudah terdaftar.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</form>
@endsection
