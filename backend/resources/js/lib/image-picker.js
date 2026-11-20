// Product edit → "Find images" (resources/views/admin/products/_image-picker.blade.php). Free web image search + import of the chosen picture.
// Texts from the web (titles, sources, URLs) are set with textContent / attributes only, never as HTML.
const root = document.querySelector('[data-image-picker]');

if (root) {
    const cfg = JSON.parse(root.dataset.config);
    const t = cfg.i18n;
    const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
    const $ = (s, el = root) => el.querySelector(s);
    const isHttp = (u) => /^https?:\/\//i.test(u || '');
    const S = { target: cfg.targets[0], query: '', page: 1, results: [] };

    async function post(url, body) {
        try {
            const res = await fetch(url, { method: 'POST', credentials: 'same-origin', body: JSON.stringify(body),
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' } });
            const json = await res.json().catch(() => ({}));
            return { ok: res.ok, json };
        } catch {
            return { ok: false, json: {} };
        }
    }
    const errorOf = (json) => Object.values(json?.errors || {})[0]?.[0] || json?.message || t.error;

    // ---------------------------------------------------------------- open / close / target
    const sel = $('[data-ip-target]');
    sel.replaceChildren(...cfg.targets.map((x, i) => {
        const o = document.createElement('option');
        o.value = String(i);
        o.textContent = `${x.type === 'product' ? t.product : t.variant}: ${x.label}${x.sku ? ` (${x.sku})` : ''}`;
        return o;
    }));

    function setTarget(i) {
        S.target = cfg.targets[i] || cfg.targets[0];
        sel.value = String(cfg.targets.indexOf(S.target));
        $('[data-ip-query]').value = S.target.query || '';
        const th = $('[data-ip-thumb]');
        th.classList.toggle('hidden', !S.target.thumb);
        if (S.target.thumb) th.src = S.target.thumb;
        S.results = []; S.page = 1;
        render();
        message(t.empty);
    }

    function open(type, id) {
        const i = cfg.targets.findIndex((x) => x.type === type && (type === 'product' || String(x.id) === String(id)));
        setTarget(i < 0 ? 0 : i);
        root.classList.remove('hidden'); root.classList.add('flex');
        document.documentElement.classList.add('overflow-hidden');
        $('[data-ip-query]').focus();
    }
    function close() {
        root.classList.add('hidden'); root.classList.remove('flex');
        document.documentElement.classList.remove('overflow-hidden');
    }

    // ---------------------------------------------------------------- search
    function message(text) {
        const m = $('[data-ip-message]');
        m.textContent = text || '';
        m.classList.toggle('hidden', !text);
    }

    async function search(more = false) {
        const base = $('[data-ip-query]').value.trim();
        if (!base) return;
        S.query = base + ($('[data-ip-white]').checked ? ' white background' : '');
        S.page = more ? S.page + 1 : 1;
        const btn = $('[data-ip-submit]');
        btn.disabled = true;
        if (!more) { S.results = []; render(); }
        message(t.searching);
        const r = await post(cfg.search, { query: S.query, page: S.page });
        btn.disabled = false;
        if (!r.ok) { message(errorOf(r.json)); return; }
        const d = r.json.data;
        const seen = new Set(S.results.map((x) => x.id));
        S.results = [...S.results, ...d.results.filter((x) => !seen.has(x.id))];
        const errs = Object.keys(d.errors || {});
        const e = $('[data-ip-errors]');
        e.textContent = errs.map((name) => t.engine_failed.replace(':engine', name)).join(' · ');
        e.classList.toggle('hidden', !errs.length || !S.results.length);
        $('[data-ip-more]').classList.toggle('hidden', !d.has_more);
        message(S.results.length ? '' : t.no_results);
        render();
    }

    function render() {
        const tpl = $('[data-ip-card]');
        $('[data-ip-grid]').replaceChildren(...S.results.map((r) => {
            const li = tpl.content.firstElementChild.cloneNode(true);
            const img = $('[data-img]', li);
            if (isHttp(r.thumbnail)) img.src = r.thumbnail;
            img.addEventListener('error', () => { if (isHttp(r.image) && img.src !== r.image) img.src = r.image; }, { once: true });
            $('[data-title]', li).textContent = r.title || r.source || '—';
            const small = r.width && r.height && Math.min(r.width, r.height) < 500;
            const size = $('[data-size]', li);
            size.textContent = r.width ? `${r.width}×${r.height}${small ? ` · ${t.small}` : ''}` : t.size_unknown;
            if (small) size.classList.add('font-bold', 'text-amber-600');
            $('[data-source]', li).textContent = r.source || '';
            const page = $('[data-page]', li);
            if (isHttp(r.page)) page.href = r.page; else page.remove();
            const main = $('[data-act="main"]', li);
            main.textContent = S.target.type === 'variant' ? t.set_variant : t.set_main;
            main.addEventListener('click', () => importPicture(r, 'main', li));
            $('[data-act="gallery"]', li).addEventListener('click', () => importPicture(r, 'gallery', li));
            return li;
        }));
    }

    // ---------------------------------------------------------------- import
    async function importPicture(r, as, li) {
        const buttons = li.querySelectorAll('button');
        buttons.forEach((b) => { b.disabled = true; });
        const label = $(`[data-act="${as}"]`, li);
        const before = label.textContent;
        label.textContent = t.importing;
        const body = { image_url: r.image, page_url: r.page, title: r.title, as };
        if (as === 'main' && S.target.type === 'variant') body.variant_id = S.target.id;
        const res = await post(cfg.import, body);
        label.textContent = before;
        buttons.forEach((b) => { b.disabled = false; });
        if (!res.ok) { window.toastr?.error(errorOf(res.json)); return; }
        window.toastr?.success(res.json.message);
        window.toastr?.info(t.reload_hint);
        li.classList.add('ring-2', 'ring-emerald-500');
        if (as === 'main' && res.json.data?.image?.thumb) {
            S.target.thumb = res.json.data.image.thumb;
            $('[data-ip-thumb]').src = S.target.thumb;
            $('[data-ip-thumb]').classList.remove('hidden');
        }
    }

    // ---------------------------------------------------------------- events
    document.addEventListener('click', (e) => {
        const b = e.target.closest('[data-image-picker-open]');
        if (!b) return;
        e.preventDefault();
        open(b.dataset.targetType || 'product', b.dataset.targetId || null);
    });
    $('[data-ip-close]').addEventListener('click', close);
    root.addEventListener('click', (e) => { if (e.target === root) close(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !root.classList.contains('hidden')) close(); });
    sel.addEventListener('change', () => setTarget(Number(sel.value)));
    $('[data-ip-form]').addEventListener('submit', (e) => { e.preventDefault(); search(); });
    $('[data-ip-more]').addEventListener('click', () => search(true));
}
