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
        <span class="ant-topo__spacer"></span>
        <button type="button" class="btn btn-default btn-sm" id="t-fit" title="Tampilkan semua"><i class="fa-solid fa-expand"></i></button>
        @if($canEdit)
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
        <aside class="ant-topo__panel" id="t-panel" hidden>
            <button type="button" class="ant-topo__close" id="t-close" aria-label="Tutup"><i class="fa-solid fa-xmark"></i></button>
            <div id="t-detail"></div>
        </aside>
        <div class="ant-topo__empty" id="t-empty" hidden>
            <i class="fa-solid fa-diagram-project"></i>
            <p><b>Belum ada sambungan yang diketahui.</b><br>Aktifkan LLDP/CDP di switch & router, atau buat mapping di menu Mapping Perangkat (mis. kamera → port switch).
                Perangkat tetap tampil sebagai titik tanpa garis.</p>
        </div>
    </div>
</div>

@include('antareja::topology.graph-script')
<script>
(function () {
    var site = document.getElementById('t-site'), group = document.getElementById('t-group');
    var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };
    var topo = AntTopology.create(document.getElementById('t-canvas'), {
        theme: document.documentElement.classList.contains('dark') ? 'dark' : 'light',
        dataUrl: function () { return '{{ route('antareja.topology.data') }}?site_id=' + site.value + '&group_id=' + group.value; },
        onData: function (d) {
            var s = d.graph.stats;
            document.getElementById('t-stats').innerHTML = '<b>' + s.nodes + '</b> perangkat · <b>' + s.edges + '</b> sambungan'
                + (s.down ? ' · <b class="ant-text-crit">' + s.down + ' down</b>' : '') + (s.unlinked ? ' · ' + s.unlinked + ' tanpa sambungan' : '');
            document.getElementById('t-empty').hidden = s.edges > 0 || s.nodes === 0;
        },
        onSelect: function (n, links) {
            var panel = document.getElementById('t-panel');
            if (!n) { panel.hidden = true; return; }
            var st = { up: ['ok', 'Online'], down: ['down', 'Down'], off: ['unknown', 'Nonaktif'] }[n.state];
            var rows = links.map(function (l) {
                var e = l.edge, bps = AntTopology.fmtBps(e.bps);
                return '<li><span class="ant-dotc ant-fill--' + (e.state === 'unknown' ? 'unknown' : e.state) + '"></span><div><b>' + esc(l.node ? l.node.label : '?') + '</b>'
                    + '<small>' + esc(l.localPort || 'port ?') + (bps ? ' · ' + bps + ' (' + e.util + '%)' : '') + '</small></div></li>';
            }).join('');
            document.getElementById('t-detail').innerHTML =
                '<p class="ant-eyebrow">' + esc(n.group) + (n.site ? ' · ' + esc(n.site) : '') + '</p>'
                + '<h3>' + esc(n.label) + '</h3><p class="ant-data ant-muted">' + esc(n.ip) + '</p>'
                + '<p><span class="ant-badge ant-badge--' + st[0] + '">' + st[1] + '</span>' + (n.uptime ? ' <span class="ant-muted">uptime ' + esc(n.uptime) + '</span>' : '') + '</p>'
                + (n.hardware ? '<p class="ant-muted">' + esc(n.hardware) + '</p>' : '')
                + '<h4>Terhubung ke (' + links.length + ')</h4>' + (rows ? '<ul class="ant-topo__links">' + rows + '</ul>' : '<p class="ant-muted">Tidak ada sambungan yang diketahui.</p>')
                + '<div class="ant-topo__actions"><a class="btn btn-primary btn-sm" href="' + n.url + '">Buka perangkat</a>'
                + ' <a class="btn btn-default btn-sm" href="{{ url('antareja/inventory') }}/' + n.id + '">Inventaris</a></div>';
            panel.hidden = false;
        }
    });
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
