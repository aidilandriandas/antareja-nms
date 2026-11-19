{{-- Aksi verifikasi untuk satu device (dipakai di halaman detail inventaris) --}}
<div class="panel panel-default">
    <div class="panel-heading"><strong>Verifikasi</strong></div>
    <div class="panel-body tw:space-y-3">
        @if($detail->verification_status === 'pending')
            <form method="post" action="{{ route('antareja.discovery.verify', $device) }}">
                @csrf
                <button class="btn btn-success btn-block"><i class="fa fa-check"></i> Verifikasi sebagai inventaris resmi</button>
                <p class="help-block tw:text-xs tw:mb-0">Syarat: kategori, site, dan PIC sudah diisi.</p>
            </form>
            <details>
                <summary class="tw:cursor-pointer tw:text-sm tw:text-red-600">Tolak perangkat ini…</summary>
                <form method="post" action="{{ route('antareja.discovery.reject', $device) }}" class="tw:mt-2">
                    @csrf
                    <input name="reason" class="form-control tw:mb-2" required maxlength="255" placeholder="Alasan, mis. perangkat milik vendor / bukan aset ICT">
                    @can('device.update')
                        <label class="tw:font-normal tw:text-sm"><input type="checkbox" name="disable_polling" value="1"> Nonaktifkan polling perangkat ini</label>
                    @endcan
                    <button class="btn btn-danger btn-sm tw:mt-1"><i class="fa fa-times"></i> Tolak</button>
                </form>
            </details>
        @elseif($detail->verification_status === 'verified')
            <p class="tw:text-sm tw:m-0"><i class="fa fa-check-circle tw:text-emerald-600"></i> Inventaris resmi sejak {{ $detail->verified_at?->format('d M Y') }}.</p>
            <details>
                <summary class="tw:cursor-pointer tw:text-sm">Pensiunkan (retire) perangkat…</summary>
                <form method="post" action="{{ route('antareja.discovery.retire', $device) }}" class="tw:mt-2">
                    @csrf
                    <input name="reason" class="form-control tw:mb-2" required maxlength="255" placeholder="Alasan, mis. diganti unit baru SN …">
                    @can('device.update')
                        <label class="tw:font-normal tw:text-sm"><input type="checkbox" name="disable_polling" value="1" checked> Nonaktifkan polling perangkat ini</label>
                    @endcan
                    <button class="btn btn-default btn-sm tw:mt-1">Retire</button>
                </form>
            </details>
        @else
            <form method="post" action="{{ route('antareja.discovery.restore', $device) }}">
                @csrf
                <button class="btn btn-default btn-block"><i class="fa fa-undo"></i> Kembalikan ke antrean verifikasi</button>
            </form>
        @endif
    </div>
</div>
