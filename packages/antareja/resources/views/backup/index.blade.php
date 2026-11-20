@extends('antareja::layouts.page')

@php($human = fn ($b) => \Antareja\Services\BackupService::human($b))
@section('title', 'Backup & Pemulihan')
@section('breadcrumb')<li class="active">Backup &amp; Pemulihan</li>@endsection
@section('heading', 'Backup & Pemulihan NMS')
@section('subheading', 'Simpan seluruh isi ANTAREJA — perangkat, kredensial, inventaris, alert, user, dan grafik — supaya bisa dipulihkan bila VM rusak.')
@section('actions')
    <form method="post" action="{{ route('antareja.backup.run') }}">@csrf
        <button class="btn btn-primary btn-sm" @disabled($running)><i class="fa-solid fa-box-archive"></i> {{ $running ? 'Backup sedang berjalan…' : 'Backup sekarang' }}</button>
    </form>
@endsection

@section('page')
    {{-- Status singkat --}}
    <div class="ant-kpis">
        <div @class(['ant-kpi', 'ant-kpi--crit' => ! $lastOk || $lastOk->finished_at?->lt(now()->subDays(2))])>
            <span class="ant-kpi__label">Backup terakhir berhasil</span>
            <span class="ant-kpi__value" style="font-size: 26px">{{ $lastOk ? $lastOk->finished_at?->locale('id')->diffForHumans() : 'Belum ada' }}</span>
            <span class="ant-kpi__hint">{{ $lastOk ? $human($lastOk->size) . ' · ' . ($lastOk->summary['devices'] ?? '?') . ' perangkat' : 'Klik "Backup sekarang" untuk membuat yang pertama.' }}</span>
        </div>
        <div class="ant-kpi">
            <span class="ant-kpi__label">Jadwal otomatis</span>
            <span class="ant-kpi__value" style="font-size: 26px">{{ $settings->schedule_enabled ? 'Setiap hari ' . $settings->schedule_time : 'Nonaktif' }}</span>
            <span class="ant-kpi__hint">Simpan {{ $settings->keep_count }} backup terakhir di VM</span>
        </div>
        <div @class(['ant-kpi', 'ant-kpi--warn' => ! $settings->sftp_enabled])>
            <span class="ant-kpi__label">Salinan di luar VM</span>
            <span class="ant-kpi__value" style="font-size: 26px">{{ $settings->sftp_enabled ? 'SFTP aktif' : 'Belum diatur' }}</span>
            <span class="ant-kpi__hint">{{ $settings->sftp_enabled ? $settings->sftp_username . '@' . $settings->sftp_host . ':' . ($settings->sftp_path ?: '~') : 'Disarankan: salin otomatis ke NAS / server lain.' }}</span>
        </div>
        <div class="ant-kpi">
            <span class="ant-kpi__label">Ruang disk tersisa</span>
            <span class="ant-kpi__value" style="font-size: 26px">{{ $free ? $human((int) $free) : '?' }}</span>
            <span class="ant-kpi__hint ant-data">{{ $dir }}</span>
        </div>
    </div>

    @if(! $settings->sftp_enabled)
        <x-antareja::alert tone="warn">Backup yang hanya disimpan di VM ikut hilang bila disk VM rusak. Aktifkan <b>salinan SFTP</b> ke NAS / server lain di bawah, atau unduh backup secara berkala.</x-antareja::alert>
    @endif

    <div class="row">
        <div class="col-lg-7">
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Daftar backup</strong></div>
                @if($backups->isEmpty())
                    <div class="panel-body ant-muted">Belum ada backup.</div>
                @else
                    <table class="table table-hover tw:m-0">
                        <thead><tr><th>Waktu</th><th>Status</th><th>Ukuran</th><th>Isi</th><th>Salinan</th><th></th></tr></thead>
                        <tbody>
                        @foreach($backups as $b)
                            <tr>
                                <td><span title="{{ $b->filename }}">{{ $b->started_at?->format('d/m/Y H:i') }}</span>
                                    <br><small class="ant-muted">{{ $b->trigger === 'schedule' ? 'terjadwal' : 'manual' }}</small></td>
                                <td>
                                    @if($b->status === 'running')<x-antareja::status status="info" size="sm">Berjalan</x-antareja::status>
                                    @elseif($b->status === 'ok')<x-antareja::status status="ok" size="sm">Berhasil</x-antareja::status>
                                    @else<x-antareja::status status="crit" size="sm">Gagal</x-antareja::status>@endif
                                    @if($b->message)<br><small class="ant-text-crit">{{ \Illuminate\Support\Str::limit($b->message, 120) }}</small>@endif
                                </td>
                                <td class="ant-data">{{ $b->size ? $human($b->size) : '—' }}</td>
                                <td class="tw:text-sm">@if($b->summary){{ $b->summary['devices'] }} perangkat{{ $b->include_rrd ? ' + grafik' : '' }}@else — @endif</td>
                                <td>
                                    @if($b->offsite_status === 'ok')<x-antareja::status status="ok" size="sm">SFTP</x-antareja::status>
                                    @elseif($b->offsite_status === 'failed')<x-antareja::status status="crit" size="sm">Gagal</x-antareja::status>
                                    @else<span class="ant-muted">—</span>@endif
                                </td>
                                <td class="text-right tw:whitespace-nowrap">
                                    @if($b->status === 'ok' && $b->exists())
                                        <a href="{{ route('antareja.backup.download', $b) }}" class="btn btn-default btn-xs" title="Unduh"><i class="fa-solid fa-download"></i> Unduh</a>
                                    @endif
                                    @if($b->status !== 'running')
                                        <form method="post" action="{{ route('antareja.backup.destroy', $b) }}" class="tw:inline" onsubmit="return confirm('Hapus backup {{ $b->filename }}?')">@csrf @method('DELETE')
                                            <button class="btn btn-default btn-xs" title="Hapus"><i class="fa-solid fa-trash"></i></button></form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            <div class="panel panel-default">
                <div class="panel-heading"><strong><i class="fa-solid fa-life-ring"></i> Cara memulihkan</strong></div>
                <div class="panel-body tw:text-sm">
                    <p><b>VM yang sama</b> (mis. setelah salah konfigurasi) — di terminal VM:</p>
