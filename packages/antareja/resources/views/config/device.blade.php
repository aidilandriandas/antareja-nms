@extends('antareja::layouts.page')

@php
    $P = \Antareja\Support\ConfigProfiles::class;
    $pending = $versions->where('unauthorized', true)->whereNull('acknowledged_at');
@endphp

@section('title', 'Konfigurasi ' . $device->displayName())
@section('breadcrumb')
    <li><a href="{{ route('antareja.config.index') }}">Backup Konfigurasi</a></li>
    <li class="active">{{ $device->displayName() }}</li>
@endsection
@section('heading')Konfigurasi {{ $device->displayName() }}@endsection
@section('subheading')
    @if($target){{ $P::PROFILES[$target->profile]['label'] ?? $target->profile }} · tiap {{ $target->interval_hours }} jam · <x-antareja::status :status="$target->statusMeta()['tone']" size="sm">{{ $target->statusMeta()['label'] }}</x-antareja::status>@else Tidak dibackup lagi — versi lama tetap tersimpan.@endif
@endsection

@section('actions')
    @if($target)
        @can('ant-config.update')
            <form method="post" action="{{ route('antareja.config.collect', $device->device_id) }}">@csrf<button class="btn btn-primary"><i class="fa fa-download"></i> Ambil sekarang</button></form>
        @endcan
    @endif
    @can('ant-change.update')<a href="{{ route('antareja.changes.create', ['device' => $device->device_id]) }}" class="btn btn-default"><i class="fa fa-exchange-alt"></i> Ajukan perubahan</a>@endcan
@endsection

