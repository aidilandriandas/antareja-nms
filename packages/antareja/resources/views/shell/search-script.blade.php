{{-- Komponen pencarian global (Alpine), sama dengan resources/views/layouts/menu.blade.php LibreNMS agar perilaku & endpoint identik --}}
<script>
    document.addEventListener('alpine:init', () => {
        window.Alpine.data('globalSearch', () => ({
            query: '',
            results: [], // groups returned by each endpoint, indexed like endpoints, unset while pending
            open: false,
            navigateOnLoad: false,
            lastRunQuery: '',
            activeIndex: -1,
            seq: 0,
            controllers: [],
            endpoints: @js([
                route('ajax.search.devices'),
                route('ajax.search.ports'),
                route('ajax.search.fdb'),
                route('ajax.search.arp'),
                route('ajax.search.health'),
                route('ajax.search.routing'),
                route('ajax.search.logs'),
            ]),
            get groups() { return this.results.flat(); },
            get flat() { return this.groups.flatMap(g => g.results); },
            get pendingIndex() { return this.endpoints.findIndex((url, i) => this.results[i] === undefined); },
            get loading() { return this.pendingIndex !== -1; },
            run() {
                let q = this.query.trim();
                if (q === '') { this.reset(); return; }
                if (q === this.lastRunQuery) { this.open = true; return; }
                this.search(q);
            },
            search(q, devicesFirst = false) {
                this.cancel();
                this.lastRunQuery = q;
                this.open = true;
                this.navigateOnLoad = false;
                this.activeIndex = -1;
                this.results = [];
                let seq = this.seq;
                let all = this.endpoints.map((url, i) => i);
                if (!devicesFirst) {
                    this.fetchEndpoints(q, seq, all);
                    return;
                }
                // devices are what Enter almost always wants, only search everything else if there are none
                this.fetchEndpoints(q, seq, [0]).then(() => {
                    if (seq === this.seq && this.flat.length === 0) { this.fetchEndpoints(q, seq, all.slice(1)); }
                });
            },
            fetchEndpoints(q, seq, indexes) {
                return Promise.all(indexes.map(i => {
                    let controller = new AbortController();
                    this.controllers.push(controller);
                    return fetch(this.endpoints[i] + '?search=' + encodeURIComponent(q), {
                        signal: controller.signal,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    })
                        .then(r => r.json())
                        .then(data => data.groups || [])
                        .catch(() => [])
                        .then(groups => {
                            if (seq !== this.seq) { return; }
                            this.results[i] = groups;
                            if (this.navigateOnLoad) { this.navigateIfReady(); }
                        });
                }));
            },
            // navigate to the top result once every higher priority endpoint has answered
            navigateIfReady() {
                let pending = this.pendingIndex;
                let settled = pending === -1 ? this.results : this.results.slice(0, pending);
                let first = settled.flat().flatMap(g => g.results)[0];
                if (first) {
                    this.navigate(first.url);
                } else if (pending === -1) {
                    this.navigateOnLoad = false;
                }
            },
            goOrRun() {
                let q = this.query.trim();
                if (q === '') { return; }
                if (this.flat[this.activeIndex]) {
                    this.navigate(this.flat[this.activeIndex].url);
                    return;
                }
                if (q !== this.lastRunQuery) { this.search(q, true); }
                this.open = true;
                this.navigateOnLoad = true;
                this.navigateIfReady();
            },
            move(dir) {
                if (this.flat.length === 0) { return; }
                this.open = true;

                if (this.activeIndex === -1) {
                    this.activeIndex = dir > 0 ? 0 : this.flat.length - 1;
                } else {
                    this.activeIndex = (this.activeIndex + dir + this.flat.length) % this.flat.length;
                }

                this.$nextTick(() => {
                    let dropdown = this.$refs.dropdown;
                    if (!dropdown) { return; }
                    let items = dropdown.querySelectorAll('a');
                    let activeEl = items[this.activeIndex];
                    if (activeEl) {
                        activeEl.scrollIntoView({ block: 'nearest' });
                    }
                });
            },
            navigate(url) {
                if (!url) { return; }
                // free up connections still held by slower searches so the page request isn't queued behind them
                this.cancel();
                this.navigateOnLoad = false;
                window.location.href = url;
            },
            cancel() { this.seq++; this.controllers.forEach(c => c.abort()); this.controllers = []; },
            close() { this.open = false; this.navigateOnLoad = false; },
            reset() { this.cancel(); this.results = []; this.open = false; this.activeIndex = -1; this.navigateOnLoad = false; this.lastRunQuery = ''; },
        }));
    });
</script>
