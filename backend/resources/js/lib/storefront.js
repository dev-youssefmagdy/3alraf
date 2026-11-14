import $ from 'jquery';
import toastr from 'toastr';
import './sliders'; // evaluated first: the product gallery (Swiper) exists before the product page code below runs
import './drag-scroll'; // desktop category bar: drag / wheel to scroll sideways

const sf = window.__sf;
if (sf) init();

function init() {
    const T = sf.i;
    const nf = new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const money = (v) => nf.format(Number(v || 0)) + ' ' + sf.symbol;
    const esc = (s) => $('<div>').text(s ?? '').html();
    const firstError = (xhr) => {
        const e = xhr.responseJSON?.errors;
        return e ? Object.values(e)[0][0] : xhr.responseJSON?.message || window.__t?.error;
    };
    const itemUrl = (id) => sf.routes.cartItems + '/' + id;
    // phone inputs: digits only (country code is fixed by the store)
    $(document).on('input', 'input[inputmode=tel][autocomplete=tel-national]', function () { const v = this.value.replace(/[^0-9+\u0660-\u0669\u06F0-\u06F9\s\-()]/g, ''); if (v !== this.value) this.value = v; });

    /* ---------- cart state ---------- */
    let cart = { items: [], totals: null };
    function loadCart() {
        return $.get(sf.routes.cart).done((res) => { cart = res.data || { items: [], totals: null }; $(document).trigger('cart:updated', [cart]); });
    }
    function cartCall(method, url, data) {
        return $.ajax({ url, method: 'POST', data: Object.assign({ _method: method }, data || {}) })
            .done((res) => { cart = res.data || cart; $(document).trigger('cart:updated', [cart]); })
            .fail((xhr) => toastr.error(firstError(xhr)));
    }
    function addToCart(productId, variantId, qty) {
        return $.post(sf.routes.cartItems, { product_id: productId, variant_id: variantId || 0, quantity: qty || 1 })
            .done((res) => {
                toastr.success(res.message); cart = res.data || cart; $(document).trigger('cart:updated', [cart]);
                const line = (cart.items || []).find((i) => String(i.product_id) === String(productId)) || {};
                window.sfTrack?.('add_to_cart', { value: Number(line.unit_price || 0) * (qty || 1), items: [{ id: productId, name: line.name, price: Number(line.unit_price || 0), qty: qty || 1 }] });
            })
            .fail((xhr) => toastr.error(firstError(xhr)));
    }
    function addBundle(bundleId, qty) {
        return $.post(sf.routes.cartItems, { bundle_id: bundleId, quantity: qty || 1 })
            .done((res) => { toastr.success(res.message); cart = res.data || cart; $(document).trigger('cart:updated', [cart]); })
            .fail((xhr) => toastr.error(firstError(xhr)));
    }
    $(document).on('click', '[data-add-bundle]', function () {
        const $b = $(this); $b.prop('disabled', true);
        addBundle($b.data('add-bundle'), 1).always(() => $b.prop('disabled', false));
    });
    const count = (c) => (c.items || []).reduce((n, i) => n + i.qty, 0);

    let beganCheckout = false;
    $(document).on('cart:updated', (_e, c) => {
        if (!beganCheckout && /\/checkout\/?$/.test(location.pathname) && c.items?.length) { beganCheckout = true; document.dispatchEvent(new CustomEvent('cart:begin', { detail: c })); }
        $('[data-cart-count]').text(count(c));
        renderMini(c);
    });

    function renderMini(c) {
        const $b = $('[data-mini-cart]');
        if (!$b.length) return;
        if (!c.items.length) { $b.html('<p class="py-6 text-center text-slate-600">' + esc(T.cart_empty) + '</p>'); $('[data-mini-cart-foot]').addClass('hidden'); return; }
        $b.html(c.items.map((i) => '<div class="flex gap-3 py-3">' + (i.image ? '<img src="' + esc(i.image) + '" class="size-14 rounded-xl bg-slate-100 object-cover" alt="">' : '<div class="size-14 rounded-xl bg-slate-100"></div>')
            + '<div class="min-w-0 flex-1"><p class="line-clamp-1 font-semibold">' + esc(i.name) + '</p><p class="text-xs text-slate-500">' + i.qty + ' &times; ' + money(i.unit_price) + '</p></div>'
            + '<button type="button" data-mini-remove="' + i.id + '" class="grid size-9 shrink-0 place-items-center self-start rounded-full text-xl leading-none text-slate-500 hover:bg-red-50 hover:text-red-700" aria-label="' + esc(T.remove) + '">&times;</button></div>').join(''));
        $('[data-mini-cart-foot]').removeClass('hidden');
        $('[data-mini-subtotal]').text(c.totals ? money(c.totals.subtotal) : '');
    }
    $('#mini-cart').on('click', '[data-mini-remove]', function () { cartCall('DELETE', itemUrl($(this).data('mini-remove'))); });

    $(document).on('click', '[data-add-to-cart]', function () {
        const $b = $(this); $b.prop('disabled', true);
        addToCart($b.data('product'), $b.data('variant'), 1).always(() => $b.prop('disabled', false));
    });

    $(document).on('click', '[data-card-wish]', function () {
        const $b = $(this), url = $b.data('card-wish');
        if (!url) { location.href = $b.data('login'); return; }
        $b.prop('disabled', true);
        $.post(url).done((r) => {
            const on = !!(r.data ? r.data.in_wishlist : r.in_wishlist);
            $b.attr('aria-pressed', on).toggleClass('text-red-500', on).toggleClass('text-slate-400', !on).find('svg').attr('fill', on ? 'currentColor' : 'none');
            toastr.success(r.message);
        }).always(() => $b.prop('disabled', false));
    });

    /* ---------- out-of-stock request modal ---------- */
    (function () {
        const $m = $('[data-sr-modal]'), $f = $m.find('[data-sr-form]');
        const close = () => $m.addClass('hidden').removeClass('grid');
        window.openStockRequest = function (productId, variantId, label) {
            $f.find('[name=product_id]').val(productId); $f.find('[name=variant_id]').val(variantId || '');
            $f.find('[data-sr-item]').text(label || '');
            $m.removeClass('hidden').addClass('grid'); $f.find('[name=name]').trigger('focus');
        };
        $(document).on('click', '[data-stock-request]', function () { window.openStockRequest($(this).data('product'), $(this).data('variant'), $(this).data('name')); });
        $m.on('click', '[data-sr-close]', close).on('click', function (e) { if (e.target === this) close(); });
        $f.on('submit', function (e) {
            e.preventDefault();
            const $b = $f.find('[type=submit]').prop('disabled', true);
            $.post(sf.routes.stockRequest, $f.serialize())
                .done((r) => { toastr.success(r.message); $f.find('[name=phone],[name=note]').val(''); close(); })
                .fail((xhr) => toastr.error(firstError(xhr)))
                .always(() => $b.prop('disabled', false));
        });
    })();

    /* ---------- generic UI ---------- */
    // drawer is a modal dialog (role=dialog aria-modal): focus moves in on open, Tab stays inside, focus returns to the opener on close
    let drawerOpener = null;
    const drawerTabbables = () => $('[data-drawer]').find('a[href], button:not([disabled]), input:not([disabled]):not([type=hidden]), select, textarea, [tabindex]:not([tabindex="-1"])').filter(':visible');
    const drawerClose = () => {
        const $d = $('[data-drawer]');
        if ($d.hasClass('hidden')) return;
        $d.addClass('hidden');
        drawerOpener?.focus?.(); drawerOpener = null;
    };
    $(document).on('click', '[data-drawer-open]', function () {
        drawerOpener = this;
        $('[data-drawer]').removeClass('hidden');
        $('[data-drawer]').find('aside [data-drawer-close]').first().trigger('focus');
    });
    $(document).on('click', '[data-drawer-close]', drawerClose);
    $(document).on('keydown', '[data-drawer]', (e) => {
        if (e.key !== 'Tab') return;
        const $t = drawerTabbables(); if (!$t.length) return;
        const first = $t[0], last = $t[$t.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); } else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    });
    $(document).on('click', '[data-drawer-toggle]', function () {
        const open = $(this).attr('aria-expanded') !== 'true';
        $(this).attr('aria-expanded', open);
        $('#' + $(this).attr('aria-controls')).toggleClass('hidden', !open);
    });
    $(document).on('keydown', (e) => {
        if (e.key !== 'Escape') return;
        drawerClose();
        $('[data-mega-item]').removeClass('is-open').addClass('is-closed').children('a[aria-expanded]').attr('aria-expanded', 'false');
    });
    // mega menu: CSS opens it on hover / focus-within; this keeps aria-expanded in step and lets Esc close it until the pointer leaves
    $('[data-mega-item]').on('mouseenter focusin', function () { $(this).removeClass('is-closed').children('a[aria-expanded]').attr('aria-expanded', 'true'); })
        .on('mouseleave focusout', function (e) { if (!this.contains(e.relatedTarget)) $(this).removeClass('is-closed').children('a[aria-expanded]').attr('aria-expanded', 'false'); });
    /* catalog filters: a sidebar from 1024px, a bottom sheet below (design 32:2442) */
    const filtersOpen = (open) => {
        $('[data-filter-form]').toggleClass('hidden', !open).attr({ role: open ? 'dialog' : null, 'aria-modal': open ? 'true' : null });
        $('[data-filters-scrim]').toggleClass('hidden', !open);
        $('[data-filters-toggle][aria-expanded]').attr('aria-expanded', String(open));
        document.documentElement.classList.toggle('max-lg:overflow-hidden', open);
        if (open) filterTabbables().first().trigger('focus'); // the sheet's close button (an h2 is not focusable)
    };
    const filterTabbables = () => $('[data-filter-form]').find('a[href], button:not([disabled]), input:not([disabled]):not([type=hidden]), select:not([disabled]), textarea').filter(':visible');
    // while it is a bottom-sheet dialog, Tab stays inside it
    $(document).on('keydown', '[data-filter-form][aria-modal=true]', (e) => {
        if (e.key !== 'Tab') return;
        const $t = filterTabbables(); if (!$t.length) return;
        const first = $t[0], last = $t[$t.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); } else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    });
    $(document).on('click', '[data-filters-toggle]', () => filtersOpen($('[data-filter-form]').hasClass('hidden')));
    $(document).on('click', '[data-filters-scrim]', () => filtersOpen(false));
    $(document).on('keydown', (e) => { if (e.key === 'Escape' && !$('[data-filters-scrim]').hasClass('hidden')) { filtersOpen(false); $('[data-filters-toggle][aria-expanded]').trigger('focus'); } });
    // a desktop resize must not leave the page locked behind a closed sheet
    matchMedia('(min-width: 1024px)').addEventListener?.('change', (m) => { if (m.matches) { $('[data-filters-scrim]').addClass('hidden'); document.documentElement.classList.remove('max-lg:overflow-hidden'); } });

    /* share (product page): the native share sheet, or copy the link */
    $(document).on('click', '[data-share-url]', async function () {
        const url = this.dataset.shareUrl;
        try {
            if (navigator.share) { await navigator.share({ url, title: this.dataset.shareTitle || document.title }); return; }
            await navigator.clipboard.writeText(url); toastr.success(T.link_copied || url);
        } catch (e) { if (e?.name !== 'AbortError') window.prompt('', url); }
    });

    function tickCountdowns() {
        $('[data-countdown]').each(function () {
            const left = Math.max(0, Math.floor((new Date($(this).data('countdown')) - Date.now()) / 1000));
            const d = Math.floor(left / 86400), h = Math.floor((left % 86400) / 3600), m = Math.floor((left % 3600) / 60), s = left % 60;
            const p = (n) => String(n).padStart(2, '0');
            $(this).text((d ? d + T.d + ' ' : '') + p(h) + ':' + p(m) + ':' + p(s));
        });
    }
    tickCountdowns(); setInterval(tickCountdowns, 1000);

    /* ---------- search suggestions ---------- */
    (function () {
        const $form = $('[data-search-form]'), $in = $form.find('[data-search-input]'), $box = $form.find('[data-suggest]');
        let timer, last = '';
        const hide = () => $box.addClass('hidden');
        $in.on('input', function () {
            clearTimeout(timer);
            const q = $in.val().trim();
            if (q.length < 2) { hide(); return; }
            timer = setTimeout(() => {
                last = q;
                $.get(sf.routes.suggest, { q }).done((r) => {
                    if (q !== last) return;
                    let h = '';
                    if (r.categories.length) h += '<p class="px-4 pb-1 pt-2 text-xs font-bold text-slate-500">' + esc(T.categories) + '</p><div class="flex flex-wrap gap-2 px-4 pb-2">' + r.categories.map((c) => '<a class="chip !min-h-9 !px-3 !text-xs" href="' + esc(c.url) + '">' + esc(c.name) + '</a>').join('') + '</div>';
                    if (r.products.length) h += '<p class="px-4 pb-1 pt-2 text-xs font-bold text-slate-500">' + esc(T.products) + '</p>' + r.products.map((p) => '<a class="flex min-h-14 items-center gap-3 px-4 py-2 text-sm hover:bg-slate-100" href="' + esc(p.url) + '">'
                        + (p.image ? '<img src="' + esc(p.image) + '" class="size-11 rounded-xl bg-slate-100 object-cover" alt="">' : '<span class="size-11 rounded-xl bg-slate-100"></span>') + '<span class="line-clamp-2 flex-1 font-semibold text-slate-950">' + esc(p.name) + '</span><b class="whitespace-nowrap font-display text-pop-600">' + money(p.price) + '</b></a>').join('');
                    $box.html(h || '<p class="px-4 py-3 text-sm text-slate-500">' + esc(T.no_results) + '</p>').removeClass('hidden');
                });
            }, 250);
        });
        $(document).on('click', (e) => { if (!$(e.target).closest('[data-search-form]').length) hide(); });
        $in.on('keydown', (e) => { if (e.key === 'Escape') hide(); });
    })();

    /* ---------- popup banner (the hero and every other horizontal row are Swiper: sliders.js) ---------- */
    $('[data-popup]').each(function () {
        const key = 'popup-' + $(this).data('popup'); let seen = false;
        try { seen = sessionStorage.getItem(key); } catch (e) { /* ignore */ }
        if (seen) return;
        const $p = $(this);
        const close = () => { $p.addClass('hidden').removeClass('grid'); try { sessionStorage.setItem(key, '1'); } catch (er) { /* ignore */ } };
        setTimeout(() => { $p.removeClass('hidden').addClass('grid'); $p.find('[data-popup-close]').trigger('focus'); }, 1500);
        $p.on('click', (e) => { if (e.target === this || $(e.target).closest('[data-popup-close]').length) close(); });
        $(document).on('keydown', (e) => { if (e.key === 'Escape' && $p.hasClass('grid')) close(); });
    });

    /* ---------- catalog: AJAX filters ---------- */
    $('[data-catalog]').each(function () {
        const $c = $(this), $form = $c.find('[data-filter-form]'), action = $c.data('action');
        let busy = null;
        // active filter chips (each removes its filter) + the count on the phone "Filters" button
        const chips = () => {
            const on = [];
            $form.find('input[type=checkbox]:checked').each(function () { on.push({ label: this.dataset.label || this.value, el: this }); });
            $form.find('input[type=number]').each(function () { if (this.value !== '') on.push({ label: (this.placeholder || this.name) + ' ' + this.value, el: this }); });
            const $box = $c.find('[data-active-filters]').empty();
            on.forEach((f) => $('<button type="button" class="chip chip-on !min-h-9 !gap-1 !px-3 !text-xs"></button>').text(f.label)
                .attr('aria-label', (T.remove_filter || '').replace(':name', f.label) || f.label)
                .append('<svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>')
                .on('click', () => { if (f.el.type === 'checkbox') f.el.checked = false; else f.el.value = ''; load(1); })
                .appendTo($box));
            $c.find('[data-filters-count]').text(on.length).toggleClass('hidden', !on.length);
        };
        const load = (page, push = true) => {
            const params = $form.serialize() + (page > 1 ? '&page=' + page : '');
            $c.find('[data-grid]').css('opacity', 0.5);
            if (busy) busy.abort();
            busy = $.get(action, params).done((r) => {
                $c.find('[data-grid]').html(r.html).css('opacity', 1);
                $c.find('[data-pagination]').html(r.pagination);
                $c.find('[data-count]').text(r.label);
                chips();
                if (push) history.pushState(null, '', action + '?' + params);
                $c.find('[data-grid]')[0].scrollIntoView({ block: 'start', behavior: 'smooth' });
            }).fail((x) => { if (x.statusText !== 'abort') { $c.find('[data-grid]').css('opacity', 1); toastr.error(window.__t?.error); } });
        };
        let t;
        $form.on('change', 'input[type=checkbox]', () => load(1));
        $form.on('input', 'input[type=number]', () => { clearTimeout(t); t = setTimeout(() => load(1), 600); });
        $form.on('submit', (e) => { e.preventDefault(); load(1); if (!matchMedia('(min-width: 1024px)').matches) filtersOpen(false); });
        $form.on('click', '[data-filter-reset]', function (e) { e.preventDefault(); $form.find('input[type=checkbox]').prop('checked', false); $form.find('input[type=number]').val(''); load(1); $(this).remove(); });
        $c.on('change', '[data-sort]', function () { $form.find('[data-sort-input]').val(this.value); load(1); });
        $c.on('click', '[data-page-link]', function (e) { e.preventDefault(); const p = new URL(this.href).searchParams.get('page'); load(parseInt(p, 10) || 1); });
        window.addEventListener('popstate', () => location.reload());
        chips();
    });

    /* ---------- product page ---------- */
    $('[data-product-page]').each(function () {
        const $p = $(this), picker = JSON.parse($p.attr('data-picker')), pid = $p.data('product-id');
        const sel = {}; // attribute id -> value id
        let current = null, flashTimer;
        const $btn = $p.find('[data-add-to-cart-page]');
        const matches = (v, s) => Object.values(s).every((id) => v.values.includes(id));
        const nGroups = picker.options.length;
        const gallery = $p.find('[data-swiper="gallery"]')[0]?.gallery || null; // Swiper gallery API (sliders.js)
        let firstRefresh = true;

        function refresh() {
            // disable option values that no variant can satisfy together with the other selections
            $p.find('[data-option-group]').each(function () {
                const gid = $(this).data('option-group');
                const others = Object.assign({}, sel); delete others[gid];
                $(this).find('[data-option]').each(function () {
                    const id = $(this).data('option');
                    const ok = picker.variants.some((v) => matches(v, others) && v.values.includes(id));
                    $(this).prop('disabled', !ok).toggleClass('is-active', sel[gid] === id);
                });
            });
            current = Object.keys(sel).length === nGroups ? picker.variants.find((v) => matches(v, sel)) || null : (nGroups ? null : picker.variants[0] || null);
            $p.find('[data-combo-error]').toggleClass('hidden', !(nGroups && Object.keys(sel).length === nGroups && !current));
            const v = current;
            $p.find('[data-price]').text(v ? nf.format(v.price) : '—');
            $p.find('[data-old]').toggleClass('hidden', !(v && v.old)).text(v && v.old ? nf.format(v.old) : '');
            $p.find('[data-price-wrap]').toggleClass('price-sale', !!(v && v.old)).toggleClass('text-brand-600', !(v && v.old));
            const $save = $p.find('[data-save]');
            $save.toggleClass('hidden', !(v && v.old)).text(v && v.old ? String($save.data('save-tpl') || '').replace(':amount', nf.format(v.old - v.price)) : '');
            $p.find('[data-sku]').text(v ? v.sku || '' : '');
            $p.find('[data-barcode]').text(v ? v.barcode || '' : '');
            let st = '';
            if (v) st = !v.in_stock ? '<span class="text-red-600">' + esc(T.out_of_stock) + '</span>' : (v.stock !== null && v.stock <= (v.alert ?? picker.alert ?? 5) ? '<span class="text-amber-700">' + esc(T.low_stock.replace(':n', v.stock)) + '</span>' : '<span class="text-emerald-700">' + esc(T.in_stock) + '</span>');
            $p.find('[data-stock]').html(st);
            if (v && v.image && gallery) gallery.showVariantImage(v.image, { initial: firstRefresh, explicit: !!(wanted && v.id === wanted) });
            $btn.prop('disabled', !(v && v.in_stock));
            $p.find('[data-stock-request-page]').toggleClass('hidden', !(v && !v.in_stock)).data('variant', v ? v.id : 0);
            // the sticky bar mirrors the buy box (its buttons forward the click)
            $p.find('[data-sticky-add]').prop('disabled', !(v && v.in_stock)).toggleClass('hidden', !!(v && !v.in_stock));
            $p.find('[data-sticky-request]').toggleClass('hidden', !(v && !v.in_stock));
            $p.find('[data-variant-status]').toggleClass('hidden', !picker.variable).html(picker.variable ? '<p class="mb-2 text-sm font-bold">' + esc(T.variants_availability) + '</p>' + picker.variants.map((x) => {
                const state = !x.in_stock ? '<span class="text-red-600">' + esc(T.out_of_stock) + '</span>' : (x.stock !== null && x.stock <= (x.alert ?? picker.alert ?? 5) ? '<span class="text-amber-700">' + esc(T.low_stock.replace(':n', x.stock)) + '</span>' : '<span class="text-emerald-700">' + esc(T.in_stock) + '</span>');
                const req = x.in_stock ? '' : ' <button type="button" class="btn-sm btn-secondary" data-request-variant="' + x.id + '">' + esc(T.request_now) + '</button>';
                return '<div class="flex items-center justify-between gap-2 border-t border-slate-100 py-1.5 text-sm first-of-type:border-0"><span>' + esc(x.label || '') + '</span><span class="flex items-center gap-2">' + state + req + '</span></div>';
            }).join('') : '');
            const $q = $p.find('[data-qty]');
            if (v && v.stock !== null) $q.attr('max', v.stock); else $q.removeAttr('max');
            clearInterval(flashTimer);
            const $f = $p.find('[data-flash]');
            if (v && v.flash_ends) {
                $f.removeClass('hidden');
                const tick = () => {
                    const left = Math.max(0, Math.floor((new Date(v.flash_ends) - Date.now()) / 1000)), p2 = (n) => String(n).padStart(2, '0');
                    $f.find('[data-flash-time]').text(Math.floor(left / 3600) + ':' + p2(Math.floor((left % 3600) / 60)) + ':' + p2(left % 60));
                };
                tick(); flashTimer = setInterval(tick, 1000);
            } else $f.addClass('hidden');
        }
        // preselect the first in-stock variant
        // (a QR / shared link can carry ?variant={product_variant_id} to preselect that variant)
        const wanted = parseInt(new URLSearchParams(location.search).get('variant'), 10);
        const start = (wanted && picker.variants.find((v) => v.id === wanted)) || picker.variants.find((v) => v.in_stock) || picker.variants[0];
        if (nGroups && start) picker.options.forEach((o) => { const val = o.values.find((x) => start.values.includes(x.id)); if (val) sel[o.id] = val.id; });
        $p.on('click', '[data-option]', function () {
            const gid = $(this).closest('[data-option-group]').data('option-group'), id = $(this).data('option');
            if (sel[gid] === id) delete sel[gid]; else sel[gid] = id;
            refresh();
        });
        $p.on('click', '[data-stock-request-page]', function () { window.openStockRequest(pid, current ? current.id : 0, $(this).data('name') + (current && current.label ? ' - ' + current.label : '')); });
        $p.on('click', '[data-request-variant]', function () {
            const x = picker.variants.find((r) => r.id === $(this).data('request-variant'));
            window.openStockRequest(pid, x.id, $p.find('[data-stock-request-page]').data('name') + (x.label ? ' - ' + x.label : ''));
        });
        // quantity stepper around the native number input
        $p.on('click', '[data-qty-step]', function () {
            const $q = $p.find('[data-qty]').first(), max = parseInt($q.attr('max'), 10);
            let n = (parseInt($q.val(), 10) || 1) + Number($(this).data('qty-step'));
            n = Math.max(1, Number.isFinite(max) ? Math.min(max, n) : n);
            $q.val(n).trigger('change');
        });
        $p.on('click', '[data-sticky-add]', () => $btn.trigger('click'));
        $p.on('click', '[data-sticky-request]', () => $p.find('[data-stock-request-page]').first().trigger('click'));
        // sticky add-to-cart bar (below 1024px) once the buy box has scrolled out of view
        const $sticky = $p.find('[data-sticky-buy]'), box = $p.find('[data-buy-box]')[0];
        if ($sticky.length && box && 'IntersectionObserver' in window) {
            new IntersectionObserver(([e]) => {
                const show = !e.isIntersecting && e.boundingClientRect.top < 0;
                $sticky.attr('data-shown', show ? '' : null).attr('aria-hidden', String(!show)).find('button').attr('tabindex', show ? null : '-1');
            }).observe(box);
        }
        $btn.on('click', function () {
            if (!current) { toastr.error(T.unavailable); return; }
            $btn.prop('disabled', true);
            addToCart(pid, current.id, parseInt($p.find('[data-qty]').val(), 10) || 1).always(() => $btn.prop('disabled', !(current && current.in_stock)));
        });
        $p.on('click', '[data-wish]', function () {
            $.post($(this).data('wish')).done((r) => { toastr.success(r.message); setTimeout(() => location.reload(), 600); });
        });
        refresh();
        firstRefresh = false;
    });

    /* ---------- totals renderer ---------- */
    function renderTotals($dl, t, opts = {}) {
        if (!t) { $dl.empty(); return; }
        // Order totals (design 21:1971): label / value rows, the total in Price L brand, the COD split in a mint block
        const row = (label, val, cls = '') => '<div class="flex items-baseline justify-between gap-3 ' + (cls || 'text-slate-600') + '"><dt>' + esc(label) + '</dt><dd class="whitespace-nowrap font-semibold text-slate-950">' + val + '</dd></div>';
        let h = row(T.subtotal, money(t.subtotal));
        if (Number(t.discount) > 0) h += row(T.discount, '<span class="text-emerald-700">-' + money(t.discount) + '</span>', 'text-emerald-700');
        if (opts.shipping) h += row(T.shipping, Number(t.shippingNet) > 0 ? money(t.shippingNet) : esc(T.free));
        if (Number(t.packagingFee) > 0) h += row(T.packaging, money(t.packagingFee));
        if (Number(t.tax) > 0) h += row(T.tax, money(t.tax));
        if (Number(t.paymentFeeCharged) > 0) h += row(T.payment_fee, money(t.paymentFeeCharged));
        if (Number(t.codFee) > 0) h += row(t.codFeeLabel || T.cod_fee, money(t.codFee));
        h += '<div class="flex items-center justify-between gap-3 border-t border-slate-200 pt-3"><dt class="text-base font-bold text-slate-950">' + esc(T.total) + '</dt><dd class="whitespace-nowrap"><span class="text-brand-600 font-display text-[26px]/[32px] font-extrabold lg:text-[30px]/9">' + money(t.total) + '</span></dd></div>';
        if (Number(t.prepaidShipping) > 0 && Number(t.codAmount) > 0) { // COD split: delivery fee now, the rest on delivery
            const mint = (label, val, cls) => '<div class="flex items-baseline justify-between gap-3 bg-mint-100 px-3 text-xs font-semibold text-mint-700 ' + cls + '"><dt>' + esc(label) + '</dt><dd class="whitespace-nowrap font-bold">' + val + '</dd></div>';
            h += mint(T.prepaid_now, money(t.prepaidShipping), '!mb-0 rounded-t-xl pt-2.5') + mint(T.cod_balance, money(t.codAmount), '!mt-0 rounded-b-xl pb-2.5 pt-1');
        }
        $dl.html(h);
    }

    /* ---------- special packaging checkbox (cart + checkout): toggles the picker; unchecking clears the option ---------- */
    $(document).on('change', '[data-packaging-toggle]', function () {
        const $w = $(this).closest('[data-packaging]'), on = this.checked;
        $w.find('[data-packaging-picker]').toggleClass('hidden', !on);
        if (on) $w.find('[data-swiper]').each(function () { this.swiper?.update(); });
        const $r = $w.find('input[data-packaging-select]'); // radios; the first one (value "") means "no packaging"
        if (!on) { $r.filter('[value=""]').prop('checked', true).trigger('change'); }
        else if (!$r.filter(':checked').val()) { $r.filter('[value!=""]').first().prop('checked', true).trigger('change'); }
    });

    /* ---------- cart page ---------- */
    const icon = (d, cls = 'size-5') => '<svg class="' + cls + ' shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="' + d + '"/></svg>';
    const ICON = {
        trash: 'M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3',
        minus: 'M5 12h14', plus: 'M12 5v14M5 12h14',
        box: 'M21 8 12 3 3 8v8l9 5 9-5zM3 8l9 5 9-5M12 13v8',
        truck: 'M3 7h11v9H3zM14 10h4l3 3v3h-7M7 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4zM17 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4z',
    };
    const TINTS = ['bg-mint-100', 'bg-pop-50', 'bg-sun-100', 'bg-violet-100'];
    const thumb = (i, k, cls) => i.image ? '<img src="' + esc(i.image) + '" class="' + cls + ' block shrink-0 rounded-xl object-cover ' + TINTS[k % 4] + '" alt="" loading="lazy">' : '<span class="' + cls + ' block shrink-0 rounded-xl ' + TINTS[k % 4] + '" aria-hidden="true"></span>';
    $('[data-cart-page]').each(function () {
        const $pg = $(this);
        const href = (i) => (i.slug ? esc(location.pathname.replace(/\/cart.*$/, '') + (i.is_bundle ? '/bundle/' : '/product/') + i.slug) : '#');
        // Cart line (design 21:2005): Default / Unavailable; a bundle line sits under a gold "Bundle: …" header
        const line = (i, k) => '<div class="overflow-hidden rounded-2xl border bg-white ' + (i.is_bundle ? 'border-accent' : 'border-slate-200') + '" data-line="' + i.id + '">'
            + (i.is_bundle ? '<p class="flex items-center gap-2 bg-accent-light px-3 py-2 text-xs font-bold text-accent-dark">' + icon(ICON.box, 'size-4') + esc(T.bundle) + ': ' + esc(i.name) + '</p>' : '')
            + '<div class="flex gap-3 p-3">'
            + '<a href="' + href(i) + '" tabindex="-1" aria-hidden="true">' + thumb(i, k, 'size-20 sm:size-[88px]') + '</a>'
            + '<div class="flex min-w-0 flex-1 flex-col gap-1">'
            + '<div class="flex items-start gap-2"><a class="line-clamp-2 flex-1 text-sm font-bold text-slate-950 hover:text-brand-600" href="' + href(i) + '">' + esc(i.name) + '</a>'
            + '<button type="button" class="-me-1 -mt-1 grid size-9 shrink-0 place-items-center rounded-full text-slate-600 transition hover:bg-red-50 hover:text-red-700" data-remove aria-label="' + esc(T.remove + ': ' + (i.name || '')) + '">' + icon(ICON.trash, 'size-[18px]') + '</button></div>'
            + (i.is_bundle ? '<ul class="space-y-0.5 text-xs text-slate-600">' + (i.components || []).map((c) => '<li>' + esc(c.name) + ' &times; ' + (c.quantity * i.qty) + '</li>').join('') + '</ul>' : '')
            + '<p class="text-xs text-slate-600">' + esc(T.unit) + ': <bdi>' + money(i.unit_price) + '</bdi></p>'
            + (i.available ? '' : '<p><span class="badge bg-red-50 text-red-700">' + esc(T.item_unavailable) + '</span></p>')
            + '<div class="mt-auto flex flex-wrap items-center justify-between gap-2 pt-1">'
            + '<div class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white p-0.5" dir="ltr" role="group" aria-label="' + esc(T.qty) + '">'
            + '<button type="button" class="grid size-9 place-items-center rounded-full bg-brand-50 text-brand-600 transition hover:bg-brand-100" data-qty-minus aria-label="' + esc(T.qty_decrease) + '">' + icon(ICON.minus, 'size-4') + '</button>'
            + '<span class="min-w-8 text-center text-sm font-bold text-slate-950" aria-live="polite">' + i.qty + '</span>'
            + '<button type="button" class="grid size-9 place-items-center rounded-full bg-brand-50 text-brand-600 transition hover:bg-brand-100" data-qty-plus aria-label="' + esc(T.qty_increase) + '">' + icon(ICON.plus, 'size-4') + '</button></div>'
            + '<b class="price whitespace-nowrap text-base lg:text-lg">' + money(i.qty * i.unit_price) + '</b>'
            + '</div></div></div></div>';
        function render(c) {
            const has = c.items.length > 0;
            $pg.find('[data-cart-empty]').toggleClass('hidden', has);
            $pg.find('[data-cart-lines], [data-cart-summary], [data-cart-head]').toggleClass('hidden', !has);
            $pg.find('[data-cart-continue]').toggleClass('hidden', !has).toggleClass('inline-flex', has);
            $pg.find('[data-cart-flash]').toggleClass('hidden', !c.flash).toggleClass('flex', !!c.flash);
            $pg.find('[data-cart-lines]').html(c.items.map(line).join(''));
            renderTotals($pg.find('[data-totals]'), c.totals);
        }
        $(document).on('cart:updated', (_e, c) => render(c));
        $pg.on('change', '[data-packaging-select]', function () { cartCall('POST', sf.routes.quote, { packaging_option_id: $(this).val() }); });
        const find = (el) => cart.items.find((x) => x.id === $(el).closest('[data-line]').data('line'));
        $pg.on('click', '[data-qty-plus]', function () { const i = find(this); cartCall('PUT', itemUrl(i.id), { quantity: i.qty + 1 }); });
        $pg.on('click', '[data-qty-minus]', function () { const i = find(this); cartCall('PUT', itemUrl(i.id), { quantity: i.qty - 1 }); });
        $pg.on('click', '[data-remove]', function () { cartCall('DELETE', itemUrl(find(this).id)); });
        if (cart.items.length) render(cart);
    });

    /* ---------- checkout ---------- */
    $('[data-checkout]').each(function () {
        const $f = $(this);
        let shippingOpts = [], shippingRate = null;

        const cityId = () => {
            const $a = $f.find('input[name=address_id]:checked');
            return $a.length && $a.val() ? String($a.data('city')) : String($f.find('[name=city_id]').val() || '');
        };
        const quote = (fromApply) => {
            const data = $f.find('input[name=address_id]:checked, [name=city_id], [name=address], [name=shipping_company_id], input[name=payment_method_id]:checked, input[name=prepay_method_id]:checked, [name=discount_code], input[name=packaging_option_id]:checked').serialize();
            return $.post(sf.routes.quote, data).done((r) => {
                renderTotals($f.find('[data-totals]'), r.data.totals, { shipping: true });
                togglePrepay(r.data.totals);
                const applied = !!(fromApply && $f.find('[name=discount_code]').val());
                if (applied) toastr.success(T.code_applied);
                if (fromApply !== undefined) $f.find('[data-code-ok]').toggleClass('hidden', !applied).toggleClass('flex', applied);
            }).fail((xhr) => {
                const errs = xhr.responseJSON?.errors || {};
                if (errs.discount_code) { $f.find('[name=discount_code]').val(''); $f.find('[data-code-ok]').addClass('hidden').removeClass('flex'); toastr.error(errs.discount_code[0]); quote(false); return; }
                toastr.error(firstError(xhr));
            });
        };
        function renderLines(c) {
            $f.find('[data-checkout-lines]').html(c.items.map((i, k) => '<div class="flex items-center gap-3">' + thumb(i, k, 'size-11')
                + '<div class="min-w-0 flex-1"><p class="line-clamp-1 text-xs font-bold text-slate-950">' + esc(i.name) + '</p><p class="text-xs text-slate-600"><bdi>' + i.qty + ' &times; ' + money(i.unit_price) + '</bdi></p></div>'
                + '<b class="whitespace-nowrap text-xs font-bold text-slate-950">' + money(i.qty * i.unit_price) + '</b></div>').join(''));
        }
        function togglePrepay(t) { // COD with a delivery fee to prepay: ask for the online/wallet method
            const cod = $f.find('input[name=payment_method_id]:checked').data('type') === 'cod';
            const need = cod && t && Number(t.prepaidShipping) > 0;
            $f.find('[data-prepay]').toggleClass('hidden', !need);
            if (!need) $f.find('input[name=prepay_method_id]').prop('checked', false);
            toggleOfflineProof();
        }
        function toggleOfflineProof() { // file input only when the chosen method (or COD's wallet prepay method) is offline = wallet
            const $pm = $f.find('input[name=payment_method_id]:checked');
            const $pp = $f.find('[data-prepay]:not(.hidden) input[name=prepay_method_id]:checked');
            const $src = $pm.data('type') === 'wallet' ? $pm : ($pm.data('type') === 'cod' && $pp.data('type') === 'wallet' ? $pp : null);
            const $box = $f.find('[data-offline-proof]'), $file = $box.find('input[type=file]');
            $box.toggleClass('hidden', !$src);
            $file.prop('disabled', !$src); // disabled inputs are not sent -> COD/online never carry a file
            if (!$src) { $file.val(''); return; }
            const $d = $box.find('[data-offline-details]').empty();
            $('<b>').text($src.data('name') || '').appendTo($d);
            if ($src.data('instructions')) $('<div>').text($src.data('instructions')).appendTo($d);
            if ($src.data('account')) $('<div>').text($file.data('msg-transfer') + ': ' + $src.data('account')).appendTo($d);
        }
        const $proofName = $f.find('[data-proof-name]'), proofCta = $proofName.text();
        $f.on('change', 'input[type=file][name=proof]', function () { // client-side guard (the server re-checks everything)
            const f = this.files[0];
            $proofName.text(f ? f.name : proofCta);
            if (!f) return;
            const okType = /\.(jpe?g|png|webp|pdf)$/i.test(f.name) && (!f.type || /^(image\/(jpeg|png|webp)|application\/pdf)$/.test(f.type));
            if (!okType || f.size > Number($(this).data('max-kb')) * 1024) {
                toastr.error(!okType ? $(this).data('msg-type') : $(this).data('msg-size'));
                $(this).val(''); $proofName.text(proofCta);
            }
        });
        function applyCodRule() {
            $f.find('[data-method-row]').each(function () {
                const $r = $(this), $i = $r.find('input'), blocked = $i.data('type') === 'cod' && shippingRate && !shippingRate.supports_cod;
                $i.prop('disabled', !!blocked); $r.find('[data-cod-note]').toggleClass('hidden', !blocked);
                if (blocked && $i.prop('checked')) $i.prop('checked', false);
            });
        }
        function loadShipping() {
            const city = cityId();
            shippingRate = null; $f.find('[data-shipping-input]').val('');
            if (!city) { $f.find('[data-shipping-options]').html('<p class="rounded-2xl bg-slate-100 px-4 py-3 text-slate-600">' + esc(T.select_city_hint) + '</p>'); applyCodRule(); quote(); return; }
            $.get(sf.routes.shipping, { city_id: city }).done((r) => {
                shippingOpts = r.data.options;
                if (!shippingOpts.length) { $f.find('[data-shipping-options]').html('<p class="flex items-center gap-2 rounded-2xl bg-amber-50 px-4 py-3 font-bold text-amber-700" role="alert">' + icon(ICON.truck) + esc(T.no_shipping) + '</p>'); applyCodRule(); quote(); return; }
                // shipping choice cards (design 7:233): radio, truck, name + ETA, price at the end
                $f.find('[data-shipping-options]').html(shippingOpts.map((o, k) => '<label class="flex cursor-pointer items-start gap-3 rounded-2xl border-2 border-slate-200 bg-white p-3.5 transition hover:border-brand-300 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 has-[:focus-visible]:ring-[3px] has-[:focus-visible]:ring-pop-500/45">'
                    + '<input type="radio" name="ship_opt" value="' + o.id + '" class="mt-0.5 size-[22px] shrink-0 accent-brand-600"' + (k === 0 ? ' checked' : '') + '>'
                    + '<span class="min-w-0 flex-1"><span class="flex items-center gap-2 text-base font-bold text-slate-950"><span class="text-mint-700">' + icon(ICON.truck) + '</span>' + esc(o.name) + '</span>'
                    + (o.eta && o.eta !== '—' ? '<span class="mt-0.5 block text-xs text-slate-600">' + esc(T.eta.replace(':d', o.eta)) + '</span>' : '') + '</span>'
                    + '<b class="whitespace-nowrap text-sm ' + (o.free ? 'text-mint-700' : 'text-slate-950') + '">' + (o.free ? esc(T.free) : money(o.fee)) + '</b></label>').join(''));
                selectShipping(shippingOpts[0].id);
            });
        }
        function selectShipping(id) {
            shippingRate = shippingOpts.find((o) => String(o.id) === String(id)) || null;
            $f.find('[data-shipping-input]').val(shippingRate ? shippingRate.id : '');
            applyCodRule(); quote();
        }
        $f.on('change', 'input[name=ship_opt]', function () { selectShipping(this.value); });
        $f.on('change', 'input[name=payment_method_id], input[name=prepay_method_id]', () => { $f.find('[data-payment-options]').removeClass('is-invalid').next('.field-error').remove(); toggleOfflineProof(); quote(); });
        $f.on('change', 'input[name=address_id]', loadShipping);

        // add / edit address modal (the form lives outside the checkout form)
        const $modal = $('#address-modal'), $af = $modal.find('[data-address-form]');
        const openAddress = (a) => {
            $af[0].reset(); $af.find('.is-invalid').removeClass('is-invalid'); $af.find('.field-error').remove();
            $af.find('[name=_method]').remove();
            if (a) {
                $af.attr('action', a.url).data('method', 'PUT');
                $af.find('[name=label]').val(a.label || ''); $af.find('[name=name]').val(a.name); $af.find('[name=phone]').val(a.phone);
                $af.find('[name=city_id]').val(String(a.city_id)); $af.find('[name=address]').val(a.address); $af.find('[name=is_default]').prop('checked', !!a.is_default);
            } else { $af.attr('action', $af.data('store-url')).removeData('method'); }
            $modal.find('h3').text($modal.find('[data-title-add]').data('title-' + (a ? 'edit' : 'add')));
            lastFocus = document.activeElement;
            $modal.removeClass('hidden').addClass('flex');
            document.documentElement.classList.add('overflow-hidden');
            setTimeout(() => $af.find('input:not([type=hidden]), select').first().trigger('focus'), 30);
        };
        let lastFocus = null;
        const closeAddress = () => { if ($modal.hasClass('hidden')) return; $modal.addClass('hidden').removeClass('flex'); document.documentElement.classList.remove('overflow-hidden'); lastFocus?.focus?.(); };
        $modal.on('click', function (e) { if (e.target === this) closeAddress(); }).on('click', '[data-modal-close]', closeAddress);
        $(document).on('keydown', (e) => { if (e.key === 'Escape') closeAddress(); });
        // the first invalid field gets focus after a refused save
        $(document).ajaxComplete((_e, xhr, o) => { if (xhr.status === 422 && !$modal.hasClass('hidden') && (o.url === $af.data('validate-url') || o.url === $af.attr('action'))) setTimeout(() => $af.find('.is-invalid').first().trigger('focus'), 0); });
        $modal.on('keydown', function (e) {
            if (e.key !== 'Tab') return; // keep focus inside the dialog
            const f = $modal.find('a[href], button:not([disabled]), input:not([type=hidden]):not([disabled]), select, textarea').filter(':visible');
            if (!f.length) return;
            if (e.shiftKey && document.activeElement === f[0]) { e.preventDefault(); f.last().trigger('focus'); } else if (!e.shiftKey && document.activeElement === f[f.length - 1]) { e.preventDefault(); f.first().trigger('focus'); }
        });
        $f.on('click', '[data-address-add]', () => openAddress(null));
        $f.on('click', '[data-address-edit]', function (e) { e.preventDefault(); openAddress($(this).data('address')); });
        $af.on('ajax-form:success', (_e, res) => {
            closeAddress();
            $f.find('[data-step=address]').removeClass('is-error').find('.field-error').remove();
            const $list = $f.find('[data-address-list]');
            $.get($list.data('list-url'), { select: res.data?.address?.id }).done((html) => { $list.html(html); loadShipping(); });
        });
        $f.on('change', '[name=city_id]', loadShipping);
        $f.on('change', '[data-packaging-select]', () => quote());
        $f.on('click', '[data-apply-code]', () => quote(true));
        $f.on('keydown', '[name=discount_code]', (e) => { if (e.key === 'Enter') { e.preventDefault(); quote(true); } });
        // checkout errors (design 32:2080): a summary alert on top and a red address step when the address is missing
        $(document).ajaxComplete((_e, xhr, o) => {
            if (o.url !== $f.data('validate-url') && o.url !== $f.attr('action')) return;
            const errs = xhr.status === 422 ? (xhr.responseJSON?.errors || {}) : null;
            const $sum = $f.find('[data-checkout-errors]').toggleClass('hidden', !errs).toggleClass('flex', !!errs);
            $f.find('[data-step=address]').toggleClass('is-error', !!(errs && errs.address_id));
            if (errs && !$f.find('.is-invalid').length) $sum[0].scrollIntoView({ block: 'center', behavior: 'smooth' });
        });
        $f.on('change', 'input[name=address_id]', function () { $f.find('[data-step=address]').removeClass('is-error').find('.field-error').remove(); });
        $(document).on('cart:updated', (_e, c) => { if (!c.items.length) { location.href = sf.routes.cart; return; } renderLines(c); });
        if (cart.items.length) renderLines(cart);
        loadShipping();
    });

    /* ---------- account: pill tabs below 1024px (all panels show from 1024) ---------- */
    $('[data-account-tabs]').each(function () {
        const $tabs = $(this).find('[data-account-tab]');
        const show = (name, focus) => {
            $tabs.each(function () { const on = this.dataset.accountTab === name; $(this).attr({ 'aria-selected': String(on), tabindex: on ? null : '-1' }); if (on && focus) this.focus(); });
            $('[data-account-panel]').each(function () { $(this).toggleClass('max-lg:hidden', this.dataset.accountPanel !== name); });
        };
        $tabs.on('click', function () { show(this.dataset.accountTab); history.replaceState(null, '', '#' + this.dataset.accountTab); });
        $tabs.on('keydown', function (e) {
            const k = { ArrowRight: -1, ArrowLeft: 1 }[e.key]; if (!k) return;
            const i = $tabs.index(this), dir = document.documentElement.dir === 'rtl' ? k : -k;
            show($tabs.eq((i + dir + $tabs.length) % $tabs.length).data('account-tab'), true);
        });
        const want = location.hash.slice(1);
        if ($tabs.filter('[data-account-tab="' + want + '"]').length) show(want);
        // a validation error inside a hidden panel: show that panel
        $(document).ajaxComplete((_e, xhr) => { if (xhr.status !== 422) return; setTimeout(() => { const p = $('[data-account-panel] .is-invalid').first().closest('[data-account-panel]').data('account-panel'); if (p) show(p); }, 0); });
    });

    /* copy a value (order number, affiliate links: data-copied = the toast text) */
    $(document).on('click', '[data-copy]', async function () {
        try { await navigator.clipboard.writeText(String(this.dataset.copy)); toastr.success(this.dataset.copied || T.copied); } catch (e) { window.prompt('', this.dataset.copy); }
    });
    /* affiliate dashboard: POST action buttons (request payout), same contract as the admin ops-form helper */
    $(document).on('click', '[data-post-action]', function () {
        const $b = $(this);
        if ($b.data('confirm') && !window.confirm($b.data('confirm'))) return;
        $b.prop('disabled', true);
        $.ajax({ url: $b.data('post-action'), method: 'POST' })
            .done((res) => { if (res.message) toastr.success(res.message); setTimeout(() => (res.redirect ? (location.href = res.redirect) : location.reload()), 500); })
            .fail((xhr) => { toastr.error(firstError(xhr)); $b.prop('disabled', false); });
    });
    /* file picker inside a drop box: show the chosen file name */
    $(document).on('change', 'input[type=file][data-file-label]', function () {
        const $t = $(this).closest('label').find('[data-file-text]');
        if (!$t.data('cta')) $t.data('cta', $t.text());
        $t.text(this.files[0]?.name || $t.data('cta'));
    });

    /* ---------- retry an online payment from the order page ---------- */
    $(document).on('click', '[data-pay-action]', function () {
        const $b = $(this).prop('disabled', true);
        $.post($b.data('pay-action')).done((r) => { location.href = r.data?.pay_url || r.redirect; })
            .fail((xhr) => { toastr.error(firstError(xhr)); $b.prop('disabled', false); });
    });

    loadCart();
}
