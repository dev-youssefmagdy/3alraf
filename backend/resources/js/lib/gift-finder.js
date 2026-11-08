/* Gift Finder page: budget slider + presets, submits the answers, renders ONE ranked list of products and bundles (type filter / sort / relaxed-filter
   notice / near-budget section), per-item selection to a prepared cart, one-click "add bundle to cart" and a share link (token URL). Logical
   (RTL-safe) utilities only; results announced through the status live region. */
const root = document.getElementById('gf');
if (root) init(root);

function init(root) {
    const cfg = JSON.parse(root.dataset.config);
    const T = cfg.t;
    const form = root.querySelector('#gf-form');
    const out = root.querySelector('#gf-out');
    const status = root.querySelector('#gf-status');
    const submit = form.querySelector('[type=submit]');
    const budget = form.querySelector('[name=budget]');
    const range = form.querySelector('[data-range]');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const fmt = (v) => Number(v).toLocaleString(document.documentElement.lang || undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 });
    const money = (v) => `${esc(fmt(v))} ${esc(cfg.symbol)}`;
    const headers = { Accept: 'application/json', 'X-CSRF-TOKEN': csrf };
    const message = (cls, text) => { out.insertAdjacentHTML('afterbegin', `<p class="${cls} rounded-xl px-3 py-2 text-sm" role="alert">${esc(text)}</p>`); };
    const toast = (ok, text) => (window.toastr ? window.toastr[ok ? 'success' : 'error'](text) : (status.textContent = text));

    /* ---------- budget: number field <-> slider <-> presets ---------- */
    const syncPresets = () => form.querySelectorAll('[data-preset]').forEach((b) => b.setAttribute('aria-pressed', String(Number(b.dataset.preset) === Number(budget.value))));
    budget.addEventListener('input', () => { if (budget.value) range.value = Math.min(Number(range.max), Number(budget.value)); syncPresets(); });
    range.addEventListener('input', () => { budget.value = range.value; syncPresets(); });
    form.querySelectorAll('[data-preset]').forEach((b) => b.addEventListener('click', () => { budget.value = b.dataset.preset; range.value = Math.min(Number(range.max), Number(b.dataset.preset)); syncPresets(); }));

    /* ---------- cards ---------- */
    const pickers = {};
    let syncCart = () => {};
    const optionHtml = (r) => r.picker.options.map((o) => `<select data-opt="${o.id}" class="rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs" aria-label="${esc(o.name)}">
        ${o.values.map((v) => `<option value="${v.id}">${esc(o.name)}: ${esc(v.label)}</option>`).join('')}</select>`).join('');
    const checkbox = (key, value, name, extra = '') => `<input type="checkbox" class="mt-1 size-5 shrink-0 accent-brand-600" data-pick="${key}" value="${value}" ${extra} checked aria-label="${esc(T.select.replace(':name', name))}">`;

    const productCard = (r, selectable) => {
        const variable = !r.simple && r.picker;
        if (variable) pickers[r.key] = r.picker;
        const first = variable ? r.picker.variants[0] : null;
        const url = `${cfg.productUrl}/${encodeURIComponent(r.slug)}`;
        const check = selectable && (r.simple || variable) ? checkbox('product', r.product_id, r.name, variable ? `data-variant="${first.id}"` : '') : '';
        return `<article class="flex gap-3 rounded-2xl bg-white p-3 shadow-sm ring-1 ring-slate-100" data-card="${esc(r.key)}">
            ${check}
            <a href="${url}" class="shrink-0" tabindex="-1" aria-hidden="true">${r.image ? `<img src="${esc(r.image)}" class="size-20 rounded-xl object-cover" alt="" width="80" height="80" loading="lazy" data-img>` : '<span class="block size-20 rounded-xl bg-slate-100"></span>'}</a>
            <div class="min-w-0 flex-1 space-y-1">
                <h3 class="line-clamp-2 font-semibold"><a class="hover:text-brand-600" href="${url}">${esc(r.name)}</a></h3>
                <p class="text-sm"><b class="text-brand-700" data-price>${money(variable ? first.price : r.price)}</b>${r.over_budget ? ` <span class="ms-1 rounded bg-amber-100 px-1.5 text-xs font-semibold text-amber-800">${esc(T.over)}</span>` : ''}${selectable && !r.simple && !variable ? ` <a class="ms-1 text-xs text-brand-700 underline" href="${url}">${esc(T.choose_options)}</a>` : ''}</p>
                ${variable && selectable ? `<div class="flex flex-wrap gap-2">${optionHtml(r)}</div><p class="hidden text-xs text-red-600" data-unavail>${esc(T.unavailable_option)}</p>` : ''}
                ${r.explanation ? `<p class="text-sm leading-6 text-slate-600">${esc(r.explanation)}</p>` : ''}
            </div></article>`;
    };

    const bundleCard = (b, selectable) => {
        const url = `${cfg.bundleUrl}/${encodeURIComponent(b.slug)}`;
        const img = b.image || b.components?.[0]?.image;
        const saving = Number(b.savings) > 0;
        return `<article class="flex gap-3 rounded-2xl bg-white p-3 shadow-sm ring-1 ring-brand-100" data-card="${esc(b.key)}">
            ${selectable ? checkbox('bundle', b.bundle_id, b.name) : ''}
            <a href="${url}" class="shrink-0" tabindex="-1" aria-hidden="true">${img ? `<img src="${esc(img)}" class="size-20 rounded-xl object-cover" alt="" width="80" height="80" loading="lazy">` : '<span class="block size-20 rounded-xl bg-slate-100"></span>'}</a>
            <div class="min-w-0 flex-1 space-y-1.5">
                <p class="flex flex-wrap items-center gap-1"><span class="rounded bg-brand-50 px-2 py-0.5 text-xs font-bold text-brand-700">${esc(T.bundle)}</span>${saving ? `<span class="rounded bg-emerald-50 px-2 py-0.5 text-xs font-bold text-emerald-700">${esc(T.save.replace(':amount', fmt(b.savings) + ' ' + cfg.symbol))}</span>` : ''}</p>
                <h3 class="line-clamp-2 font-semibold"><a class="hover:text-brand-600" href="${url}">${esc(b.name)}</a></h3>
                <p class="text-sm"><b class="text-brand-700">${money(b.price)}</b>${saving ? ` <s class="ms-1 text-xs text-slate-400">${money(b.regular_total)}</s>` : ''}${b.over_budget ? ` <span class="ms-1 rounded bg-amber-100 px-1.5 text-xs font-semibold text-amber-800">${esc(T.over)}</span>` : ''}</p>
                <div><p class="sr-only">${esc(T.includes)}</p><ul class="flex flex-wrap gap-1.5">${(b.components || []).map((c) => `<li class="relative" title="${esc(c.name)}">
                    ${c.image ? `<img src="${esc(c.image)}" alt="${esc(c.name)}" class="size-10 rounded-lg object-cover ring-1 ring-slate-200" width="40" height="40" loading="lazy">` : `<span class="grid size-10 place-items-center rounded-lg bg-slate-100 text-[10px]">${esc(c.name.slice(0, 2))}</span>`}
                    ${c.quantity > 1 ? `<span class="absolute -end-1 -top-1 rounded-full bg-slate-800 px-1 text-[10px] font-bold text-white">×${c.quantity}</span>` : ''}</li>`).join('')}</ul></div>
                ${b.explanation ? `<p class="text-sm leading-6 text-slate-600">${esc(b.explanation)}</p>` : ''}
                ${selectable ? `<button type="button" class="btn btn-secondary !px-3 !py-1.5 text-xs" data-add-gift-bundle="${b.bundle_id}">${esc(T.add_bundle)}</button>` : ''}
            </div></article>`;
    };
    const card = (r, selectable) => (r.type === 'bundle' ? bundleCard(r, selectable) : productCard(r, selectable));

    /* a card's selects -> the matching in-stock variant (updates price, image and the checkbox's variant) */
    const resolveVariant = (cardEl) => {
        const picker = pickers[cardEl.dataset.card];
        const chosen = [...cardEl.querySelectorAll('[data-opt]')].map((s) => Number(s.value)).sort((a, b) => a - b);
        const v = picker.variants.find((x) => x.values.length === chosen.length && [...x.values].sort((a, b) => a - b).every((n, i) => n === chosen[i]));
        const box = cardEl.querySelector('[data-pick]');
        cardEl.querySelector('[data-unavail]')?.classList.toggle('hidden', !!v);
        if (box) { box.disabled = !v; box.dataset.variant = v ? v.id : ''; if (!v) box.checked = false; }
        if (v) {
            cardEl.querySelector('[data-price]').innerHTML = money(v.price);
            const img = cardEl.querySelector('[data-img]'); if (img && v.image) img.src = v.image;
        }
        syncCart();
    };
    const initPickers = () => out.querySelectorAll('[data-card]').forEach((c) => {
        const picker = pickers[c.dataset.card]; if (!picker || !c.querySelector('[data-opt]')) return;
        const first = picker.variants[0];
        c.querySelectorAll('[data-opt]').forEach((sel) => { const hit = first.values.find((vid) => [...sel.options].some((o) => Number(o.value) === vid)); if (hit) sel.value = hit; });
        resolveVariant(c);
    });

    const render = (d) => {
        const items = d.items || [];
        const near = d.near_items || [];
        let h = '';
        if (d.relaxed_notice) h += `<p class="rounded-xl bg-amber-50 px-3 py-2 text-sm text-amber-900" role="note">${esc(d.relaxed_notice)}</p>`;
        h += `<div class="flex flex-wrap items-center justify-between gap-2">
            <p class="font-bold">${esc(T.results)} <span class="text-sm font-normal text-slate-500">(${esc(T.count.replace(':n', items.length))})</span></p>
            ${d.share_url ? `<div class="flex items-center gap-2"><button type="button" class="btn btn-secondary !px-3 !py-1.5 text-xs" data-share="${esc(d.share_url)}">
                <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 13.5 6.8 4M15.4 6.5l-6.8 4"/></svg>${esc(T.share)}</button></div>` : ''}
        </div>`;
        h += items.length ? `<div class="grid gap-3 xl:grid-cols-2">${items.map((r) => card(r, true)).join('')}</div>` : `<p class="rounded-xl bg-white p-4 text-slate-500">${esc(T.none)}</p>`;
        if (items.some((x) => x.type === 'bundle' || x.simple || x.picker)) {
            h += `<div class="sticky bottom-20 z-10 lg:bottom-4"><button type="button" id="gf-cart" class="btn btn-primary w-full !py-3 shadow-lg sm:w-auto">${esc(T.add)} (<span data-count></span>)</button></div>`;
        }
        if (near.length) h += `<h3 class="pt-2 font-bold text-amber-700">${esc(T.near)}</h3><div class="grid gap-3 xl:grid-cols-2">${near.map((r) => card(r, false)).join('')}</div>`;
        out.innerHTML = h;
        initPickers();
        wireCart(d.token);
        status.textContent = T.count.replace(':n', items.length) + (d.relaxed_notice ? '. ' + d.relaxed_notice : '');
    };

    const refreshMiniCart = async () => {
        try {
            const r = await fetch(cfg.cartUrl, { headers: { Accept: 'application/json' } });
            const j = await r.json();
            if (j.data && window.$) window.$(document).trigger('cart:updated', [j.data]);
        } catch { /* the header count catches up on the next page */ }
    };

    const postCart = async (token, body) => {
        const res = await fetch(`${cfg.searchUrl}/${token}/cart`, { method: 'POST', body, headers });
        const j = await res.json().catch(() => ({}));
        return { ok: res.ok, j };
    };

    const wireCart = (token) => {
        const btn = out.querySelector('#gf-cart');
        const picked = () => [...out.querySelectorAll('[data-pick]:checked')];
        const sync = () => { if (!btn) return; const n = picked().length; btn.querySelector('[data-count]').textContent = n; btn.disabled = n === 0; };
        out.onchange = (e) => {
            if (e.target.matches('[data-opt]')) resolveVariant(e.target.closest('[data-card]'));
            if (e.target.matches('[data-pick]')) sync();
        };
        out.onclick = async (e) => {
            const share = e.target.closest('[data-share]');
            if (share) {
                const url = share.dataset.share;
                try {
                    if (navigator.share) { await navigator.share({ url, title: document.title }); return; }
                    await navigator.clipboard.writeText(url); toast(true, T.copied);
                } catch { window.prompt(T.copy_link, url); }
                return;
            }
            const add = e.target.closest('[data-add-gift-bundle]');
            if (add) {
                add.disabled = true;
                const body = new FormData(); body.append('bundle_ids[]', add.dataset.addGiftBundle);
                try {
                    const { ok, j } = await postCart(token, body);
                    toast(ok, ok ? T.added : (j.message || T.error));
                    if (ok) refreshMiniCart();
                } catch { toast(false, T.error); }
                add.disabled = false;
            }
        };
        syncCart = sync;
        sync();
        btn?.addEventListener('click', async () => {
            btn.disabled = true;
            try {
                const body = new FormData();
                picked().forEach((i) => {
                    if (i.dataset.pick === 'bundle') { body.append('bundle_ids[]', i.value); return; }
                    body.append('product_ids[]', i.value);
                    if (i.dataset.variant) body.append(`variants[${i.value}]`, i.dataset.variant);
                });
                const { ok, j } = await postCart(token, body);
                if (ok && j.redirect) { location.href = j.redirect; return; }
                toast(false, j.message || T.error);
            } catch { toast(false, T.error); }
            btn.disabled = false;
        });
    };

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!budget.value || Number(budget.value) <= 0) { budget.focus(); budget.reportValidity?.(); return; }
        submit.disabled = true;
        out.setAttribute('aria-busy', 'true');
        status.textContent = T.loading;
        out.innerHTML = `<div class="grid gap-3 xl:grid-cols-2" aria-hidden="true">${'<div class="h-28 animate-pulse rounded-2xl bg-slate-100"></div>'.repeat(4)}</div>`;
        try {
            const res = await fetch(cfg.searchUrl, { method: 'POST', body: new FormData(form), headers });
            const j = await res.json().catch(() => ({}));
            if (!res.ok) { out.innerHTML = ''; message('bg-red-50 text-red-700', Object.values(j.errors || {}).flat()[0] || j.message || T.error); status.textContent = T.error; return; }
            render(j.data);
            if (window.matchMedia('(max-width: 1023px)').matches) out.scrollIntoView({ behavior: 'smooth', block: 'start' });
            out.focus({ preventScroll: true });
        } catch { out.innerHTML = ''; message('bg-red-50 text-red-700', T.error); } finally {
            submit.disabled = false;
            out.removeAttribute('aria-busy');
        }
    });

    if (cfg.initial) render(cfg.initial);
}
