@extends('antareja::layouts.page')

@section('title', 'Peta Topologi')
@section('breadcrumb')<li class="active">Peta Topologi</li>@endsection
@section('heading', 'Peta Topologi')
@section('subheading', 'Siapa terhubung ke siapa — dari mapping terverifikasi & LLDP/CDP. Warna garis = pemakaian bandwidth port.')
@section('actions')
    <a href="{{ route('antareja.noc') }}" class="btn btn-primary btn-sm" target="_blank" rel="noopener"><i class="fa-solid fa-display"></i> Layar NOC</a>
@endsection

@section('page')
<div class="ant-topo">
    <div class="ant-topo__bar">
        <select id="t-site" class="form-control input-sm" aria-label="Filter site">
            <option value="">Semua site</option>
            @foreach($sites as $s)<option value="{{ $s->id }}">{{ $s->code }} — {{ $s->name }}</option>@endforeach
        </select>
        <select id="t-group" class="form-control input-sm" aria-label="Filter grup">
            <option value="">Semua grup</option>
            @foreach($groups as $g)<option value="{{ $g->id }}">{{ $g->name }}</option>@endforeach
        </select>
        <div class="ant-seg ant-seg--sm" role="group" aria-label="Tata letak">
            <button type="button" data-layout="auto" aria-selected="true">Tersimpan / otomatis</button>
            <button type="button" data-layout="hierarchy" aria-selected="false">Hierarki</button>
            <button type="button" data-layout="physics" aria-selected="false">Bebas</button>
        </div>
        <form id="t-find" class="ant-topo__find"><input type="search" class="form-control input-sm" placeholder="Cari nama / IP di peta…" aria-label="Cari di peta"></form>
        <label class="ant-check ant-topo__live" title="Baca counter port langsung dari perangkat tiap 5 detik (SNMP)">
            <input type="checkbox" id="t-live" checked> Trafik live <span class="ant-live-dot" id="t-live-dot" hidden></span>
        </label>
        <span class="ant-topo__spacer"></span>
        <button type="button" class="btn btn-default btn-sm" id="t-fit" title="Tampilkan semua"><i class="fa-solid fa-expand"></i></button>
        @if($canEdit)
            <button type="button" class="btn btn-default btn-sm" id="t-connect" aria-pressed="false" title="Tarik garis antara dua perangkat"><i class="fa-solid fa-link"></i> Hubungkan perangkat</button>
            <button type="button" class="btn btn-default btn-sm" id="t-reset" title="Kembalikan tata letak otomatis"><i class="fa-solid fa-rotate-left"></i></button>
            <button type="button" class="btn btn-primary btn-sm" id="t-save"><i class="fa-solid fa-floppy-disk"></i> Simpan tata letak</button>
        @endif
    </div>

    <div class="ant-topo__body">
        <div class="ant-topo__canvas" id="t-canvas" aria-label="Peta topologi jaringan"></div>
        <div class="ant-topo__legend" aria-hidden="true">
            <span><i class="fa-solid fa-circle ant-text-ok"></i> Online</span>
            <span><i class="fa-solid fa-circle ant-text-crit"></i> Down</span>
            <span class="ant-topo__sep"></span>
            <span><b class="ant-ln ant-ln--ok"></b> &lt;70%</span>
            <span><b class="ant-ln ant-ln--warn"></b> 70–90%</span>
            <span><b class="ant-ln ant-ln--crit"></b> &gt;90%</span>
            <span><b class="ant-ln" style="background: var(--ant-border-strong)"></b> Tanpa data trafik</span>
            <span><b class="ant-ln ant-ln--dash"></b> Belum diverifikasi / putus</span>
        </div>
        <div class="ant-topo__stats" id="t-stats"></div>
        <div class="ant-topo__mode" id="t-mode" hidden role="status" aria-live="polite">
            <i class="fa-solid fa-link"></i> <span id="t-mode-text"></span>
            <button type="button" class="btn btn-default btn-xs" id="t-mode-cancel">Selesai</button>
        </div>
        <aside class="ant-topo__panel" id="t-panel" hidden>
            <button type="button" class="ant-topo__close" id="t-close" aria-label="Tutup"><i class="fa-solid fa-xmark"></i></button>
            <div id="t-detail"></div>
        </aside>
        <div class="ant-topo__empty" id="t-empty" hidden>
            <i class="fa-solid fa-diagram-project"></i>
            <p><b>Belum ada sambungan yang diketahui.</b><br>Aktifkan LLDP/CDP di switch & router, atau klik <b>Hubungkan perangkat</b> lalu klik dua perangkat yang tersambung.
                Perangkat tetap tampil sebagai titik tanpa garis.</p>
        </div>
    </div>
</div>

