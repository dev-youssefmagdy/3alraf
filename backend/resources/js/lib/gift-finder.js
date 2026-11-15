/* Gift Finder page (design 25:4691, results 31:1480): budget slider + presets, submits the answers, renders ONE ranked list of products and bundles (type filter / sort / relaxed-filter
   notice / near-budget section; products, bundles and near-budget picks each on a Swiper row), per-item selection to a prepared cart, one-click "add bundle to cart" and a share link (token URL). Logical
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
    const message = (cls, text) => { out.insertAdjacentHTML('afterbegin', `<p class="${cls} rounded-2xl px-4 py-3 text-sm font-semibold" role="alert">${esc(text)}</p>`); };
    const toast = (ok, text) => (window.toastr ? window.toastr[ok ? 'success' : 'error'](text) : (status.textContent = text));

    /* ---------- budget: number field <-> slider <-> presets ---------- */
    const syncPresets = () => form.querySelectorAll('[data-preset]').forEach((b) => b.setAttribute('aria-pressed', String(Number(b.dataset.preset) === Number(budget.value))));
    budget.addEventListener('input', () => { if (budget.value) range.value = Math.min(Number(range.max), Number(budget.value)); syncPresets(); });
    range.addEventListener('input', () => { budget.value = range.value; syncPresets(); });
    form.querySelectorAll('[data-preset]').forEach((b) => b.addEventListener('click', () => { budget.value = b.dataset.preset; range.value = Math.min(Number(range.max), Number(b.dataset.preset)); syncPresets(); }));

    /* ---------- cards ---------- */
    const pickers = {};
    let syncCart = () => {};
    const optionHtml = (r) => r.picker.options.map((o) => `<select data-opt="${o.id}" class="form-control !min-h-10 w-full !rounded-full !py-1.5 !text-xs" aria-label="${esc(o.name)}">
        ${o.values.map((v) => `<option value="${v.id}">${esc(o.name)}: ${esc(v.label)}</option>`).join('')}</select>`).join('');
    // selection checkbox: a 44px row with a visible "Select" label (the accessible name keeps the item name)
    const checkbox = (key, value, name, extra = '') => `<label class="mt-2 flex min-h-11 cursor-pointer items-center gap-2 border-t border-slate-100 px-1 pt-2 text-sm font-semibold text-slate-950">
        <input type="checkbox" class="size-[22px] shrink-0 rounded-md accent-brand-600" data-pick="${key}" value="${value}" ${extra} checked aria-label="${esc(T.select.replace(':name', name))}">
        <span aria-hidden="true">${esc(T.select.replace(':name', '').trim())}</span></label>`;
    const tints = ['bg-mint-100', 'bg-pop-50', 'bg-sun-100', 'bg-violet-100'];
    const sparkle = '<svg class="size-3.5 shrink-0" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l2.2 6.3L20 10l-5.8 1.7L12 18l-2.2-6.3L4 10l5.8-1.7zM19 15l.9 2.1L22 18l-2.1.9L19 21l-.9-2.1L16 18l2.1-.9z"/></svg>';
    const reason = (r) => (r.explanation ? `<p class="mt-2 flex w-fit max-w-full items-start gap-1 rounded-xl bg-violet-100 px-2 py-1 text-xs/[18px] font-semibold text-violet-700">${sparkle}<span>${esc(r.explanation)}</span></p>` : '');
    const overBadge = (r) => (r.over_budget ? `<span class="badge badge-low">${esc(T.over)}</span>` : '');

    const productCard = (r, selectable) => {
        const variable = !r.simple && r.picker;
        if (variable) pickers[r.key] = r.picker;
        const first = variable ? r.picker.variants[0] : null;
        const url = `${cfg.productUrl}/${encodeURIComponent(r.slug)}`;
        const check = selectable && (r.simple || variable) ? checkbox('product', r.product_id, r.name, variable ? `data-variant="${first.id}"` : '') : '';
        return `<article class="product-card flex h-full flex-col rounded-2xl border border-slate-200 bg-white p-2 shadow-card" data-card="${esc(r.key)}">
            <a href="${url}" class="relative block overflow-hidden rounded-xl ${tints[(Number(r.product_id) || 0) % 4]}" tabindex="-1" aria-hidden="true">${r.image ? `<img src="${esc(r.image)}" class="product-card-img aspect-square w-full object-cover" alt="" width="300" height="300" loading="lazy" decoding="async" data-img>` : '<span class="block aspect-square"></span>'}
                ${r.over_budget ? `<span class="absolute start-2 top-2">${overBadge(r)}</span>` : ''}</a>
            <div class="flex flex-1 flex-col px-1 pt-2">
                <h3 class="line-clamp-2 min-h-11 text-sm/[22px] font-semibold text-slate-950"><a class="hover:text-brand-600" href="${url}">${esc(r.name)}</a></h3>
                <p class="mt-1"><b class="price text-lg/6 text-brand-600" data-price>${money(variable ? first.price : r.price)}</b></p>
                ${selectable && !r.simple && !variable ? `<a class="link mt-1 text-xs" href="${url}">${esc(T.choose_options)}</a>` : ''}
                ${variable && selectable ? `<div class="mt-2 space-y-1.5">${optionHtml(r)}</div><p class="mt-1 hidden text-xs text-red-600" data-unavail>${esc(T.unavailable_option)}</p>` : ''}
                ${reason(r)}
                <div class="mt-auto">${check}</div>
            </div></article>`;
    };

    const bundleCard = (b, selectable) => {
        const url = `${cfg.bundleUrl}/${encodeURIComponent(b.slug)}`;
        const imgs = (b.components || []).filter((c) => c.image).slice(0, 4);
        const saving = Number(b.savings) > 0;
        const media = b.image ? `<img src="${esc(b.image)}" class="product-card-img aspect-square w-full object-cover" alt="" width="300" height="300" loading="lazy" decoding="async">`
            : imgs.length > 1 ? `<span class="grid aspect-square grid-cols-2 gap-1.5 p-3">${imgs.map((c) => `<img src="${esc(c.image)}" alt="" class="aspect-square size-full rounded-lg bg-white object-cover" loading="lazy" decoding="async">`).join('')}</span>`
            : imgs.length ? `<img src="${esc(imgs[0].image)}" class="product-card-img aspect-square w-full object-cover" alt="" loading="lazy" decoding="async">` : '<span class="block aspect-square"></span>';
        return `<article class="product-card flex h-full flex-col rounded-2xl border border-slate-200 bg-white p-2 shadow-card" data-card="${esc(b.key)}">
            <a href="${url}" class="relative block overflow-hidden rounded-xl bg-accent-light" tabindex="-1" aria-hidden="true">${media}
                <span class="absolute start-2 top-2 flex flex-col items-start gap-1"><span class="badge badge-bundle">${esc(T.bundle)}</span>${overBadge(b)}</span></a>
            <div class="flex flex-1 flex-col px-1 pt-2">
                <h3 class="line-clamp-2 min-h-11 text-sm/[22px] font-semibold text-slate-950"><a class="hover:text-brand-600" href="${url}">${esc(b.name)}</a></h3>
                <p class="mt-1 flex flex-wrap items-baseline gap-x-2"><b class="price text-lg/6 text-brand-600">${money(b.price)}</b>${saving ? `<s class="price-old">${money(b.regular_total)}</s>` : ''}</p>
                ${saving ? `<p class="mt-1"><span class="badge bg-sun-400 text-slate-950">${esc(T.save.replace(':amount', fmt(b.savings) + ' ' + cfg.symbol))}</span></p>` : ''}
                <div class="mt-2"><p class="sr-only">${esc(T.includes)}</p><ul class="flex flex-wrap gap-1.5">${(b.components || []).map((c) => `<li class="relative" title="${esc(c.name)}">
                    ${c.image ? `<img src="${esc(c.image)}" alt="${esc(c.name)}" class="size-9 rounded-lg object-cover ring-1 ring-slate-200" width="36" height="36" loading="lazy">` : `<span class="grid size-9 place-items-center rounded-lg bg-slate-100 text-[10px]">${esc(c.name.slice(0, 2))}</span>`}
                    ${c.quantity > 1 ? `<span class="absolute -end-1 -top-1 rounded-full bg-slate-950 px-1 text-[10px] font-bold text-white">×${c.quantity}</span>` : ''}</li>`).join('')}</ul></div>
                ${reason(b)}
                <div class="mt-auto">
                    ${selectable ? `<button type="button" class="btn btn-secondary mt-2 w-full !px-2 !text-xs" data-add-gift-bundle="${b.bundle_id}">${esc(T.add_bundle)}</button>` : ''}
                    ${selectable ? checkbox('bundle', b.bundle_id, b.name) : ''}
                </div>
            </div></article>`;
    };
    const card = (r, selectable) => (r.type === 'bundle' ? bundleCard(r, selectable) : productCard(r, selectable));
    const arrow = (d) => `<svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="${d}"/></svg>`;
    // one Swiper row ("rail" preset, sized for the narrower results column on desktop); sliders.js initialises it on `sliders:refresh`
    const row = (title, list, selectable, extra = '') => (list.length ? `<section data-swiper="rail" data-swiper-bleed data-swiper-options='{"slidesPerView":1.7,"breakpoints":{"560":{"slidesPerView":2.3},"768":{"slidesPerView":3.2,"spaceBetween":12},"1024":{"slidesPerView":2.6,"spaceBetween":16},"1280":{"slidesPerView":3.4,"spaceBetween":16}}}' class="gf-row max-lg:-mx-4">
            <div class="mb-3 flex items-center justify-between gap-3 max-lg:px-4"><h3 class="font-display text-xl/7 font-bold text-slate-950">${esc(title)} <span class="font-sans text-sm font-semibold text-slate-500">(${esc(T.count.replace(':n', list.length))})</span></h3>
                <div class="flex shrink-0 items-center gap-2">${extra}<button type="button" data-swiper-prev class="icon-btn icon-btn-outline" aria-label="${esc(T.prev)}">${arrow('m15 6-6 6 6 6')}</button><button type="button" data-swiper-next class="icon-btn icon-btn-outline" aria-label="${esc(T.next)}">${arrow('m9 6 6 6-6 6')}</button></div></div>
            <div class="swiper !py-1"><div class="swiper-wrapper">${list.map((r) => `<div class="swiper-slide">${card(r, selectable)}</div>`).join('')}</div></div>
            <div data-swiper-pagination class="mx-auto mt-3 w-32"></div></section>` : '');

    /* answers summary chips + Edit (design 31:1480) */
    const summary = () => {
        const pick = (sel) => { const el = form.querySelector(sel); return el && el.value ? el.options[el.selectedIndex].text : ''; };
        const type = form.querySelector('[name=type]:checked');
        const chips = [budget.value ? `${T.budget}: ${fmt(budget.value)} ${cfg.symbol}` : '', pick('[name=occasion]'), pick('[name=recipient]'), type && type.value !== 'all' ? type.nextElementSibling?.textContent.trim() : '', pick('[name=category_id]').replace(/^(— )+/, '')].filter(Boolean);
        return `<div class="flex flex-wrap items-center gap-2 rounded-3xl bg-violet-500 p-3 lg:p-4">${chips.map((c) => `<span class="inline-flex min-h-9 items-center rounded-full bg-white px-3 text-xs font-bold text-violet-700">${esc(c)}</span>`).join('')}
            <button type="button" data-gf-edit class="btn btn-sun !min-h-9 !px-4 !py-1 text-xs">${esc(T.edit)}</button></div>`;
    };

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
        const products = items.filter((x) => x.type !== 'bundle');
        const bundles = items.filter((x) => x.type === 'bundle');
        const share = d.share_url ? `<button type="button" class="btn btn-secondary !min-h-11 !px-3 text-xs" data-share="${esc(d.share_url)}">
                <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 13.5 6.8 4M15.4 6.5l-6.8 4"/></svg><span class="max-sm:sr-only">${esc(T.share)}</span></button>` : '';
        let h = summary();
        if (d.relaxed_notice) h += `<p class="rounded-2xl bg-amber-50 px-4 py-3 text-sm text-amber-700" role="note">${esc(d.relaxed_notice)}</p>`;
        if (!items.length) {
            h += `<div class="flex items-center justify-between gap-2"><p class="text-sm font-semibold text-slate-600">${esc(T.count.replace(':n', 0))}</p>${share}</div>
                <div class="panel flex flex-col items-center py-10 text-center"><span class="grid size-[120px] place-items-center rounded-[42%_58%_55%_45%/48%_42%_58%_52%] bg-sun-100 text-accent-dark" aria-hidden="true"><svg class="size-[52px]" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M20 12v9H4v-9M2 7h20v5H2zM12 21V7M12 7H7.5a2.5 2.5 0 1 1 0-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 1 0 0-5C13 2 12 7 12 7z"/></svg></span>
                <p class="mt-5 max-w-sm text-base font-bold text-slate-950">${esc(T.none)}</p></div>`;
        }
        // the first row carries the Share button; bundles get their own row
        h += row(T.type_products, products, true, share);
        h += row(T.bundles, bundles, true, products.length ? '' : share);
        if (near.length) h += row(T.near, near, false);
        if (items.some((x) => x.type === 'bundle' || x.simple || x.picker)) {
            h += `<div class="fixed inset-x-0 bottom-[calc(72px+env(safe-area-inset-bottom))] z-30 bg-slate-950 px-4 py-3 shadow-bar lg:sticky lg:bottom-4 lg:rounded-3xl lg:px-5">
                <div class="mx-auto flex max-w-xl items-center justify-between gap-3 lg:max-w-none"><p class="text-sm font-bold text-white" aria-hidden="true"><span data-count-mirror></span> &check;</p>
                <button type="button" id="gf-cart" class="btn btn-sun !min-h-11">${'<svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.4 11.2a1 1 0 0 0 1 .8h8.8a1 1 0 0 0 1-.8L20 8H6M9 20h.01M17 20h.01"/></svg>'}<span>${esc(T.add)} (<span data-count></span>)</span></button></div></div>`;
        }
        out.innerHTML = h;
        out.classList.toggle('max-lg:pb-20', !!out.querySelector('#gf-cart'));
        out.dispatchEvent(new CustomEvent('sliders:refresh', { bubbles: true }));
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
        const sync = () => { if (!btn) return; const n = picked().length; btn.querySelector('[data-count]').textContent = n; const m = out.querySelector('[data-count-mirror]'); if (m) m.textContent = n; btn.disabled = n === 0; };
        out.onchange = (e) => {
            if (e.target.matches('[data-opt]')) resolveVariant(e.target.closest('[data-card]'));
            if (e.target.matches('[data-pick]')) sync();
        };
        out.onclick = async (e) => {
            if (e.target.closest('[data-gf-edit]')) { form.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' }); budget.focus({ preventScroll: true }); return; }
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
        out.innerHTML = `<div class="grid grid-cols-2 gap-3 sm:grid-cols-3" aria-hidden="true">${'<div class="aspect-[3/4] animate-pulse rounded-2xl bg-slate-100"></div>'.repeat(3)}</div>`;
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
