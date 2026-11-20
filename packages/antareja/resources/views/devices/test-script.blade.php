{{-- Hasil "Uji koneksi" (ping + SNMP). AntConnTest.run(url, payload, targetEl) — kredensial tidak pernah dikembalikan server. --}}
@once
<script>
window.AntConnTest = (function () {
    var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };
    function row(ok, title, text, extra) {
        var tone = ok === null ? 'unknown' : (ok ? 'ok' : 'crit');
        var label = ok === null ? 'Dilewati' : (ok ? 'OK' : 'Gagal');
        return '<li class="ant-ct__row"><span class="ant-badge ant-badge--sm ant-badge--' + tone + '">' + label + '</span>'
            + '<div><b>' + esc(title) + '</b><small>' + esc(text) + '</small>' + (extra || '') + '</div></li>';
    }
    function render(r, el, opts) {
        var html = '<div class="ant-ct__head ant-ct__head--' + (r.ok ? 'ok' : 'crit') + '"><i class="fa-solid ' + (r.ok ? 'fa-circle-check' : 'fa-triangle-exclamation') + '"></i> '
            + (r.ok ? 'Perangkat bisa dipantau.' : 'Ada masalah koneksi.') + '<span class="ant-data">' + esc(r.ip || r.host) + '</span></div><ul class="ant-ct">';
        html += row(r.resolve ? r.resolve.ok : null, 'Alamat', r.resolve ? r.resolve.text : '');
        html += row(r.ping ? r.ping.ok : null, 'Ping (ICMP)', r.ping ? r.ping.text : '—');
        if (r.snmp) {
            var tried = (r.snmp.tried || []).map(function (t) {
                return '<span class="ant-ct__try">' + (t.ok ? '✓' : '✗') + ' ' + esc(t.label) + ' · ' + t.ms + ' ms' + (t.ok ? '' : ' — ' + esc(t.error || '')) + '</span>';
            }).join('');
            var info = r.snmp.ok ? '<span class="ant-ct__try"><b>sysName:</b> ' + esc(r.snmp.sysName || '—') + '</span><span class="ant-ct__try">' + esc(r.snmp.sysDescr || '') + '</span>' : '';
            html += row(r.snmp.ok, 'SNMP', r.snmp.text, info + tried);
        } else {
            html += row(null, 'SNMP', 'Mode ping saja — SNMP tidak diuji.');
        }
        html += '</ul>';
        if (r.ping_only && r.snmp && r.snmp.ok) {
            html += '<div class="ant-ct__fix"><b>Perangkat ini terdaftar "ping saja", padahal SNMP-nya sudah terbaca.</b> Aktifkan SNMP agar port, trafik, dan sensor ikut terpantau.'
                + (r.can_enable ? '<button type="button" class="btn btn-primary btn-sm" data-enable-snmp><i class="fa-solid fa-bolt"></i> Aktifkan SNMP</button>' : '<br><small>Minta admin untuk mengaktifkannya.</small>') + '</div>';
        }
        if (r.hints && r.hints.length) {
            html += '<div class="ant-ct__hints"><b>Kemungkinan penyebab & solusi</b><ul>' + r.hints.map(function (h) { return '<li>' + esc(h) + '</li>'; }).join('') + '</ul></div>';
        }
        el.innerHTML = html; el.hidden = false;
        var btn = el.querySelector('[data-enable-snmp]');
        if (btn && opts.enableUrl) btn.addEventListener('click', function () {
            btn.disabled = true; btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Mengaktifkan…';
            post(opts.enableUrl, opts.payload()).then(function (x) {
                if (x.ok) { el.querySelector('.ant-ct__fix').innerHTML = '<i class="fa-solid fa-circle-check ant-text-ok"></i> ' + esc(x.data.message); toastr.success('SNMP diaktifkan.'); }
                else { btn.disabled = false; btn.textContent = 'Aktifkan SNMP'; toastr.error(x.data.message || 'Gagal mengaktifkan SNMP.'); }
            });
        });
    }
    function post(url, payload) {
        return fetch(url, { method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
            body: JSON.stringify(payload) }).then(function (r) { return r.json().catch(function () { return {}; }).then(function (d) { return { ok: r.ok, status: r.status, data: d }; }); });
    }
    function run(url, payload, el, opts) {
        opts = opts || {};
        el.hidden = false;
        el.innerHTML = '<div class="ant-ct__head"><i class="fa fa-spinner fa-spin"></i> Menguji ping & SNMP dari server ANTAREJA… (± 3–10 detik)</div>';
        return post(url, payload).then(function (x) {
            if (x.ok) { render(x.data, el, Object.assign({ payload: function () { return payload; } }, opts)); return x.data; }
            var msg = x.data.errors ? Object.values(x.data.errors).flat().join(' ') : (x.data.message || ('Gagal (HTTP ' + x.status + ')'));
            el.innerHTML = '<div class="ant-ct__head ant-ct__head--crit"><i class="fa-solid fa-triangle-exclamation"></i> ' + esc(msg) + '</div>';
        }).catch(function () { el.innerHTML = '<div class="ant-ct__head ant-ct__head--crit">Tidak bisa menghubungi server ANTAREJA.</div>'; });
    }
    return { run: run };
})();
</script>
@endonce