@if($canEdit)
<div class="modal fade" id="t-link-modal" tabindex="-1" role="dialog" aria-labelledby="t-link-title">
    <div class="modal-dialog" role="document">
        <form class="modal-content" id="t-link-form">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="t-link-title">Hubungkan perangkat</h4>
            </div>
            <div class="modal-body">
                <div class="ant-linkpair">
                    <div class="ant-linkpair__side">
                        <span class="ant-linkpair__role">Sisi atas (upstream)</span>
                        <b id="t-a-name"></b><small class="ant-data" id="t-a-ip"></small>
                        <label for="t-a-port" class="tw:mt-2">Port</label>
                        <select id="t-a-port" class="form-control input-sm"></select>
                    </div>
                    <button type="button" class="btn btn-default btn-sm ant-linkpair__swap" id="t-swap" title="Tukar atas / bawah"><i class="fa-solid fa-right-left"></i></button>
                    <div class="ant-linkpair__side">
                        <span class="ant-linkpair__role">Sisi bawah (downstream)</span>
                        <b id="t-b-name"></b><small class="ant-data" id="t-b-ip"></small>
                        <label for="t-b-port" class="tw:mt-2">Port</label>
                        <select id="t-b-port" class="form-control input-sm"></select>
                    </div>
                </div>
                <div class="form-group tw:mt-4">
                    <label for="t-type">Jenis sambungan</label>
                    <select id="t-type" class="form-control">
                        @foreach($linkTypes as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
                    </select>
                    <p class="help-block">Sisi atas dianggap induk: bila induk down, alert perangkat di bawahnya diredam (tidak banjir notifikasi).</p>
                </div>
                <div class="form-group">
                    <label for="t-notes">Catatan <span class="ant-muted" style="font-weight:400">(opsional)</span></label>
                    <input id="t-notes" class="form-control" maxlength="2000" placeholder="mis. kabel FO core 3, patch panel R1-12">
                </div>
                @if($canVerify)
                    <label class="ant-check"><input type="checkbox" id="t-verify" checked> Tandai sudah diverifikasi (saya sudah cek di lapangan)</label>
                @else
                    <p class="help-block">Sambungan akan tampil putus-putus sampai diverifikasi engineer yang berwenang.</p>
                @endif
                <p class="ant-text-crit tw:mt-2 tw:mb-0" id="t-link-err" hidden></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary" id="t-link-save"><i class="fa-solid fa-link"></i> Simpan sambungan</button>
            </div>
        </form>
    </div>
</div>
@endif

@include('antareja::topology.graph-script')
<script>
(function () {
    var site = document.getElementById('t-site'), group = document.getElementById('t-group');
    var canEdit = {{ $canEdit ? 'true' : 'false' }};
    var connect = { on: false, pick: function () {} };
    var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };
    var topo = AntTopology.create(document.getElementById('t-canvas'), {
        theme: document.documentElement.classList.contains('dark') ? 'dark' : 'light',
        dataUrl: function () { return '{{ route('antareja.topology.data') }}?site_id=' + site.value + '&group_id=' + group.value; },
        live: true, liveUrl: '{{ route('antareja.topology.live') }}',
        onLive: function (n) { document.getElementById('t-live-dot').hidden = n === 0; },
        onData: function (d) {
            var s = d.graph.stats;
            document.getElementById('t-stats').innerHTML = '<b>' + s.nodes + '</b> perangkat · <b>' + s.edges + '</b> sambungan'
                + (s.down ? ' · <b class="ant-text-crit">' + s.down + ' down</b>' : '') + (s.unlinked ? ' · ' + s.unlinked + ' tanpa sambungan' : '');
            document.getElementById('t-empty').hidden = s.edges > 0 || s.nodes === 0;
        },
        onSelect: function (n, links) {
            if (connect.on) { return connect.pick(n); }
            var panel = document.getElementById('t-panel');
            if (!n) { panel.hidden = true; return; }
            var st = { up: ['ok', 'Online'], down: ['down', 'Down'], off: ['unknown', 'Nonaktif'] }[n.state];
            var rows = links.map(function (l) {
                var e = l.edge, bps = e.down_bps != null ? '▼ ' + AntTopology.fmtBps(e.down_bps) + ' ▲ ' + AntTopology.fmtBps(e.up_bps) + (e.live ? ' ●' : '') : null;
                var src = { verified: 'mapping', suggested: 'belum diverifikasi', lldp: 'LLDP/CDP' }[e.source];
                var del = (canEdit && e.link_id) ? '<button type="button" class="ant-topo__del" data-link="' + e.link_id + '" data-name="' + esc(l.node ? l.node.label : '') + '" title="Hapus sambungan"><i class="fa-solid fa-trash"></i></button>' : '';
                return '<li><span class="ant-dotc ant-fill--' + (e.state === 'unknown' ? 'unknown' : e.state) + '"></span><div><b>' + esc(l.node ? l.node.label : '?') + '</b>'
                    + '<small>' + esc(l.localPort || 'port ?') + (bps ? ' · ' + bps + (e.util != null ? ' (' + e.util + '%)' : '') : '') + ' · ' + src + '</small></div>' + del + '</li>';
            }).join('');
            document.getElementById('t-detail').innerHTML =
                '<p class="ant-eyebrow">' + esc(n.group) + (n.site ? ' · ' + esc(n.site) : '') + '</p>'
                + '<h3>' + esc(n.label) + '</h3><p class="ant-data ant-muted">' + esc(n.ip) + '</p>'
                + '<p><span class="ant-badge ant-badge--' + st[0] + '">' + st[1] + '</span>' + (n.uptime ? ' <span class="ant-muted">uptime ' + esc(n.uptime) + '</span>' : '') + '</p>'
                + (n.hardware ? '<p class="ant-muted">' + esc(n.hardware) + '</p>' : '')
                + '<h4>Terhubung ke (' + links.length + ')</h4>' + (rows ? '<ul class="ant-topo__links">' + rows + '</ul>' : '<p class="ant-muted">Tidak ada sambungan yang diketahui.</p>')
                + '<div class="ant-topo__actions"><a class="btn btn-primary btn-sm" href="' + n.url + '">Buka perangkat</a>'
                + ' <a class="btn btn-default btn-sm" href="{{ url('antareja/devices') }}/' + n.id + '">Pengaturan</a></div>';
            panel.hidden = false;
        }
    });
    window.AntTopologyPage = topo;
    document.getElementById('t-live').addEventListener('change', function () { topo.setLive(this.checked); if (!this.checked) document.getElementById('t-live-dot').hidden = true; });
    topo.load(); topo.every(30);
    site.addEventListener('change', function () { topo.reload(); });
    group.addEventListener('change', function () { topo.reload(); });
    document.getElementById('t-fit').addEventListener('click', function () { topo.fit(); });
    document.getElementById('t-close').addEventListener('click', function () { document.getElementById('t-panel').hidden = true; });
    document.querySelectorAll('[data-layout]').forEach(function (b) {
        b.addEventListener('click', function () {
            document.querySelectorAll('[data-layout]').forEach(function (x) { x.setAttribute('aria-selected', x === b ? 'true' : 'false'); });
            topo.setLayout(b.dataset.layout);
        });
    });
    document.getElementById('t-find').addEventListener('submit', function (e) {
        e.preventDefault(); var q = this.querySelector('input').value.trim();
        if (q && !topo.find(q)) toastr.warning('Tidak ditemukan di peta: ' + q);
    });
    @if($canEdit)
    // ---- Hubungkan dua perangkat dari peta
    (function () {
        var btn = document.getElementById('t-connect'), mode = document.getElementById('t-mode'), text = document.getElementById('t-mode-text');
        var first = null, pair = null;
        var LEVEL = { firewall: 0, router: 0, 'core-switch': 1, 'distribution-switch': 2, switch: 2, 'wireless-controller': 2, ptp: 2, ptmp: 2,
            server: 3, nvr: 3, 'access-switch': 3, ups: 3, 'wireless-ap': 4, 'cctv-camera': 4, other: 4 };
        function setMode(on) {
            connect.on = on; first = null; topo.select([]);
            btn.setAttribute('aria-pressed', on ? 'true' : 'false'); btn.classList.toggle('active', on);
            mode.hidden = !on; document.getElementById('t-panel').hidden = true;
            text.innerHTML = 'Klik perangkat <b>pertama</b> (mis. switch / NVR / router).';
        }
        function guessType(a, b) {
            if (b.category === 'cctv-camera') return a.category === 'nvr' ? 'nvr-camera' : 'cctv-port';
            if (b.category === 'wireless-ap') return 'ap-port';
            if (a.category === 'ptp' && b.category === 'ptp') return 'ptp-pair';
            if (a.category === 'ptmp') return 'ptmp-client';
            if (a.category === 'ups') return 'power';
            return 'uplink';
        }
        function loadPorts(sel, id) {
            sel.innerHTML = '<option value="">memuat port…</option>';
            fetch('{{ url('antareja/api/devices') }}/' + id + '/ports', { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : []; }).catch(function () { return []; })
                .then(function (ports) {
                    sel.innerHTML = '<option value="">— tidak tahu / tanpa port —</option>' + ports.map(function (p) {
                        return '<option value="' + p.id + '">' + esc(p.label) + (p.up ? '' : ' (down)') + '</option>';
                    }).join('');
                    if (!ports.length) sel.options[0].text = '— tanpa data port (ping saja) —';
                });
        }
        function fill() {
            document.getElementById('t-a-name').textContent = pair.a.label; document.getElementById('t-a-ip').textContent = pair.a.ip;
            document.getElementById('t-b-name').textContent = pair.b.label; document.getElementById('t-b-ip').textContent = pair.b.ip;
            loadPorts(document.getElementById('t-a-port'), pair.a.id); loadPorts(document.getElementById('t-b-port'), pair.b.id);
            document.getElementById('t-type').value = guessType(pair.a, pair.b);
        }
        connect.pick = function (n) {
            if (!n) return;
            if (!first) { first = n; topo.select([n.id]); text.innerHTML = '<b>' + esc(n.label) + '</b> dipilih — klik perangkat yang <b>tersambung</b> dengannya.'; return; }
            if (n.id === first.id) return;
            var a = first, b = n;
            if ((LEVEL[a.category] ?? 4) > (LEVEL[b.category] ?? 4)) { var t = a; a = b; b = t; } // yang lebih "atas" jadi induk
            pair = { a: a, b: b }; fill();
            document.getElementById('t-link-err').hidden = true; document.getElementById('t-notes').value = '';
            $('#t-link-modal').modal('show');
        };
        document.getElementById('t-swap').addEventListener('click', function () { pair = { a: pair.b, b: pair.a }; fill(); });
        btn.addEventListener('click', function () { setMode(!connect.on); });
        document.getElementById('t-mode-cancel').addEventListener('click', function () { setMode(false); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && connect.on && !$('#t-link-modal').hasClass('in')) setMode(false); });
        $('#t-link-modal').on('hidden.bs.modal', function () {
            if (connect.on) { first = null; topo.select([]); text.innerHTML = 'Klik perangkat <b>pertama</b> berikutnya, atau klik <b>Selesai</b>.'; }
        });
        document.getElementById('t-link-form').addEventListener('submit', function (e) {
            e.preventDefault();
            var save = document.getElementById('t-link-save'), err = document.getElementById('t-link-err'), v = document.getElementById('t-verify');
            save.disabled = true;
            $.ajax({ url: '{{ route('antareja.topology.links.store') }}', type: 'POST', contentType: 'application/json', dataType: 'json',
                data: JSON.stringify({ a_device_id: pair.a.id, b_device_id: pair.b.id, a_port_id: document.getElementById('t-a-port').value || null,
                    b_port_id: document.getElementById('t-b-port').value || null, link_type: document.getElementById('t-type').value,
                    notes: document.getElementById('t-notes').value || null, verify: v ? v.checked : false }) })
                .done(function () {
                    $('#t-link-modal').modal('hide');
                    toastr.success(pair.a.label + ' → ' + pair.b.label + ' tersambung.');
                    topo.load();
                })
                .fail(function (x) {
                    var j = x.responseJSON || {};
                    err.textContent = j.errors ? Object.values(j.errors).flat().join(' ') : (j.message || 'Gagal menyimpan sambungan.');
                    err.hidden = false;
                })
                .always(function () { save.disabled = false; });
        });
        // Hapus sambungan (hanya mapping ANTAREJA; LLDP/CDP berasal dari perangkat)
        document.getElementById('t-detail').addEventListener('click', function (e) {
            var b = e.target.closest('[data-link]'); if (!b) return;
            if (!confirm('Hapus sambungan ke ' + b.dataset.name + '?')) return;
            $.ajax({ url: '{{ url('antareja/topology/links') }}/' + b.dataset.link, type: 'DELETE' })
                .done(function () { toastr.success('Sambungan dihapus.'); b.closest('li').remove(); topo.load(); })
                .fail(function () { toastr.error('Gagal menghapus sambungan.'); });
        });
    })();

    document.getElementById('t-save').addEventListener('click', function () {
        $.ajax({ url: '{{ route('antareja.topology.positions') }}', type: 'POST', contentType: 'application/json',
            data: JSON.stringify({ positions: topo.positions() }) })
            .done(function (r) { toastr.success('Tata letak disimpan (' + r.saved + ' perangkat). Layar NOC memakai tata letak ini.'); })
            .fail(function () { toastr.error('Gagal menyimpan tata letak.'); });
    });
    document.getElementById('t-reset').addEventListener('click', function () {
        if (!confirm('Kembalikan tata letak ke susunan otomatis (hierarki)?')) return;
        $.ajax({ url: '{{ route('antareja.topology.positions.reset') }}', type: 'POST' }).done(function () { topo.setLayout('hierarchy'); topo.reload(); toastr.success('Tata letak dikembalikan ke otomatis.'); });
    });
    @endif
})();
</script>
@endsection