@section('page')
    @if($target?->last_error)<x-antareja::alert tone="crit">Pengambilan terakhir gagal: {{ $target->last_error }}</x-antareja::alert>@endif

    @if($pending->isNotEmpty())
        <div class="ant-alert ant-alert--warn tw:block" role="status">
            <strong>{{ $pending->count() }} versi berubah tanpa change request.</strong> Periksa diff di bawah. Bila perubahan memang sah, verifikasi dengan keterangan siapa yang mengubah dan alasannya.
            @can('ant-config.update')
                <form method="post" action="{{ route('antareja.config.acknowledge', $device->device_id) }}" class="tw:flex tw:gap-2 tw:mt-2">
                    @csrf
                    <input name="ack_note" class="form-control" required maxlength="500" placeholder="mis. Penambahan VLAN 30 oleh Budi saat gangguan malam, CHG menyusul" aria-label="Keterangan verifikasi">
                    <button class="btn btn-default tw:whitespace-nowrap">Verifikasi perubahan</button>
                </form>
            @endcan
        </div>
    @endif

    <div class="row">
        <div class="col-lg-8">
            @if($b)
                <div class="panel panel-default">
                    <div class="panel-heading tw:flex tw:flex-wrap tw:items-center tw:justify-between tw:gap-2">
                        <strong>
                            @if($a) Perubahan {{ $a->collected_at->format('d M H:i') }} → {{ $b->collected_at->format('d M H:i') }} @else Versi {{ $b->collected_at->format('d M Y H:i') }} (baseline) @endif
                        </strong>
                        <span class="tw:flex tw:gap-2 tw:text-sm">
                            @can('ant-config.update')
                                @if($reveal)<a href="{{ request()->fullUrlWithQuery(['reveal' => null]) }}">Samarkan rahasia</a>@else<a href="{{ request()->fullUrlWithQuery(['reveal' => 1]) }}">Tampilkan rahasia</a>@endif ·
                            @endcan
                            <a href="{{ route('antareja.config.download', ['version' => $b->id, 'reveal' => $reveal ? 1 : null]) }}"><i class="fa fa-download"></i> Unduh</a>
                        </span>
                    </div>
                    @if($hunks !== null)
                        @if(count($hunks) === 0)
                            <div class="panel-body ant-muted">Tidak ada perbedaan.</div>
                        @else
                            <div class="tw:overflow-x-auto">
                                <table class="ant-diff">
                                    @foreach($hunks as $h)
                                        @if(! $loop->first)<tr class="ant-diff__gap"><td colspan="3">⋯</td></tr>@endif
                                        @foreach($h as [$op, $line, $ln, $rn])
                                            <tr class="{{ $op === '+' ? 'ant-diff__add' : ($op === '-' ? 'ant-diff__del' : '') }}">
                                                <td class="ant-diff__ln">{{ $ln }}</td><td class="ant-diff__ln">{{ $rn }}</td>
                                                <td class="ant-diff__code"><span aria-hidden="true">{{ $op === ' ' ? ' ' : $op }}</span><span class="tw:sr-only">{{ $op === '+' ? 'ditambah: ' : ($op === '-' ? 'dihapus: ' : '') }}</span>{{ $line }}</td>
                                            </tr>
                                        @endforeach
                                    @endforeach
                                </table>
                            </div>
                        @endif
                    @endif
                </div>
                <details class="panel panel-default" @if(! $a) open @endif>
                    <summary class="panel-heading tw:cursor-pointer"><strong>Konfigurasi lengkap</strong> <span class="tw:text-xs ant-muted">{{ $b->line_count }} baris · {{ number_format($b->size / 1024, 1, ',', '.') }} KB{{ $reveal ? '' : ' · rahasia disamarkan' }}</span></summary>
                    <pre class="ant-config-text">{{ $text }}</pre>
                </details>
            @else
                <div class="panel panel-default"><div class="panel-body ant-muted">Belum ada versi. {{ $target ? 'Tekan "Ambil sekarang" untuk pengambilan pertama.' : '' }}</div></div>
            @endif
        </div>
        <div class="col-lg-4">
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Versi ({{ $versions->count() }})</strong></div>
                <div class="list-group tw:max-h-[480px] tw:overflow-y-auto">
                    @foreach($versions as $v)
                        <a href="{{ route('antareja.config.device', ['device' => $device->device_id, 'b' => $v->id]) }}" class="list-group-item tw:text-sm {{ $b && $b->id === $v->id ? 'active' : '' }}">
                            <span class="tw:flex tw:items-center tw:justify-between tw:gap-2">
                                <span>{{ $v->collected_at->format('d M Y H:i') }}</span>
                                <span class="ant-data tw:text-xs">+{{ $v->lines_added }} / −{{ $v->lines_removed }}</span>
                            </span>
                            <span class="tw:text-xs">
                                @if($v->change){{ $v->change->number }}@elseif($v->unauthorized){{ $v->acknowledged_at ? 'tanpa CHG · diverifikasi' : '⚠ tanpa change request' }}@elseif($loop->last) baseline @endif
                            </span>
                        </a>
                    @endforeach
                </div>
                @if($versions->count() > 1)
                    <form method="get" class="panel-body tw:flex tw:flex-wrap tw:items-end tw:gap-2">
                        <div><label for="a" class="tw:text-xs">Bandingkan</label>
                            <select id="a" name="a" class="form-control input-sm">@foreach($versions as $v)<option value="{{ $v->id }}" @selected($a?->id === $v->id)>{{ $v->collected_at->format('d M H:i') }}</option>@endforeach</select></div>
                        <div><label for="b" class="tw:text-xs">dengan</label>
                            <select id="b" name="b" class="form-control input-sm">@foreach($versions as $v)<option value="{{ $v->id }}" @selected($b?->id === $v->id)>{{ $v->collected_at->format('d M H:i') }}</option>@endforeach</select></div>
                        <button class="btn btn-default btn-sm">Diff</button>
                    </form>
                @endif
            </div>

            @if($target)
                @can('ant-config.update')
                    <form method="post" action="{{ route('antareja.config.targets.update', $device->device_id) }}" class="panel panel-default">
                        @csrf @method('PUT')
                        <div class="panel-heading"><strong>Pengaturan</strong></div>
                        <div class="panel-body">
                            <div class="form-group"><label for="profile">Profil</label><select id="profile" name="profile" class="form-control">@foreach($P::options() as $k => $l)<option value="{{ $k }}" @selected($target->profile === $k)>{{ $l }}</option>@endforeach</select>
                                <p class="help-block tw:text-xs">Perintah: <code>{{ implode(' ; ', $P::PROFILES[$target->profile]['commands'] ?? []) }}</code></p></div>
                            <div class="form-group"><label for="credential_id">Kredensial</label><select id="credential_id" name="credential_id" class="form-control">@foreach($credentials as $c)<option value="{{ $c->id }}" @selected($target->credential_id === $c->id)>{{ $c->name }}</option>@endforeach</select></div>
                            <div class="row">
                                <div class="form-group col-xs-7"><label for="host">Host SSH</label><input id="host" name="host" class="form-control" maxlength="255" value="{{ $target->host }}" placeholder="{{ $device->overwrite_ip ?: $device->hostname }}"></div>
                                <div class="form-group col-xs-5"><label for="interval_hours">Interval (jam)</label><input id="interval_hours" name="interval_hours" type="number" min="1" max="168" class="form-control" value="{{ $target->interval_hours }}"></div>
                            </div>
                            <p class="tw:text-xs ant-muted">Host key: <span class="ant-data">{{ $target->host_key ?? 'belum tersimpan' }}</span></p>
                            @if($target->host_key)<div class="checkbox"><label><input type="checkbox" name="reset_host_key" value="1"> Reset host key (perangkat diganti / di-reset)</label></div>@endif
                            <div class="checkbox"><label><input type="hidden" name="enabled" value="0"><input type="checkbox" name="enabled" value="1" @checked($target->enabled)> Aktif</label></div>
                            <button class="btn btn-default">Simpan</button>
                        </div>
                    </form>
                    <form method="post" action="{{ route('antareja.config.targets.destroy', $device->device_id) }}" onsubmit="return confirm('Hentikan backup perangkat ini? Versi lama tetap tersimpan.')">@csrf @method('DELETE')<button class="btn btn-link ant-text-crit">Hentikan backup</button></form>
                @endcan
            @endif
        </div>
    </div>
@endsection
