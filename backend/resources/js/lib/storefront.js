import $ from 'jquery';
import toastr from 'toastr';

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
        if (!c.items.length) { $b.html('<p class="py-4 text-center text-slate-500">' + esc(T.cart_empty) + '</p>'); $('[data-mini-cart-foot]').addClass('hidden'); return; }
        $b.html(c.items.map((i) => '<div class="flex gap-2 py-2">' + (i.image ? '<img src="' + esc(i.image) + '" class="size-12 rounded object-cover" alt="">' : '<div class="size-12 rounded bg-slate-100"></div>')
            + '<div class="min-w-0 flex-1"><p class="line-clamp-1 font-semibold">' + esc(i.name) + '</p><p class="text-xs text-slate-500">' + i.qty + ' &times; ' + money(i.unit_price) + '</p></div>'
            + '<button type="button" data-mini-remove="' + i.id + '" class="self-start text-lg leading-none text-slate-400 hover:text-red-600" aria-label="' + esc(T.remove) + '">&times;</button></div>').join(''));
        $('[data-mini-cart-foot]').removeClass('hidden');
        $('[data-mini-subtotal]').text(c.totals ? money(c.totals.subtotal) : '');
    }
    $('#mini-cart').on('click', '[data-mini-remove]', function () { cartCall('DELETE', itemUrl($(this).data('mini-remove'))); });

    $(document).on('click', '[data-add-to-cart]', function () {
        const $b = $(this); $b.prop('disabled', true);
        addToCart($b.data('product'), $b.data('variant'), 1).always(() => $b.prop('disabled', false));
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
    $(document).on('click', '[data-drawer-open]', () => $('[data-drawer]').removeClass('hidden'));
    $(document).on('click', '[data-drawer-close]', () => $('[data-drawer]').addClass('hidden'));
    $(document).on('click', '[data-filters-toggle]', () => $('[data-filter-form]').toggleClass('hidden'));

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
                    if (r.categories.length) h += '<p class="bg-slate-50 px-4 py-1 text-xs font-bold text-slate-500">' + esc(T.categories) + '</p>' + r.categories.map((c) => '<a class="block px-4 py-2 text-sm hover:bg-slate-50" href="' + esc(c.url) + '">' + esc(c.name) + '</a>').join('');
                    if (r.products.length) h += '<p class="bg-slate-50 px-4 py-1 text-xs font-bold text-slate-500">' + esc(T.products) + '</p>' + r.products.map((p) => '<a class="flex items-center gap-3 px-4 py-2 text-sm hover:bg-slate-50" href="' + esc(p.url) + '">'
                        + (p.image ? '<img src="' + esc(p.image) + '" class="size-10 rounded object-cover" alt="">' : '<span class="size-10 rounded bg-slate-100"></span>') + '<span class="flex-1">' + esc(p.name) + '</span><b class="text-brand-600">' + money(p.price) + '</b></a>').join('');
                    $box.html(h || '<p class="px-4 py-3 text-sm text-slate-500">' + esc(T.no_results) + '</p>').removeClass('hidden');
                });
            }, 250);
        });
        $(document).on('click', (e) => { if (!$(e.target).closest('[data-search-form]').length) hide(); });
        $in.on('keydown', (e) => { if (e.key === 'Escape') hide(); });
    })();

    /* ---------- hero slider + popup ---------- */
    // Swipeable slider (no library): pointer drag follows the finger, snaps on release, autoplay pauses while touched/hovered, dots + arrows + keys.
    $('[data-slider]').each(function () {
        const $s = $(this), $track = $s.find('[data-slides]'), n = $s.find('[data-slide]').length, $dots = $s.find('[data-dots] span'), rtl = sf.dir === 'rtl', dir = rtl ? 1 : -1;
        let i = 0, timer, startX = 0, startY = 0, dx = 0, dragging = false, locked = null, moved = false;
        const width = () => $s.width() || 1;
        const render = (px, animate) => {
            $track.css({ transition: animate ? 'transform .45s ease' : 'none', transform: 'translate3d(' + (dir * i * width() + px) + 'px,0,0)' });
        };
        const go = (k) => {
            i = (k + n) % n; // wraps around
            render(0, true);
            $dots.removeClass('bg-white').addClass('bg-white/60').eq(i).removeClass('bg-white/60').addClass('bg-white');
        };
        const auto = () => { clearInterval(timer); if (n > 1 && !matchMedia('(prefers-reduced-motion: reduce)').matches) timer = setInterval(() => go(i + 1), 5000); };
        if (n > 1) {
            $s.css('touch-action', 'pan-y').attr({ tabindex: 0, role: 'region' });
            $s.on('pointerdown', (e) => {
                if (e.pointerType === 'mouse' && e.button !== 0) return;
                dragging = true; locked = null; moved = false; dx = 0; startX = e.clientX; startY = e.clientY; clearInterval(timer);
            });
            $(window).on('pointermove', (e) => {
                if (!dragging) return;
                const mx = e.clientX - startX, my = e.clientY - startY;
                if (locked === null && (Math.abs(mx) > 6 || Math.abs(my) > 6)) locked = Math.abs(mx) > Math.abs(my) ? 'x' : 'y';
                if (locked !== 'x') return;
                moved = true; dx = mx;
                render(dx, false);
            });
            const release = () => {
                if (!dragging) return; dragging = false;
                if (locked === 'x' && Math.abs(dx) > Math.min(80, width() * 0.15)) go(i + (dx * dir > 0 ? 1 : -1)); // dragging the track toward `dir` reveals the next slide
                else render(0, true);
                dx = 0; auto();
            };
            $(window).on('pointerup pointercancel', release);
            // a drag must not open the banner link
            $s.on('click', 'a', (e) => { if (moved) { e.preventDefault(); moved = false; } });
            $s.on('dragstart', 'img', (e) => e.preventDefault());
            $s.on('mouseenter', () => clearInterval(timer)).on('mouseleave', () => { if (!dragging) auto(); });
            $s.on('keydown', (e) => { if (e.key === 'ArrowRight') { go(i + (rtl ? -1 : 1)); auto(); } else if (e.key === 'ArrowLeft') { go(i + (rtl ? 1 : -1)); auto(); } });
            $s.on('click', '[data-dots] span', function () { go($(this).index()); auto(); });
            $(window).on('resize', () => render(0, false));
        }
        $s.on('click', '[data-slide-next]', (e) => { e.preventDefault(); go(i + 1); auto(); });
        $s.on('click', '[data-slide-prev]', (e) => { e.preventDefault(); go(i - 1); auto(); });
        go(0); auto();
    });
    $('[data-popup]').each(function () {
        const key = 'popup-' + $(this).data('popup'); let seen = false;
        try { seen = sessionStorage.getItem(key); } catch (e) { /* ignore */ }
        if (seen) return;
        const $p = $(this);
        setTimeout(() => $p.removeClass('hidden').addClass('grid'), 1500);
        $p.on('click', (e) => { if (e.target === this || $(e.target).closest('[data-popup-close]').length) { $p.addClass('hidden').removeClass('grid'); try { sessionStorage.setItem(key, '1'); } catch (er) { /* ignore */ } } });
    });

    /* ---------- catalog: AJAX filters ---------- */
    $('[data-catalog]').each(function () {
        const $c = $(this), $form = $c.find('[data-filter-form]'), action = $c.data('action');
        let busy = null;
        const load = (page, push = true) => {
            const params = $form.serialize() + (page > 1 ? '&page=' + page : '');
            $c.find('[data-grid]').css('opacity', 0.5);
            if (busy) busy.abort();
            busy = $.get(action, params).done((r) => {
                $c.find('[data-grid]').html(r.html).css('opacity', 1);
                $c.find('[data-pagination]').html(r.pagination);
                $c.find('[data-count]').text(r.label);
                if (push) history.pushState(null, '', action + '?' + params);
                $c.find('[data-grid]')[0].scrollIntoView({ block: 'start', behavior: 'smooth' });
            }).fail((x) => { if (x.statusText !== 'abort') { $c.find('[data-grid]').css('opacity', 1); toastr.error(window.__t?.error); } });
        };
        let t;
        $form.on('change', 'input[type=checkbox]', () => load(1));
        $form.on('input', 'input[type=number]', () => { clearTimeout(t); t = setTimeout(() => load(1), 600); });
        $form.on('submit', (e) => { e.preventDefault(); load(1); });
        $form.on('click', '[data-filter-reset]', function (e) { e.preventDefault(); $form.find('input[type=checkbox]').prop('checked', false); $form.find('input[type=number]').val(''); load(1); $(this).remove(); });
        $c.on('change', '[data-sort]', function () { $form.find('[data-sort-input]').val(this.value); load(1); });
        $c.on('click', '[data-page-link]', function (e) { e.preventDefault(); const p = new URL(this.href).searchParams.get('page'); load(parseInt(p, 10) || 1); });
        window.addEventListener('popstate', () => location.reload());
    });

    /* ---------- product page ---------- */
    $('[data-product-page]').each(function () {
        const $p = $(this), picker = JSON.parse($p.attr('data-picker')), pid = $p.data('product-id');
        const sel = {}; // attribute id -> value id
        let current = null, flashTimer;
        const $btn = $p.find('[data-add-to-cart-page]');
        const matches = (v, s) => Object.values(s).every((id) => v.values.includes(id));
        const nGroups = picker.options.length;

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
            $p.find('[data-sku]').text(v ? v.sku || '' : '');
            $p.find('[data-barcode]').text(v ? v.barcode || '' : '');
            let st = '';
            if (v) st = !v.in_stock ? '<span class="text-red-600">' + esc(T.out_of_stock) + '</span>' : (v.stock !== null && v.stock <= (v.alert ?? picker.alert ?? 5) ? '<span class="text-amber-600">' + esc(T.low_stock.replace(':n', v.stock)) + '</span>' : '<span class="text-emerald-600">' + esc(T.in_stock) + '</span>');
            $p.find('[data-stock]').html(st);
            if (v && v.image) $p.find('[data-main-image]').attr('src', v.image);
            $btn.prop('disabled', !(v && v.in_stock));
            $p.find('[data-stock-request-page]').toggleClass('hidden', !(v && !v.in_stock)).data('variant', v ? v.id : 0);
            $p.find('[data-variant-status]').toggleClass('hidden', !picker.variable).html(picker.variable ? '<p class="mb-2 text-sm font-bold">' + esc(T.variants_availability) + '</p>' + picker.variants.map((x) => {
                const state = !x.in_stock ? '<span class="text-red-600">' + esc(T.out_of_stock) + '</span>' : (x.stock !== null && x.stock <= (x.alert ?? picker.alert ?? 5) ? '<span class="text-amber-600">' + esc(T.low_stock.replace(':n', x.stock)) + '</span>' : '<span class="text-emerald-600">' + esc(T.in_stock) + '</span>');
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
        $p.on('click', '[data-thumb]', function () {
            $p.find('[data-main-image]').attr('src', $(this).data('thumb'));
            $p.find('[data-thumb]').removeClass('border-brand-600').addClass('border-transparent'); $(this).addClass('border-brand-600').removeClass('border-transparent');
        });
        $btn.on('click', function () {
            if (!current) { toastr.error(T.unavailable); return; }
            $btn.prop('disabled', true);
            addToCart(pid, current.id, parseInt($p.find('[data-qty]').val(), 10) || 1).always(() => $btn.prop('disabled', !(current && current.in_stock)));
        });
        $p.on('click', '[data-wish]', function () {
            $.post($(this).data('wish')).done((r) => { toastr.success(r.message); setTimeout(() => location.reload(), 600); });
        });
        refresh();
    });

    /* ---------- totals renderer ---------- */
    function renderTotals($dl, t, opts = {}) {
        if (!t) { $dl.empty(); return; }
        const row = (label, val, cls = '') => '<div class="flex justify-between ' + cls + '"><dt>' + esc(label) + '</dt><dd>' + val + '</dd></div>';
        let h = row(T.subtotal, money(t.subtotal));
        if (Number(t.discount) > 0) h += row(T.discount, '-' + money(t.discount), 'text-emerald-600');
        if (opts.shipping) h += row(T.shipping, Number(t.shippingNet) > 0 ? money(t.shippingNet) : esc(T.free));
        if (Number(t.packagingFee) > 0) h += row(T.packaging, money(t.packagingFee));
        if (Number(t.tax) > 0) h += row(T.tax, money(t.tax));
        if (Number(t.paymentFeeCharged) > 0) h += row(T.payment_fee, money(t.paymentFeeCharged));
        if (Number(t.codFee) > 0) h += row(t.codFeeLabel || T.cod_fee, money(t.codFee));
        h += row(T.total, '<span class="text-brand-600">' + money(t.total) + '</span>', 'border-t border-slate-100 pt-2 text-base font-extrabold');
        if (Number(t.prepaidShipping) > 0 && Number(t.codAmount) > 0) { // COD split: delivery fee now, the rest on delivery
            h += row(T.prepaid_now, money(t.prepaidShipping), 'text-sm') + row(T.cod_balance, money(t.codAmount), 'text-sm');
        }
        $dl.html(h);
    }

    /* ---------- special packaging checkbox (cart + checkout): toggles the picker; unchecking clears the option ---------- */
    $(document).on('change', '[data-packaging-toggle]', function () {
        const $w = $(this).closest('[data-packaging]'), on = this.checked;
        $w.find('[data-packaging-picker]').toggleClass('hidden', !on);
        const $r = $w.find('input[data-packaging-select]'); // radios; the first one (value "") means "no packaging"
        if (!on) { $r.filter('[value=""]').prop('checked', true).trigger('change'); }
        else if (!$r.filter(':checked').val()) { $r.filter('[value!=""]').first().prop('checked', true).trigger('change'); }
    });

    /* ---------- cart page ---------- */
    $('[data-cart-page]').each(function () {
        const $pg = $(this);
        function render(c) {
            const has = c.items.length > 0;
            $pg.find('[data-cart-empty]').toggleClass('hidden', has);
            $pg.find('[data-cart-lines]').toggleClass('hidden', !has);
            $pg.find('[data-cart-summary]').toggleClass('hidden', !has);
            $pg.find('[data-cart-flash]').toggleClass('hidden', !c.flash);
            $pg.find('[data-cart-lines]').html(c.items.map((i) => '<div class="flex flex-wrap items-center gap-3 p-3" data-line="' + i.id + '">'
                + (i.image ? '<img src="' + esc(i.image) + '" class="size-20 rounded-lg object-cover" alt="">' : '<div class="size-20 rounded-lg bg-slate-100"></div>')
                + '<div class="min-w-0 flex-1"><a class="font-semibold hover:text-brand-600" href="' + (i.slug ? esc(location.pathname.replace(/\/cart.*$/, '') + (i.is_bundle ? '/bundle/' : '/product/') + i.slug) : '#') + '">' + esc(i.name) + '</a>'
                + (i.is_bundle ? '<ul class="mt-1 list-disc ps-5 text-xs text-slate-500">' + (i.components || []).map((c) => '<li>' + esc(c.name) + ' &times; ' + (c.quantity * i.qty) + '</li>').join('') + '</ul>' : '')
                + (i.available ? '' : '<p class="text-xs text-red-600">' + esc(T.item_unavailable) + '</p>') + '<p class="text-sm text-slate-500">' + esc(T.unit) + ': ' + money(i.unit_price) + '</p></div>'
                + '<div class="flex items-center rounded-lg border border-slate-300"><button type="button" class="px-3 py-1.5" data-qty-minus aria-label="-">&minus;</button><span class="min-w-8 text-center text-sm font-bold">' + i.qty + '</span><button type="button" class="px-3 py-1.5" data-qty-plus aria-label="+">+</button></div>'
                + '<b class="w-28 text-end">' + money(i.qty * i.unit_price) + '</b>'
                + '<button type="button" class="text-2xl leading-none text-slate-400 hover:text-red-600" data-remove aria-label="' + esc(T.remove) + '">&times;</button></div>').join(''));
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
                if (fromApply && $f.find('[name=discount_code]').val()) toastr.success(T.code_applied);
            }).fail((xhr) => {
                const errs = xhr.responseJSON?.errors || {};
                if (errs.discount_code) { $f.find('[name=discount_code]').val(''); toastr.error(errs.discount_code[0]); quote(false); return; }
                toastr.error(firstError(xhr));
            });
        };
        function renderLines(c) {
            $f.find('[data-checkout-lines]').html(c.items.map((i) => '<div class="flex justify-between gap-2 py-2"><span>' + esc(i.name) + ' <span class="text-slate-400">&times; ' + i.qty + '</span></span><b>' + money(i.qty * i.unit_price) + '</b></div>').join(''));
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
        $f.on('change', 'input[type=file][name=proof]', function () { // client-side guard (the server re-checks everything)
            const f = this.files[0];
            if (!f) return;
            const okType = /\.(jpe?g|png|webp|pdf)$/i.test(f.name) && (!f.type || /^(image\/(jpeg|png|webp)|application\/pdf)$/.test(f.type));
            if (!okType || f.size > Number($(this).data('max-kb')) * 1024) {
                toastr.error(!okType ? $(this).data('msg-type') : $(this).data('msg-size'));
                $(this).val('');
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
            if (!city) { $f.find('[data-shipping-options]').html('<p class="text-slate-500">' + esc(T.select_city_hint) + '</p>'); applyCodRule(); quote(); return; }
            $.get(sf.routes.shipping, { city_id: city }).done((r) => {
                shippingOpts = r.data.options;
                if (!shippingOpts.length) { $f.find('[data-shipping-options]').html('<p class="text-red-600">' + esc(T.no_shipping) + '</p>'); applyCodRule(); quote(); return; }
                $f.find('[data-shipping-options]').html(shippingOpts.map((o, k) => '<label class="flex cursor-pointer items-center justify-between gap-2 rounded-lg border border-slate-200 p-3 has-[:checked]:border-brand-600 has-[:checked]:bg-brand-50">'
                    + '<span class="flex items-center gap-2"><input type="radio" name="ship_opt" value="' + o.id + '" class="accent-brand-600"' + (k === 0 ? ' checked' : '') + '><b>' + esc(o.name) + '</b>'
                    + (o.eta && o.eta !== '—' ? '<small class="text-slate-500">' + esc(T.eta.replace(':d', o.eta)) + '</small>' : '') + '</span>'
                    + '<span class="text-end"><b>' + (o.free ? esc(T.free) : money(o.fee)) + '</b></span></label>').join(''));
                selectShipping(shippingOpts[0].id);
            });
        }
        function selectShipping(id) {
            shippingRate = shippingOpts.find((o) => String(o.id) === String(id)) || null;
            $f.find('[data-shipping-input]').val(shippingRate ? shippingRate.id : '');
            applyCodRule(); quote();
        }
        $f.on('change', 'input[name=ship_opt]', function () { selectShipping(this.value); });
        $f.on('change', 'input[name=payment_method_id], input[name=prepay_method_id]', () => { toggleOfflineProof(); quote(); });
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
            $modal.removeClass('hidden').addClass('flex');
        };
        $f.on('click', '[data-address-add]', () => openAddress(null));
        $f.on('click', '[data-address-edit]', function (e) { e.preventDefault(); openAddress($(this).data('address')); });
        $af.on('ajax-form:success', (_e, res) => {
            $modal.addClass('hidden').removeClass('flex');
            const $list = $f.find('[data-address-list]');
            $.get($list.data('list-url'), { select: res.data?.address?.id }).done((html) => { $list.html(html); loadShipping(); });
        });
        $f.on('change', '[name=city_id]', loadShipping);
        $f.on('change', '[data-packaging-select]', () => quote());
        $f.on('click', '[data-apply-code]', () => quote(true));
        $f.on('keydown', '[name=discount_code]', (e) => { if (e.key === 'Enter') { e.preventDefault(); quote(true); } });
        $(document).on('cart:updated', (_e, c) => { if (!c.items.length) { location.href = sf.routes.cart; return; } renderLines(c); });
        if (cart.items.length) renderLines(cart);
        loadShipping();
    });

    /* ---------- retry an online payment from the order page ---------- */
    $(document).on('click', '[data-pay-action]', function () {
        const $b = $(this).prop('disabled', true);
        $.post($b.data('pay-action')).done((r) => { location.href = r.data?.pay_url || r.redirect; })
            .fail((xhr) => { toastr.error(firstError(xhr)); $b.prop('disabled', false); });
    });

    loadCart();
}

// Horizontal rows: [data-scroll-row] gets a left and a right arrow (physical sides, works in RTL), hidden at the ends.
const initScrollRows = () => {
    document.querySelectorAll('[data-scroll-row]:not([data-scroll-ready])').forEach((row) => {
        row.dataset.scrollReady = '1';
        const wrap = document.createElement('div');
        wrap.className = 'relative';
        row.parentNode.insertBefore(wrap, row);
        wrap.appendChild(row);
        const mk = (dir) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.dir = 'ltr';
            b.setAttribute('aria-label', dir < 0 ? 'Scroll left' : 'Scroll right');
            b.className = `absolute top-1/2 z-10 hidden size-9 -translate-y-1/2 place-items-center rounded-full bg-white text-lg text-slate-700 shadow ring-1 ring-slate-200 hover:text-brand-600 sm:grid ${dir < 0 ? 'left-1' : 'right-1'}`;
            b.textContent = dir < 0 ? '‹' : '›';
            b.addEventListener('click', () => row.scrollBy({ left: dir * row.clientWidth * 0.8, behavior: 'smooth' }));
            wrap.appendChild(b);
            return b;
        };
        const l = mk(-1), r = mk(1);
        const sync = () => {
            const max = row.scrollWidth - row.clientWidth;
            const x = Math.abs(row.scrollLeft);
            const rtl = getComputedStyle(row).direction === 'rtl';
            const atLeft = rtl ? x >= max - 2 : x <= 2;
            const atRight = rtl ? x <= 2 : x >= max - 2;
            l.style.visibility = max > 2 && !atLeft ? '' : 'hidden';
            r.style.visibility = max > 2 && !atRight ? '' : 'hidden';
        };
        row.addEventListener('scroll', sync, { passive: true });
        window.addEventListener('resize', sync);
        sync();
        setTimeout(sync, 400);
    });
};
document.addEventListener('DOMContentLoaded', initScrollRows);
