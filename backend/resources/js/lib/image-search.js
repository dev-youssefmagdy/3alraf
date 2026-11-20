/**
 * Admin → Products: search by image (resources/views/admin/products/_image-search.blade.php, IMAGE_SEARCH_PLAN.md §9).
 * Opens from the camera button, Ctrl+V of an image anywhere on the page, or dropping an image anywhere on the page.
 * The photo is previewed from a local object URL and posted once; the server never stores it. Filters switch locally (no re-upload).
 */
import jq from 'jquery';

const root = document.querySelector('[data-image-search]');

if (root) {
    const cfg = JSON.parse(root.dataset.config);
    const t = cfg.i18n;
    const fmt = (s, r) => Object.entries(r).reduce((a, [k, v]) => a.replaceAll(':' + k, v), s);
    const nf = new Intl.NumberFormat('en-US');
    const $ = (sel, el = root) => el.querySelector(sel);
    const $$ = (sel, el = root) => [...el.querySelectorAll(sel)];
    const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
    const dialog = $('[data-is-dialog]');
    const input = $('[data-is-input]');
    const drop = $('[data-is-drop]');
    const filter = $('[data-is-filter]');
    const filterHome = filter.parentElement;
    const overlay = document.querySelector('[data-is-dragover]');
    const cardTpl = document.querySelector('template[data-is-card]');
    const opener = document.querySelector('[data-is-open]');

    let current = null;      // {file, url}
    let data = null;         // last search response
    let type = 'all';
    let lastFocus = null;
    let ctrl = null;

    // Camera button: next to the DataTables search box once the table has drawn its toolbar (stays in the page header otherwise).
    const placeOpener = () => {
        const box = document.querySelector('#products-table_wrapper .dt-search');
        if (opener && box && !box.contains(opener)) {
            box.classList.add('flex', 'items-center', 'gap-2');
            box.prepend(opener);
        }
    };
    jq(document).on('init.dt', placeOpener); // DataTables triggers its events through jQuery
    setTimeout(placeOpener, 0);

    const isOpen = () => !root.classList.contains('hidden');
    const open = () => {
        if (isOpen()) return;
        lastFocus = document.activeElement;
        root.classList.remove('hidden');
        root.classList.add('flex');
        document.documentElement.classList.add('overflow-hidden');
        loadCoverage();
        (current ? $('[data-is-another]') : drop).focus();
    };
    const close = () => {
        ctrl?.abort();
        root.classList.add('hidden');
        root.classList.remove('flex');
        document.documentElement.classList.remove('overflow-hidden');
        lastFocus?.focus?.();
    };

    const setState = (state) => {
        root.dataset.state = state;
        const empty = state === 'empty';
        $('[data-is-pane="empty"]').classList.toggle('hidden', !empty);
        $('[data-is-pane="query"]').classList.toggle('hidden', empty);
        $('[data-is-pane="query"]').classList.toggle('flex', !empty);
        for (const b of $$('[data-is-block]')) {
            const on = b.dataset.isBlock === state;
            b.classList.toggle('hidden', !on);
            b.classList.toggle('flex', on);
        }
        $('[data-is-results-head]').classList.toggle('hidden', !['searching', 'results'].includes(state));
        // the type filter lives in the empty pane before a search and in the results header after it
        (['searching', 'results'].includes(state) ? $('[data-is-filter-slot]') : filterHome).appendChild(filter);
    };

    const fileError = (msg) => {
        const p = $('[data-is-file-error]');
        p.querySelector('span').textContent = msg || '';
        p.classList.toggle('hidden', !msg);
        p.classList.toggle('flex', !!msg);
        drop.toggleAttribute('data-error', !!msg);
    };

    const extOf = (file) => ((file.name || '').split('.').pop() || file.type.split('/').pop() || '?').toUpperCase();
    const mb = (bytes) => Math.max(0.1, bytes / 1048576).toFixed(1);

    const validate = (file) => {
        const ext = extOf(file).toLowerCase();
        if (!cfg.mimes.includes(file.type) && !cfg.exts.includes(ext)) return fmt(t.wrong_type, { ext: extOf(file) });
        if (file.size > cfg.maxBytes) return fmt(t.too_large, { x: mb(file.size) });
        return null;
    };

    const start = (file) => {
        if (!file) return;
        open();
        const err = validate(file);
        if (err) {
            setState('empty');
            fileError(err);
            return;
        }
        fileError(null);
        if (current?.url) URL.revokeObjectURL(current.url);
        current = { file, url: URL.createObjectURL(file) };
        $('[data-is-preview]').src = current.url;
        $('[data-is-file-name]').textContent = file.name || 'clipboard.' + (file.type.split('/').pop() || 'png');
        $('[data-is-file-meta]').textContent = fmt(t.mb, { x: mb(file.size) }) + ' · ' + extOf(file);
        search();
    };

    const search = async () => {
        if (!current) return;
        ctrl?.abort();
        ctrl = new AbortController();
        setState('searching');
        $('[data-is-count]').textContent = t.searching;
        const body = new FormData();
        body.append('image', current.file, current.file.name || 'clipboard.png');
        body.append('type', 'all');
        let res;
        try {
            res = await fetch(cfg.search, { method: 'POST', body, signal: ctrl.signal, headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' } });
        } catch (e) {
            if (e.name === 'AbortError') return;
            setState('down');
            coverageState(false);
            return;
        }
        const json = await res.json().catch(() => ({}));
        if (res.status === 422) {
            setState('empty');
            fileError(json.errors?.image?.[0] || json.message || t.wrong_type.replace(':ext', extOf(current.file)));
            return;
        }
        if (res.status === 429) {
            setState('down');
            window.toastr?.warning(json.message || '');
            return;
        }
        if (!res.ok || !json.success) {
            setState('down');
            coverageState(false);
            return;
        }
        data = json.data;
        render();
    };

    const BADGE = {
        product: ['bg-panel-accent-soft', 'text-panel-accent-text'],
        variant: ['bg-[#ece7ff]', 'text-[#4b2bc9]'],
        bundle: ['bg-accent-light', 'text-[#7a5a22]'],
    };
    const MATCH = {
        strong: ['text-panel-success-fg', 'bg-panel-success-solid'],
        medium: ['text-panel-warning-fg', 'bg-panel-warning-solid'],
        weak: ['text-panel-text-subtle', 'bg-panel-text-subtle'],
    };

    const card = (r, best) => {
        const a = cardTpl.content.firstElementChild.cloneNode(true);
        a.href = r.url;
        const img = $('[data-c-img]', a);
        if (r.thumb) img.src = r.thumb; else img.remove();
        const badge = $('[data-c-badge]', a);
        badge.textContent = t['badge_' + r.type];
        badge.classList.add(...BADGE[r.type]);
        if (best) {
            const b = $('[data-c-best]', a);
            b.textContent = t.best;
            b.classList.remove('hidden');
        }
        $('[data-c-name]', a).textContent = r.name;
        $('[data-c-sub]', a).textContent = r.type === 'bundle' ? fmt(t.items, { n: nf.format(r.items ?? 0) }) : (r.sku ? fmt(t.sku, { sku: r.sku }) : '');
        const [txt, fill] = MATCH[r.match] || MATCH.weak;
        const label = $('[data-c-label]', a);
        label.textContent = t['match_' + r.match];
        label.classList.add(txt);
        const meter = $('[data-c-meter]', a);
        meter.setAttribute('aria-valuenow', String(r.strength));
        meter.setAttribute('aria-label', t['match_' + r.match]);
        const f = $('[data-c-fill]', a);
        f.style.width = Math.max(4, r.strength) + '%';
        f.classList.add(fill);
        return a;
    };

    const visible = () => {
        const rows = data?.results || [];
        const list = type === 'products' ? rows.filter((r) => r.type !== 'bundle') : type === 'bundles' ? rows.filter((r) => r.type === 'bundle') : rows;
        return list.slice(0, cfg.limit);
    };

    const render = () => {
        if (!data) return;
        for (const b of $$('[data-filter]', filter)) {
            const n = data.counts[b.dataset.filter] ?? 0;
            const c = b.querySelector('[data-count]');
            c.textContent = nf.format(n);
            c.classList.remove('hidden');
        }
        if (!data.results.length) {
            setState('none');
            return;
        }
        setState('results');
        const rows = visible();
        $('[data-is-count]').textContent = fmt(t.results, { n: nf.format(rows.length) });
        const low = $('[data-is-low]');
        low.classList.toggle('hidden', !data.low_confidence);
        low.classList.toggle('flex', !!data.low_confidence);
        const grid = $('[data-is-grid]');
        grid.replaceChildren(...rows.map((r) => card(r, r.best)));
    };

    const setType = (v) => {
        type = v;
        for (const b of $$('[data-filter]', filter)) b.setAttribute('aria-checked', String(b.dataset.filter === v));
        if (data && root.dataset.state !== 'empty') render();
    };

    const coverageState = (ok) => {
        const bar = $('[data-cov-bar]');
        $('[data-cov-icon="ok"]').classList.toggle('hidden', !ok);
        $('[data-cov-icon="warn"]').classList.toggle('hidden', ok);
        bar.classList.toggle('hidden', !ok);
        bar.classList.toggle('flex', ok);
        if (!ok) $('[data-cov-text]').textContent = t.coverage_unavailable;
    };

    const loadCoverage = async () => {
        const text = $('[data-cov-text]');
        const show = coverageState;
        try {
            const res = await fetch(cfg.coverage, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const json = await res.json();
            if (!res.ok || !json.success) throw new Error('coverage');
            const d = json.data;
            if (!d.service_up) throw new Error('down');
            text.textContent = d.complete ? fmt(t.coverage_done, { b: nf.format(d.total) }) : fmt(t.coverage, { a: nf.format(d.indexed), b: nf.format(d.total), n: nf.format(d.waiting) });
            $('[data-cov-fill]').style.width = d.percent + '%';
            $('[data-cov-progress]').setAttribute('aria-valuenow', String(d.percent));
            $('[data-cov-percent]').textContent = d.percent + '%';
            show(true);
        } catch {
            show(false);
        }
    };

    const imageFrom = (items) => {
        for (const it of items || []) {
            if (it.kind === 'file' && it.type.startsWith('image/')) return it.getAsFile();
        }
        return null;
    };
    const hasFiles = (e) => [...(e.dataTransfer?.types || [])].includes('Files');

    // --- events
    opener?.addEventListener('click', open);
    $('[data-is-close]').addEventListener('click', close);
    root.addEventListener('mousedown', (e) => { if (!dialog.contains(e.target)) close(); });
    drop.addEventListener('click', () => input.click());
    drop.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); } });
    $('[data-is-another]').addEventListener('click', () => input.click());
    input.addEventListener('change', () => { start(input.files[0]); input.value = ''; });
    $('[data-is-retry]').addEventListener('click', () => { search(); loadCoverage(); });
    $('[data-is-by-name]').addEventListener('click', () => {
        close();
        document.querySelector('#products-table_wrapper .dt-search input')?.focus();
    });
    filter.addEventListener('click', (e) => { const b = e.target.closest('[data-filter]'); if (b) setType(b.dataset.filter); });
    filter.addEventListener('keydown', (e) => {
        if (!['ArrowLeft', 'ArrowRight'].includes(e.key)) return;
        const chips = $$('[data-filter]', filter);
        const i = chips.findIndex((c) => c.getAttribute('aria-checked') === 'true');
        const step = (e.key === 'ArrowRight') === (document.documentElement.dir === 'rtl') ? -1 : 1;
        const next = chips[(i + step + chips.length) % chips.length];
        setType(next.dataset.filter);
        next.focus();
        e.preventDefault();
    });

    document.addEventListener('keydown', (e) => {
        if (!isOpen()) return;
        if (e.key === 'Escape') { e.preventDefault(); close(); return; }
        if (e.key === 'Tab') { // focus trap
            const f = $$('a[href], button:not([disabled]), [tabindex="0"], input:not([type=file])', dialog).filter((el) => el.offsetParent !== null);
            if (!f.length) return;
            if (e.shiftKey && document.activeElement === f[0]) { e.preventDefault(); f[f.length - 1].focus(); }
            else if (!e.shiftKey && document.activeElement === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
        }
    });

    // Paste an image anywhere on the page (text pastes into inputs are left alone).
    document.addEventListener('paste', (e) => {
        const file = imageFrom(e.clipboardData?.items);
        if (!file) return;
        e.preventDefault();
        start(file);
    });

    // Drop an image anywhere on the page.
    let depth = 0;
    const showOverlay = (on) => { overlay.classList.toggle('hidden', !on); overlay.classList.toggle('flex', on); };
    document.addEventListener('dragenter', (e) => { if (!hasFiles(e)) return; depth++; showOverlay(true); });
    document.addEventListener('dragleave', (e) => { if (!hasFiles(e)) return; depth = Math.max(0, depth - 1); if (!depth) showOverlay(false); });
    document.addEventListener('dragover', (e) => { if (hasFiles(e)) e.preventDefault(); });
    document.addEventListener('drop', (e) => {
        if (!hasFiles(e)) return;
        e.preventDefault();
        depth = 0;
        showOverlay(false);
        start(e.dataTransfer.files[0]);
    });

    setState('empty');
}