<pre class="ant-pre">cd /opt/antareja      # folder hasil git clone
./antareja.sh backups                       # lihat daftar backup di VM
./antareja.sh restore antareja-YYYYmmdd-HHMMSS.tar.gz</pre>
                    <p class="tw:mt-3"><b>VM baru</b> (VM lama rusak / hilang):</p>
                    <ol class="tw:pl-5 tw:space-y-1">
                        <li>Pasang ANTAREJA seperti biasa (<code>./antareja.sh install</code>), tidak perlu membuat admin.</li>
                        <li>Salin file backup ke VM baru, mis. dengan WinSCP ke <code>/opt/antareja/</code> (atau ambil dari NAS/server SFTP).</li>
                        <li>Jalankan <code>./antareja.sh restore /opt/antareja/antareja-YYYYmmdd-HHMMSS.tar.gz</code> lalu ketik <code>YA</code>.</li>
                        <li>Selesai — semua perangkat, grup, site, user, dan grafik kembali. Login dengan akun lama.</li>
                    </ol>
                    <p class="ant-muted tw:m-0">File backup berisi seluruh kredensial (SNMP, SSH, APP_KEY). Simpan di tempat yang aman dan jangan dibagikan.</p>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <form method="post" action="{{ route('antareja.backup.settings') }}" class="panel panel-default">
                @csrf @method('PUT')
                <div class="panel-heading"><strong>Pengaturan</strong></div>
                <div class="panel-body">
                    <label class="ant-check"><input type="checkbox" name="schedule_enabled" value="1" @checked(old('schedule_enabled', $settings->schedule_enabled))> Backup otomatis setiap hari</label>
                    <div class="row tw:mt-3">
                        <div class="form-group col-xs-6 @error('schedule_time') has-error @enderror">
                            <label for="schedule_time">Jam</label>
                            <input type="time" id="schedule_time" name="schedule_time" class="form-control" value="{{ old('schedule_time', $settings->schedule_time) }}">
                            @error('schedule_time')<p class="help-block">{{ $message }}</p>@enderror
                        </div>
                        <div class="form-group col-xs-6">
                            <label for="keep_count">Simpan di VM</label>
                            <div class="input-group"><input type="number" min="1" max="365" id="keep_count" name="keep_count" class="form-control" value="{{ old('keep_count', $settings->keep_count) }}"><span class="input-group-addon">backup</span></div>
                        </div>
                    </div>
                    <label class="ant-check"><input type="checkbox" name="include_rrd" value="1" @checked(old('include_rrd', $settings->include_rrd))> Sertakan grafik historis (RRD) — file lebih besar</label>

                    <hr>
                    <label class="ant-check"><input type="checkbox" name="sftp_enabled" value="1" @checked(old('sftp_enabled', $settings->sftp_enabled))> <b>Salin otomatis ke server lain (SFTP)</b></label>
                    <p class="help-block">NAS Synology/QNAP, server Linux, atau Windows dengan OpenSSH. Gunakan akun khusus backup.</p>
                    <div class="row">
                        <div class="form-group col-xs-8 @error('sftp_host') has-error @enderror"><label for="sftp_host">Host / IP</label>
                            <input id="sftp_host" name="sftp_host" class="form-control ant-data" value="{{ old('sftp_host', $settings->sftp_host) }}" placeholder="192.168.20.10">
                            @error('sftp_host')<p class="help-block">{{ $message }}</p>@enderror</div>
                        <div class="form-group col-xs-4"><label for="sftp_port">Port</label>
                            <input id="sftp_port" name="sftp_port" type="number" class="form-control" value="{{ old('sftp_port', $settings->sftp_port) }}"></div>
                        <div class="form-group col-xs-6 @error('sftp_username') has-error @enderror"><label for="sftp_username">Username</label>
                            <input id="sftp_username" name="sftp_username" class="form-control" value="{{ old('sftp_username', $settings->sftp_username) }}" autocomplete="off">
                            @error('sftp_username')<p class="help-block">{{ $message }}</p>@enderror</div>
                        <div class="form-group col-xs-6"><label for="sftp_path">Folder tujuan</label>
                            <input id="sftp_path" name="sftp_path" class="form-control ant-data" value="{{ old('sftp_path', $settings->sftp_path) }}" placeholder="/backup/antareja"></div>
                        <div class="form-group col-xs-12"><label for="sftp_secret">Password atau private key</label>
                            <textarea id="sftp_secret" name="sftp_secret" class="form-control ant-data" rows="2" autocomplete="off"
                                      placeholder="{{ $settings->sftp_secret ? '•••••• tersimpan (kosongkan bila tidak diubah)' : 'Password, atau tempel isi private key OpenSSH' }}"></textarea></div>
                        <div class="form-group col-xs-6"><label for="sftp_keep_count">Simpan di server tujuan</label>
                            <div class="input-group"><input type="number" min="1" id="sftp_keep_count" name="sftp_keep_count" class="form-control" value="{{ old('sftp_keep_count', $settings->sftp_keep_count) }}"><span class="input-group-addon">backup</span></div></div>
                        <div class="form-group col-xs-6"><label>Host key</label>
                            <p class="form-control-static ant-data tw:text-xs" style="word-break: break-all">{{ $settings->sftp_host_key ?: 'dicatat saat koneksi pertama' }}</p>
                            @if($settings->sftp_host_key)<label class="ant-check tw:text-xs"><input type="checkbox" name="reset_host_key" value="1"> lupakan host key</label>@endif
                        </div>
                    </div>
                </div>
                <div class="panel-footer tw:flex tw:justify-between tw:gap-2">
                    <button type="submit" formaction="{{ route('antareja.backup.test') }}" formmethod="post" name="_method" value="POST" class="btn btn-default" @disabled(! $settings->sftp_host)>
                        <i class="fa-solid fa-plug"></i> Uji koneksi</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>

    @if($running)
        <script>
            // Perbarui halaman saat backup selesai
            setInterval(function () {
                fetch('{{ route('antareja.backup.status') }}', { headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); })
                    .then(function (d) { if (!d.running) location.reload(); }).catch(function () {});
            }, 4000);
        </script>
    @endif
@endsection
