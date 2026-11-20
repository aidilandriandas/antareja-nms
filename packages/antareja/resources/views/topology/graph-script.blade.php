{{--
    Komponen peta topologi ANTAREJA (vis-network bawaan LibreNMS). Dipakai halaman Peta Topologi & layar NOC.
    AntTopology.create(el, {dataUrl, theme: 'light'|'dark', flow: bool, labels: bool, onSelect(node|null), onData(data)})
--}}
@once
<script src="{{ asset('js/vis-network.min.js') }}"></script>
<script src="{{ asset('js/vis-data.min.js') }}"></script>
<script>
window.AntTopology = (function () {
    // Kode glyph Font Awesome 6 (solid) untuk ikon kategori
    var GLYPH = { 'route': '', 'network-wired': '', 'server': '', 'ethernet': '', 'wifi': '',
        'tower-broadcast': '', 'tower-cell': '', 'satellite-dish': '', 'shield-halved': '', 'video': '',
        'hard-drive': '', 'car-battery': '', 'microchip': '' };

    function palette(theme) {
        var css = getComputedStyle(document.documentElement);
        var v = function (n, f) { var x = css.getPropertyValue(n).trim(); return x || f; };
        if (theme === 'dark') {
            return { up: '#2ee6c5', down: '#ff4d4f', off: '#6b7280', ink: '#e6edf3', muted: '#8b98a5', bg: '#0b1015',
                ok: '#1fb89a', warn: '#f5a524', crit: '#ff4d4f', unknown: '#3b4754', glowUp: 'rgba(46,230,197,.45)', glowDown: 'rgba(255,77,79,.85)' };
        }
        return { up: v('--ant-ok', '#0b6e63'), down: v('--ant-crit-strong', '#b3121b'), off: v('--ant-border-strong', '#857e79'),
            ink: v('--ant-ink', '#1a1716'), muted: v('--ant-ink-muted', '#5a534e'), bg: v('--ant-surface-raised', '#fff'),
            ok: v('--ant-ok', '#0b6e63'), warn: v('--ant-warn', '#8f4700'), crit: v('--ant-crit', '#a8101a'), unknown: v('--ant-border-strong', '#857e79'),
            glowUp: 'rgba(0,0,0,0)', glowDown: 'rgba(179,18,27,.55)' };
    }

    function fmtBps(b) {
        if (b === null || b === undefined) return null;
        var u = ['bps', 'Kbps', 'Mbps', 'Gbps', 'Tbps'], i = 0;
        while (b >= 1000 && i < u.length - 1) { b /= 1000; i++; }
        return (b >= 100 ? b.toFixed(0) : b.toFixed(1)) + ' ' + u[i];
    }
    function stateText(s) { return { up: 'Online', down: 'DOWN', off: 'Nonaktif' }[s] || s; }

    function nodeStyle(n, P, opts) {
        var color = P[n.state] || P.off;
        return {
            id: n.id, level: n.level,
            label: opts.labels === false ? undefined : n.label,
            title: n.label + '\n' + n.ip + '\n' + n.group + (n.site ? ' · ' + n.site : '') + '\nStatus: ' + stateText(n.state) + (n.uptime ? '\nUptime: ' + n.uptime : ''),
            shape: 'icon',
            icon: { face: '"Font Awesome 6 Free"', weight: '900', code: GLYPH[n.icon] || GLYPH.microchip, size: n.level <= 1 ? 46 : (n.level >= 4 ? 30 : 38), color: color },
            font: { color: n.state === 'down' ? P.down : P.ink, size: 13, face: 'IBM Plex Sans, system-ui, sans-serif', strokeWidth: 3, strokeColor: P.bg, vadjust: -2 },
            shadow: { enabled: n.state !== 'off', color: n.state === 'down' ? P.glowDown : P.glowUp, size: n.state === 'down' ? 22 : 14, x: 0, y: 0 },
            raw: n
        };
    }
    function edgeStyle(e, P) {
        var color = { ok: P.ok, warn: P.warn, crit: P.crit, down: P.down, unknown: P.unknown }[e.state] || P.unknown;
        var width = e.speed >= 10e9 ? 4.5 : (e.speed >= 1e9 ? 3 : (e.speed ? 1.8 : 2));
        var bps = fmtBps(e.bps);
        var t = (e.a_port || '?') + ' ↔ ' + (e.b_port || '?')
            + (bps ? '\nTrafik: ' + bps + ' (' + e.util + '%)' : '')
            + (e.speed ? '\nKecepatan port: ' + fmtBps(e.speed) : '')
            + '\nSumber: ' + ({ verified: 'Mapping terverifikasi', suggested: 'Saran mapping (belum diverifikasi)', lldp: 'LLDP/CDP' }[e.source]);
        return { id: e.id, from: e.from, to: e.to, title: t, width: width, color: { color: color, highlight: color, hover: color, opacity: e.state === 'unknown' ? 0.6 : 1 },
            dashes: e.source === 'suggested' || e.state === 'down' ? [6, 6] : false, smooth: false, raw: e };
    }

    function create(el, opts) {
        var P = palette(opts.theme);
        var nodes = new vis.DataSet(), edges = new vis.DataSet();
        var network = null, timer = null, mode = opts.layout || 'auto', lastData = null, phase = 0, raf = null;

        function build(data) {
            var positioned = data.nodes.some(function (n) { return n.x !== null; });
            var hier = mode === 'hierarchy' || (mode === 'auto' && !positioned);
            nodes.clear(); edges.clear();
            nodes.add(data.nodes.map(function (n) {
                var s = nodeStyle(n, P, opts);
                if (!hier && n.x !== null) { s.x = n.x; s.y = n.y; }
                return s;
            }));
            edges.add(data.edges.map(function (e) { return edgeStyle(e, P); }));
            var options = {
                autoResize: true,
                interaction: { hover: true, tooltipDelay: 120, navigationButtons: false, keyboard: false, multiselect: false, dragNodes: !opts.readonly },
                nodes: { borderWidth: 0, chosen: { label: false, node: function (v) { v.size = v.size * 1.15; } } },
                edges: { selectionWidth: 2, hoverWidth: 1 },
                layout: hier ? { hierarchical: { enabled: true, direction: 'UD', levelSeparation: 140, nodeSpacing: 120, treeSpacing: 160, parentCentralization: true, sortMethod: 'directed', shakeTowards: 'roots' } } : { randomSeed: 7, improvedLayout: data.nodes.length < 150 },
                physics: hier ? false : (positioned && mode !== 'physics' ? false : { solver: 'forceAtlas2Based', forceAtlas2Based: { gravitationalConstant: -60, springLength: 120 }, stabilization: { iterations: 250 } })
            };
            if (network) network.destroy();
            network = new vis.Network(el, { nodes: nodes, edges: edges }, options);
            // Node tanpa posisi tersimpan (mode bebas): letakkan di dekat tetangganya
            if (!hier && positioned && mode !== 'physics') {
                var pos = network.getPositions();
                data.nodes.forEach(function (n) {
                    if (n.x !== null) return;
                    var nb = data.edges.find(function (e) { return (e.from === n.id || e.to === n.id); });
                    var other = nb ? (nb.from === n.id ? nb.to : nb.from) : null;
                    var base = other && pos[other] ? pos[other] : { x: 0, y: 0 };
                    network.moveNode(n.id, base.x + (Math.random() - .5) * 160, base.y + 90 + Math.random() * 60);
                });
            }
            network.once('afterDrawing', function () { network.fit({ animation: false }); });
            network.on('click', function (p) {
                if (!opts.onSelect) return;
                if (p.nodes.length) { opts.onSelect(nodes.get(p.nodes[0]).raw, connected(p.nodes[0])); } else { opts.onSelect(null); }
            });
            network.on('doubleClick', function (p) { if (p.nodes.length && !opts.readonly) window.location = nodes.get(p.nodes[0]).raw.url; });
            if (opts.flow) {
                network.on('afterDrawing', drawFlow);
                if (!raf) loop();
            } else {
                network.on('afterDrawing', drawPulse);
                if (!raf) loop();
            }
        }

        function connected(id) {
            return edges.get({ filter: function (e) { return e.from === id || e.to === id; } }).map(function (e) {
                var other = nodes.get(e.from === id ? e.to : e.from);
                return { node: other ? other.raw : null, edge: e.raw, localPort: e.from === id ? e.raw.a_port : e.raw.b_port };
            });
        }

        // Cincin berdenyut di sekitar perangkat down
        function drawPulse(ctx) {
            var pos = network.getPositions();
            var t = (Math.sin(phase / 12) + 1) / 2;
            nodes.forEach(function (n) {
                if (n.raw.state !== 'down' || !pos[n.id]) return;
                ctx.beginPath(); ctx.arc(pos[n.id].x, pos[n.id].y - 4, 24 + t * 14, 0, 2 * Math.PI);
                ctx.strokeStyle = P.down; ctx.globalAlpha = 0.65 - t * 0.55; ctx.lineWidth = 2.5; ctx.stroke(); ctx.globalAlpha = 1;
            });
        }
        // Mode NOC: titik trafik mengalir di sepanjang sambungan yang hidup + denyut perangkat down
        function drawFlow(ctx) {
            drawPulse(ctx);
            var pos = network.getPositions();
            edges.forEach(function (e) {
                var r = e.raw; if (r.state === 'down' || !pos[e.from] || !pos[e.to]) return;
                var a = pos[e.from], b = pos[e.to];
                var speed = r.util ? 0.004 + r.util / 100 * 0.02 : 0.003;
                var count = r.util && r.util > 40 ? 3 : 2;
                ctx.fillStyle = e.color.color; ctx.shadowColor = e.color.color; ctx.shadowBlur = 8;
                for (var i = 0; i < count; i++) {
                    var f = ((phase * speed) + i / count) % 1;
                    ctx.beginPath(); ctx.arc(a.x + (b.x - a.x) * f, a.y + (b.y - a.y) * f, 2.6, 0, 2 * Math.PI); ctx.fill();
                }
                ctx.shadowBlur = 0;
            });
        }
        function loop() {
            raf = requestAnimationFrame(function () {
                phase++;
                var animate = opts.flow || nodes.get({ filter: function (n) { return n.raw.state === 'down'; } }).length > 0;
                if (network && animate && !document.hidden) network.redraw();
                loop();
            });
        }

        function update(data) {
            lastData = data;
            if (!network || nodes.length === 0 || data.nodes.length !== nodes.length) { build(data); return; }
            var ids = {}; data.nodes.forEach(function (n) { ids[n.id] = 1; });
            if (nodes.getIds().some(function (id) { return !ids[id]; })) { build(data); return; }
            nodes.update(data.nodes.map(function (n) { var s = nodeStyle(n, P, opts); delete s.level; return s; }));
            var eids = {}; data.edges.forEach(function (e) { eids[e.id] = 1; });
            edges.remove(edges.getIds().filter(function (id) { return !eids[id]; }));
            edges.update(data.edges.map(function (e) { return edgeStyle(e, P); }));
        }

        var fontsReady = document.fonts && document.fonts.load
            ? Promise.all([document.fonts.load('900 24px "Font Awesome 6 Free"'), document.fonts.load('400 13px "IBM Plex Sans"')]).catch(function () {})
            : Promise.resolve();
        function load() {
            return fontsReady.then(function () { return fetch(opts.dataUrl(), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
                .then(function (data) { update(data.graph || data); if (opts.onData) opts.onData(data); return data; }); });
        }

        return {
            load: load,
            reload: function () { nodes.clear(); return load(); },
            every: function (sec) { clearInterval(timer); timer = setInterval(function () { if (!document.hidden) load().catch(function () {}); }, sec * 1000); },
            setLayout: function (m) { mode = m; if (lastData) build(lastData); },
            positions: function () { return network ? network.getPositions() : {}; },
            focus: function (id) { if (network) { network.selectNodes([id]); network.focus(id, { scale: 1.2, animation: { duration: 500 } }); } },
            fit: function () { if (network) network.fit({ animation: { duration: 400 } }); },
            select: function (ids) { if (network) { if (ids.length) network.selectNodes(ids); else network.unselectAll(); } },
            node: function (id) { var n = nodes.get(id); return n ? n.raw : null; },
            domPos: function (id) { if (!network) return null; var p = network.getPositions([id])[id]; return p ? network.canvasToDOM(p) : null; },
            ids: function () { return nodes.getIds(); },
            find: function (q) {
                q = q.toLowerCase();
                var hit = nodes.get({ filter: function (n) { return n.raw.label.toLowerCase().indexOf(q) >= 0 || n.raw.ip.toLowerCase().indexOf(q) >= 0; } })[0];
                if (hit) { this.focus(hit.id); if (opts.onSelect) opts.onSelect(hit.raw, connected(hit.id)); }
                return !!hit;
            },
            fmtBps: fmtBps
        };
    }
    return { create: create, fmtBps: fmtBps };
})();
</script>
@endonce
