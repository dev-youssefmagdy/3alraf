/* Gift Finder page: submits the form, renders suggestions (with per-item selection) and sends the chosen ones to the cart. */
const root = document.getElementById('gf');
if (root) init(root);

function init(root) {
    const cfg = JSON.parse(root.dataset.config);
    const T = cfg.t;
    const form = root.querySelector('#gf-form');
    const out = root.querySelector('#gf-out');
    const submit = form.querySelector('[type=submit]');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const money = (v) => `${esc(Number(v).toFixed(2))} ${esc(cfg.symbol)}`;
    const headers = { Accept: 'application/json', 'X-CSRF-TOKEN': csrf };
    const message = (cls, text) => { out.innerHTML = `<p class="${cls}" role="alert">${esc(text)}</p>`; };

    const pickers = {};
    let syncCart = () => {};      // re-pointed at the current results' cart button on every search
    const optionHtml = (r) => r.picker.options.map((o) => `<select data-opt="${o.id}" data-product="${r.product_id}" class="rounded border border-slate-300 bg-white px-2 py-1 text-xs" aria-label="${esc(o.name)}">
        ${o.values.map((v) => `<option value="${v.id}">${esc(o.name)}: ${esc(v.label)}</option>`).join('')}</select>`).join('');

    const card = (r, selectable) => {
        const variable = !r.simple && r.picker;
        if (variable) pickers[r.product_id] = r.picker;
        const first = variable ? r.picker.variants[0] : null;
        const check = selectable && (r.simple || variable)
            ? `<input type="checkbox" class="mt-1 size-4 accent-brand-600" data-pick value="${r.product_id}" ${variable ? `data-variant="${first.id}"` : ''} checked aria-label="${esc(r.name)}">` : '<span class="w-4"></span>';
        return `<div class="flex items-start gap-3 rounded-xl border border-slate-100 bg-white p-3" data-card="${r.product_id}">
            ${check}
            ${r.image ? `<img src="${esc(r.image)}" class="size-16 shrink-0 rounded object-cover" alt="" width="64" height="64" loading="lazy" data-img>` : ''}
            <div class="min-w-0 flex-1">
                <a class="font-semibold hover:text-brand-600" href="${cfg.productUrl}/${encodeURIComponent(r.slug)}">${esc(r.name)}</a>
                <p class="text-sm"><b data-price>${money(variable ? first.price : r.price)}</b>${r.over_budget ? ` <span class="rounded bg-amber-100 px-1.5 text-xs font-semibold text-amber-800">${esc(T.over)}</span>` : ''}${selectable && !r.simple && !variable ? ` <a class="text-xs text-brand-700 underline" href="${cfg.productUrl}/${encodeURIComponent(r.slug)}">${esc(T.choose_options)}</a>` : ''}</p>
                ${variable && selectable ? `<div class="mt-1 flex flex-wrap gap-2">${optionHtml(r)}</div><p class="hidden text-xs text-red-600" data-unavail>${esc(T.unavailable_option)}</p>` : ''}
                ${r.explanation ? `<p class="text-sm text-slate-600">${esc(r.explanation)}</p>` : ''}
            </div></div>`;
    };

    /* a card's selects -> the matching in-stock variant (updates price, image and the checkbox's variant) */
    const resolveVariant = (cardEl) => {
        const id = cardEl.dataset.card, picker = pickers[id];
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

    /* preselect the first in-stock variant so the item is selectable straight away */
    const initPickers = () => out.querySelectorAll('[data-card]').forEach((c) => {
        const picker = pickers[c.dataset.card]; if (!picker) return;
        const first = picker.variants[0];
        c.querySelectorAll('[data-opt]').forEach((sel) => { const hit = first.values.find((vid) => [...sel.options].some((o) => Number(o.value) === vid)); if (hit) sel.value = hit; });
        resolveVariant(c);
    });

    const render = (d) => {
        let h = `<h2 class="font-bold">${esc(T.results)}</h2>`;
        h += d.results.length ? d.results.map((r) => card(r, true)).join('') : `<p class="text-slate-500">${esc(T.none)}</p>`;
        if (d.results.some((x) => x.simple)) h += `<button type="button" id="gf-cart" class="btn btn-primary">${esc(T.add)} (<span data-count></span>)</button>`;
        if (d.bundles.length) h += `<h2 class="font-bold">${esc(T.bundles)}</h2>` + d.bundles.map((b) => `<a class="block rounded-xl border border-slate-100 bg-white p-3" href="${cfg.bundleUrl}/${encodeURIComponent(b.slug)}">${esc(b.name)} - ${esc(b.price)} ${esc(cfg.symbol)}</a>`).join('');
        if (d.near_budget.length) h += `<h2 class="font-bold text-amber-700">${esc(T.near)}</h2>` + d.near_budget.map((r) => card(r, false)).join('');
        out.innerHTML = h;
        initPickers();
        wireCart(d.token);
    };

    const wireCart = (token) => {
        const btn = out.querySelector('#gf-cart');
        if (!btn) return;
        const picked = () => [...out.querySelectorAll('[data-pick]:checked')];
        const sync = () => { const n = picked().length; btn.querySelector('[data-count]').textContent = n; btn.disabled = n === 0; };
        const onChange = (e) => {
            if (e.target.matches('[data-opt]')) resolveVariant(e.target.closest('[data-card]'));
            if (e.target.matches('[data-pick]')) sync();
        };
        out.onchange = onChange;           // replaced (not stacked) on every new search
        syncCart = sync;
        sync();
        btn.addEventListener('click', async () => {
            btn.disabled = true;
            try {
                const body = new FormData();
                [...out.querySelectorAll('[data-pick]:checked')].forEach((i) => {
                    body.append('product_ids[]', i.value);
                    if (i.dataset.variant) body.append(`variants[${i.value}]`, i.dataset.variant);
                });
                const res = await fetch(`${cfg.searchUrl}/${token}/cart`, { method: 'POST', body, headers });
                const j = await res.json().catch(() => ({}));
                if (res.ok && j.redirect) { location.href = j.redirect; return; }
                message('text-red-600', j.message || T.error);
            } catch { message('text-red-600', T.error); }
            btn.disabled = false;
        });
    };

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        submit.disabled = true;
        out.setAttribute('aria-busy', 'true');
        out.innerHTML = `<div class="space-y-3" aria-hidden="true">${'<div class="h-20 animate-pulse rounded-xl bg-slate-100"></div>'.repeat(3)}</div><p class="sr-only">${esc(T.loading)}</p>`;
        try {
            const res = await fetch(cfg.searchUrl, { method: 'POST', body: new FormData(form), headers });
            const j = await res.json().catch(() => ({}));
            if (!res.ok) { message('text-red-600', Object.values(j.errors || {}).flat()[0] || j.message || T.error); return; }
            render(j.data);
        } catch { message('text-red-600', T.error); } finally {
            submit.disabled = false;
            out.removeAttribute('aria-busy');
        }
    });
}
