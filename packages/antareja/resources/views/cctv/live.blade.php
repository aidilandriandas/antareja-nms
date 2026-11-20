@extends('antareja::layouts.page')

@section('title', 'Live CCTV')
@section('breadcrumb')
    <li><a href="{{ route('antareja.cctv.index') }}">CCTV Infrastructure</a></li>
    <li class="active">Live view</li>
@endsection
@section('heading', 'Live view CCTV')
@section('subheading', 'Snapshot diperbarui tiap 5 detik. Klik kamera untuk video langsung (sub-stream, hemat bandwidth). Video hanya ditarik dari NVR saat ada yang menonton.')

@section('actions')
    @can('ant-cctv.update')<a href="{{ route('antareja.cctv.streams') }}" class="btn btn-default"><i class="fa fa-cog"></i> Sumber stream</a>@endcan
@endsection

@section('page')
    <form method="get" class="tw:flex tw:flex-wrap tw:items-center tw:gap-2 tw:mb-3">
        <select name="site" class="form-control input-sm tw:w-auto" aria-label="Site" onchange="this.form.submit()">
            <option value="">Semua site</option>
            @foreach($sites as $s)<option value="{{ $s->id }}" @selected((string) request('site') === (string) $s->id)>{{ $s->code }} · {{ $s->name }}</option>@endforeach
        </select>
        <div class="btn-group" role="group" aria-label="Jumlah kolom">
            @foreach([2, 3, 4, 6] as $c)<a href="{{ request()->fullUrlWithQuery(['cols' => $c]) }}" class="btn btn-sm {{ $cols === $c ? 'btn-primary' : 'btn-default' }}">{{ $c }}×</a>@endforeach
        </div>
        <span class="tw:text-xs ant-muted tw:ml-auto">{{ $streams->count() }} kamera · snapshot otomatis berhenti saat tab tidak dilihat</span>
    </form>

    @if($streams->isEmpty())
        <div class="panel panel-default"><div class="panel-body ant-muted">Belum ada sumber stream. @can('ant-cctv.update')Tambahkan di <a href="{{ route('antareja.cctv.streams') }}">Sumber stream</a>.@endcan</div></div>
    @else
        <div class="ant-cctv-grid" style="--cols: {{ $cols }}">
            @foreach($streams as $s)
                @php($down = ! $s->device->status && ! $s->device->disabled)
                <figure class="ant-cctv-tile" data-stream="{{ $s->id }}" data-snapshot="{{ route('antareja.cctv.snapshot', $s) }}" data-player="{{ route('antareja.cctv.player', $s) }}" data-name="{{ $s->device->displayName() }}">
                    <button type="button" class="ant-cctv-tile__view" aria-label="Live {{ $s->device->displayName() }}" @disabled($down)>
                        <img src="{{ route('antareja.cctv.snapshot', $s) }}?w=640" alt="Snapshot {{ $s->device->displayName() }}" loading="lazy" width="640" height="360">
                        @if($down)<span class="ant-cctv-tile__overlay">Kamera down</span>@endif
                    </button>
                    <figcaption class="ant-cctv-tile__cap">
                        <span class="tw:truncate"><strong>{{ $s->device->displayName() }}</strong> <span class="ant-muted">{{ $siteCodes[$siteOf[$s->device_id] ?? 0] ?? '' }}</span></span>
                        @if($down)
                            <x-antareja::status status="down" size="sm">Down</x-antareja::status>
                        @elseif($s->health && $s->health !== 'ok')
                            <x-antareja::status :status="$s->healthMeta()['tone']" size="sm">{{ $s->healthMeta()['label'] }}</x-antareja::status>
                        @endif
                        <a href="{{ route('antareja.cctv.streams.show', $s) }}" class="tw:text-xs" aria-label="Detail {{ $s->device->displayName() }}"><i class="fa fa-info-circle"></i></a>
                    </figcaption>
                </figure>
            @endforeach
        </div>
    @endif

    {{-- Pemutar live --}}
    <dialog id="ant-live" class="ant-live-dialog" aria-labelledby="ant-live-title">
        <div class="ant-live-dialog__head">
            <strong id="ant-live-title">Live</strong>
            <label class="tw:text-xs tw:font-normal tw:m-0"><input type="checkbox" id="ant-live-main"> Kualitas penuh (main stream, boros bandwidth)</label>
            <button type="button" class="btn btn-default btn-sm" id="ant-live-close">Tutup</button>
        </div>
        <div id="ant-live-body" class="ant-live-dialog__body"></div>
        <p class="tw:text-xs ant-muted tw:m-0 tw:px-3 tw:pb-2" id="ant-live-note">Menghubungkan…</p>
    </dialog>

    <script type="module">
        import '{{ url('antareja/live/video-stream.js') }}';

        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        const dlg = document.getElementById('ant-live'), body = document.getElementById('ant-live-body'), note = document.getElementById('ant-live-note');
        const mainBox = document.getElementById('ant-live-main');
        let current = null;

        // Snapshot berkala hanya untuk tile yang terlihat, berhenti saat tab disembunyikan atau live dibuka
        const visible = new Set();
        const io = new IntersectionObserver(es => es.forEach(e => e.isIntersecting ? visible.add(e.target) : visible.delete(e.target)), { threshold: 0.1 });
        document.querySelectorAll('.ant-cctv-tile').forEach(t => io.observe(t));
        setInterval(() => {
            if (document.hidden || dlg.open) return;
            visible.forEach(t => { const img = t.querySelector('img'); img.src = t.dataset.snapshot + '?w=640&t=' + Date.now(); });
        }, 5000);

        async function openLive(tile) {
            current = tile;
            document.getElementById('ant-live-title').textContent = tile.dataset.name;
            body.innerHTML = ''; note.textContent = 'Menghubungkan…';
            if (!dlg.open) dlg.showModal();
            const r = await fetch(tile.dataset.player + (mainBox.checked ? '?main=1' : ''), { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } });
            const data = await r.json().catch(() => ({}));
            if (!r.ok) { note.textContent = data.error || 'Live view tidak tersedia.'; return; }
            const v = document.createElement('video-stream');
            v.mode = 'mse,mjpeg';          // MSE (H.264); MJPEG sebagai cadangan untuk H.265 / browser tanpa dukungan
            v.media = 'video';
            v.background = false;
            body.appendChild(v);
            v.src = data.ws;
            note.textContent = 'Live · ' + (mainBox.checked ? 'main stream' : 'sub-stream') + ' · video berhenti otomatis saat jendela ditutup';
        }
        function closeLive() { body.innerHTML = ''; current = null; if (dlg.open) dlg.close(); }

        document.querySelectorAll('.ant-cctv-tile__view').forEach(b => b.addEventListener('click', () => openLive(b.closest('.ant-cctv-tile'))));
        document.getElementById('ant-live-close').addEventListener('click', closeLive);
        dlg.addEventListener('close', () => { body.innerHTML = ''; });
        mainBox.addEventListener('change', () => current && openLive(current));
    </script>
@endsection
