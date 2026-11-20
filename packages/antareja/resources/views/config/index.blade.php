@extends('antareja::layouts.page')

@php($P = \Antareja\Support\ConfigProfiles::class)

@section('title', 'Backup Konfigurasi')
@section('breadcrumb')<li class="active">Backup Konfigurasi</li>@endsection
@section('heading', 'Backup Konfigurasi')
@section('subheading', 'Konfigurasi perangkat diambil lewat SSH dengan perintah baca saja (show / display / export), disimpan per versi terenkripsi, dan dibandingkan. Perubahan di luar change request ditandai.')

@section('page')
    @php($totalUnauth = $unauth->sum())
    @if($totalUnauth)
        <x-antareja::alert tone="warn">{{ $totalUnauth }} versi konfigurasi berubah tanpa change request dan belum diverifikasi.</x-antareja::alert>
    @endif

    <div class="panel panel-default">
        <div class="panel-heading"><strong>Perangkat dibackup ({{ $targets->count() }})</strong></div>
        <div class="table-responsive">
            <table class="table table-condensed table-hover tw:m-0">
                <thead><tr><th>Perangkat</th><th>Profil</th><th>Kredensial</th><th>Status</th><th>Terakhir diambil</th><th>Terakhir berubah</th><th class="tw:text-right">Versi</th><th>Tanpa CHG</th></tr></thead>
                <tbody>
                @forelse($targets as $t)
                    <tr @class(['tw:opacity-60' => ! $t->enabled])>
                        <td><a href="{{ route('antareja.config.device', $t->device_id) }}">{{ $t->device?->displayName() }}</a></td>
                        <td class="tw:text-xs">{{ $P::PROFILES[$t->profile]['label'] ?? $t->profile }}</td>
                        <td class="tw:text-xs">{{ $t->credential?->name }}</td>
                        <td><x-antareja::status :status="$t->enabled ? $t->statusMeta()['tone'] : 'unknown'" size="sm">{{ $t->enabled ? $t->statusMeta()['label'] : 'Nonaktif' }}</x-antareja::status>
                            @if($t->last_error)<div class="tw:text-xs ant-text-crit tw:max-w-72 tw:truncate" title="{{ $t->last_error }}">{{ $t->last_error }}</div>@endif</td>
                        <td class="tw:text-xs">{{ $t->last_collected_at?->format('d M H:i') ?? '—' }}</td>
                        <td class="tw:text-xs">{{ $t->last_changed_at?->format('d M H:i') ?? '—' }}</td>
                        <td class="tw:text-right ant-data">{{ $versions[$t->device_id] ?? 0 }}</td>
                        <td>@if($unauth[$t->device_id] ?? 0)<x-antareja::status status="warn" size="sm">{{ $unauth[$t->device_id] }} versi</x-antareja::status>@else<span class="ant-muted">—</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="tw:text-center tw:py-6 ant-muted">Belum ada perangkat. Tambahkan kredensial lalu pilih perangkat di bawah.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="row">
        @can('ant-config.update')
            <div class="col-lg-7">
                <form method="post" action="{{ route('antareja.config.targets.store') }}" class="panel panel-default">
                    @csrf
                    <div class="panel-heading"><strong>Tambah perangkat ke backup</strong></div>
                    <div class="panel-body">
                        <div class="form-group">
                            <label for="devices">Perangkat</label>
                            <select id="devices" name="devices[]" class="form-control" multiple size="6" required>
                                @foreach($devices as $d)<option value="{{ $d->device_id }}">{{ $d->displayName() }} ({{ $d->overwrite_ip ?: $d->ip ?: $d->hostname }})</option>@endforeach
                            </select>
                        </div>
                        <div class="row">
                            <div class="form-group col-sm-5">
                                <label for="profile">Profil vendor</label>
                                <select id="profile" name="profile" class="form-control">@foreach($P::options() as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
                            </div>
                            <div class="form-group col-sm-4">
                                <label for="credential_id">Kredensial</label>
                                <select id="credential_id" name="credential_id" class="form-control" required>@foreach($credentials as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select>
                            </div>
                            <div class="form-group col-sm-3">
                                <label for="interval_hours">Interval (jam)</label>
                                <input id="interval_hours" name="interval_hours" type="number" min="1" max="168" value="24" class="form-control" required>
                            </div>
                        </div>
                        <button class="btn btn-primary" @disabled($credentials->isEmpty())><i class="fa fa-plus"></i> Tambahkan</button>
                        @if($credentials->isEmpty())<span class="tw:text-xs ant-muted tw:ml-2">Buat kredensial dulu.</span>@endif
                    </div>
                </form>
            </div>
        @endcan
        <div class="col-lg-5">
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Kredensial SSH</strong></div>
                <ul class="list-group">
                    @forelse($credentials as $c)
                        <li class="list-group-item tw:flex tw:items-center tw:justify-between tw:gap-2">
                            <span><strong>{{ $c->name }}</strong> <span class="tw:text-xs ant-muted">{{ $c->username }} · port {{ $c->port }} · {{ $c->targets_count }} perangkat</span></span>
                            @can('ant-config.update')
                                @if(! $c->targets_count)<form method="post" action="{{ route('antareja.config.credentials.destroy', $c) }}">@csrf @method('DELETE')<button class="btn btn-link btn-xs" aria-label="Hapus {{ $c->name }}"><i class="fa fa-times"></i></button></form>@endif
                            @endcan
                        </li>
                    @empty
                        <li class="list-group-item ant-muted">Belum ada.</li>
                    @endforelse
                </ul>
                @can('ant-config.update')
                    <form method="post" action="{{ route('antareja.config.credentials.store') }}" class="panel-body tw:flex tw:flex-col tw:gap-2" autocomplete="off">
                        @csrf
                        <input name="name" class="form-control" required maxlength="64" placeholder="Nama, mis. Switch Pit (read-only)" aria-label="Nama kredensial">
                        <div class="tw:flex tw:gap-2">
                            <input name="username" class="form-control" required maxlength="128" placeholder="Username" aria-label="Username" autocomplete="off">
                            <input name="secret" type="password" class="form-control" required maxlength="255" placeholder="Password" aria-label="Password" autocomplete="new-password">
                            <input name="port" type="number" min="1" max="65535" value="22" class="form-control tw:w-24" aria-label="Port">
                        </div>
                        <button class="btn btn-default tw:self-start">Simpan kredensial</button>
                        <p class="help-block tw:text-xs tw:m-0">Pakai akun <strong>read-only</strong> di perangkat. Password dienkripsi dan tidak pernah ditampilkan lagi.</p>
                    </form>
                @endcan
            </div>
        </div>
    </div>

    <div class="panel panel-default">
        <div class="panel-heading"><strong>Versi terbaru</strong></div>
        <div class="table-responsive">
            <table class="table table-condensed tw:m-0">
                <thead><tr><th>Waktu</th><th>Perangkat</th><th class="tw:text-right">Perubahan</th><th>Dasar</th></tr></thead>
                <tbody>
                @forelse($recent as $v)
                    <tr>
                        <td class="tw:text-xs tw:whitespace-nowrap">{{ $v->collected_at->format('d M Y H:i') }}</td>
                        <td><a href="{{ route('antareja.config.device', ['device' => $v->device_id, 'b' => $v->id]) }}">{{ $v->device?->displayName() }}</a></td>
                        <td class="tw:text-right ant-data"><span class="ant-text-ok">+{{ $v->lines_added }}</span> / <span class="ant-text-crit">−{{ $v->lines_removed }}</span></td>
                        <td>
                            @if($v->change)<a href="{{ route('antareja.changes.show', $v->change) }}">{{ $v->change->number }}</a>
                            @elseif($v->unauthorized)<x-antareja::status :status="$v->acknowledged_at ? 'info' : 'warn'" size="sm">{{ $v->acknowledged_at ? 'tanpa CHG · diverifikasi' : 'tanpa change request' }}</x-antareja::status>
                            @else<span class="ant-muted tw:text-xs">baseline / maintenance</span>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="ant-muted">Belum ada versi.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
<script>
    if (window.jQuery && jQuery.fn.select2) { jQuery('#devices').select2({ width: '100%', theme: 'bootstrap', placeholder: 'Pilih perangkat' }); }
</script>
@endsection
