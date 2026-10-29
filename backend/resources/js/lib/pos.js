import $ from 'jquery';
import toastr from 'toastr';
import TomSelect from 'tom-select';
import { clearErrors, showErrors } from './ajax-form';

// Point of sale (tenant admin): product finder -> cart -> live quote -> place order. Server stays the only source of prices/totals.
const $pos = $('#pos');
if ($pos.length) {
    const t = $pos.data('i18n');
    const sym = $pos.data('symbol') || '';
    const $form = $('#pos-form');
    const cart = new Map(); // "pid-vid" -> {product_id, variant_id, name, price, qty, stock, image}
    const money = (v) => `${Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${sym}`.trim();
    const debounce = (fn, ms) => { let h; return (...a) => { clearTimeout(h); h = setTimeout(() => fn(...a), ms); }; };
    const ajax = (url, fd) => $.ajax({ url, method: 'POST', data: fd, processData: false, contentType: false });

    // ---------- products ----------
    const $list = $('#pos-products');
    let searchSeq = 0;
    const loadProducts = (q) => {
        const seq = ++searchSeq;
        $.getJSON($pos.data('url-products'), { q }).done((res) => {
            if (seq !== searchSeq) return;
            $list.empty();
            if (!res.items.length) return $list.append($('<p class="col-span-full py-6 text-center text-sm text-slate-500">').text(t.no_products));
            res.items.forEach((p) => {
                const $img = p.image ? $('<img class="size-14 shrink-0 rounded-lg object-cover" alt="">').attr('src', p.image) : $('<div class="size-14 shrink-0 rounded-lg bg-slate-100">');
                const $badge = $('<span class="text-xs">').addClass(p.stock > 0 ? 'text-emerald-600' : 'font-medium text-red-600').text(p.stock > 0 ? t.in_stock.replace(':n', p.stock) : t.out_of_stock);
                const $b = $('<button type="button" class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-2 text-start transition hover:border-brand-500 hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500/30">')
                    .append($img, $('<div class="min-w-0 flex-1">').append(
                        $('<div class="truncate text-sm font-medium text-slate-800">').text(p.name),
                        $('<div class="text-xs text-slate-500">').text(p.sku || ''),
                        $('<div class="flex items-center justify-between gap-2">').append($('<span class="text-sm font-semibold text-slate-900">').text(money(p.price)), $badge)));
                $b.on('click', () => addToCart(p));
                $list.append($b);
            });
        });
    };
    $('#pos-product-search').on('input', debounce(function () { loadProducts(this.value.trim()); }, 250)).on('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); const $f = $list.children('button').first(); if ($f.length) $f.trigger('click'); } // scanner / quick add: first hit
    });
    loadProducts('');

    // ---------- cart ----------
    const addToCart = (p) => {
        const row = cart.get(p.key);
        if (row) row.qty += 1; else cart.set(p.key, { ...p, qty: 1 });
        renderCart();
        changed(true);
    };
    const renderCart = () => {
        const $c = $('#pos-cart').empty();
        if (!cart.size) return $c.append($('<p class="py-6 text-center text-sm text-slate-500">').text(t.cart_empty));
        cart.forEach((r, key) => {
            const $qty = $('<input type="number" min="1" max="9999" class="form-control w-20 text-center">').val(r.qty).on('change input', function () {
                r.qty = Math.max(1, parseInt(this.value, 10) || 1);
                $line.text(money(Number(r.price) * r.qty));
                changed(true);
            });
            const $line = $('<span class="w-24 text-end text-sm font-semibold text-slate-800">').text(money(Number(r.price) * r.qty));
            const $rm = $('<button type="button" class="btn-sm btn-danger" aria-label="">').attr('title', t.remove).html('&times;').on('click', () => { cart.delete(key); renderCart(); changed(true); });
            $c.append($('<div class="flex items-center gap-3 py-2">').append(
                $('<div class="min-w-0 flex-1"><div class="truncate text-sm font-medium text-slate-800"></div><div class="text-xs text-slate-500"></div></div>')
                    .find('div:first').text(r.name).end().find('div:last').text(money(r.price)).end(), $qty, $line, $rm));
        });
    };
    $('#pos-clear').on('click', () => { cart.clear(); renderCart(); changed(true); });
    renderCart();

    // ---------- customer (remote search + quick create) ----------
    const customerLabel = (c) => $('<div class="flex items-center justify-between gap-3">').append(
        $('<div>').append($('<div class="font-medium">').text(c.name), $('<div class="text-xs text-slate-500" dir="ltr">').text([c.phone_display || c.phone, c.email].filter(Boolean).join(' · '))),
        $('<span class="shrink-0 text-xs text-slate-400">').text(t.orders_count.replace(':n', c.orders))).prop('outerHTML');
    const ts = new TomSelect('#pos-customer', {
        valueField: 'id', labelField: 'name', searchField: [], maxOptions: 20, create: false, preload: 'focus', loadThrottle: 250,
        load: (q, cb) => $.getJSON($pos.data('url-customers'), { q }).done((r) => cb(r.items)).fail(() => cb()),
        score: () => () => 1, // server already ranked/filtered
        render: {
            option: (c) => `<div class="py-1">${customerLabel(c)}</div>`,
            item: (c) => `<div>${$('<span>').text(c.name).prop('outerHTML')} <span class="text-xs text-slate-500" dir="ltr">${$('<span>').text(c.phone_display || c.phone || '').html()}</span></div>`,
            no_results: () => `<div class="no-results">${$('<span>').text(t.no_customers).html()}</div>`,
        },
        onChange: (id) => {
            const c = id ? ts.options[id] : null;
            if (!c) return;
            $form.find('[name=name]').val(c.name);
            $form.find('[name=phone]').val(c.phone || '');
            $form.find('[name=email]').val(c.email || '');
            if (c.address) $form.find('[name=address]').val(c.address);
            if (c.city_id) { const sel = $form.find('[name=city_id]')[0]; (sel.tomselect || { setValue: (v) => { sel.value = v; } }).setValue(String(c.city_id)); }
            changed(true);
        },
    });
    // modal inputs share names with the delivery fields: give them their own ids so labels focus the right control
    $('#pos-customer-modal [id^=f-]').each(function () { const old = this.id; this.id = 'c' + old; $(`#pos-customer-modal label[for="${old}"]`).attr('for', this.id); });
    $('#pos-customer-form').on('submit', function (e) {
        e.preventDefault();
        const $f = $(this);
        clearErrors($f);
        ajax($f.attr('action'), new FormData(this)).done((res) => {
            const c = res.data.customer;
            ts.addOption(c);
            ts.setValue(c.id);
            this.reset();
            $f.closest('[data-modal]').addClass('hidden').removeClass('flex');
            toastr.success(res.message);
        }).fail((xhr) => (xhr.status === 422 ? showErrors($f, xhr.responseJSON.errors) : toastr.error(xhr.responseJSON?.message || window.__t?.error)));
    });

    // ---------- shipping options + live quote ----------
    const fd = () => {
        const f = new FormData($form[0]);
        f.set('items', JSON.stringify([...cart.values()].map((r) => ({ product_id: r.product_id, variant_id: r.variant_id, qty: r.qty }))));
        return f;
    };
    const $ship = $('#pos-shipping');
    const renderShipHint = () => $ship.empty().append($('<p class="text-sm text-slate-500 sm:col-span-2">').text(t.pick_city));
    renderShipHint();
    const loadShipping = () => {
        const city = $form.find('[name=city_id]').val();
        if (!city || !cart.size) { $form.find('[name=shipping_company_id]').val(''); return renderShipHint(); }
        ajax($pos.data('url-shipping'), fd()).done((res) => {
            const opts = res.data.options;
            const cur = $form.find('[name=shipping_company_id]').val();
            if (!opts.some((o) => String(o.id) === cur)) $form.find('[name=shipping_company_id]').val(opts[0]?.id ?? '');
            const sel = $form.find('[name=shipping_company_id]').val();
            $ship.empty();
            opts.forEach((o) => {
                const on = String(o.id) === sel;
                $ship.append($('<button type="button" class="rounded-xl border p-3 text-start transition">').addClass(on ? 'border-brand-500 bg-brand-50 ring-2 ring-brand-500/20' : 'border-slate-200 bg-white hover:border-slate-300').append(
                    $('<div class="flex items-center justify-between gap-2"><span class="text-sm font-medium text-slate-800"></span><span class="text-sm font-semibold"></span></div>')
                        .find('span:first').text(o.name).end().find('span:last').text(Number(o.fee) === 0 ? t.free : money(o.fee)).end(),
                    $('<div class="text-xs text-slate-500">').text([o.eta && o.eta !== '—' ? t.eta_days.replace(':d', o.eta) : '', o.supports_cod ? '' : t.cod_unavailable].filter(Boolean).join(' · ')))
                    .on('click', () => { $form.find('[name=shipping_company_id]').val(o.id); loadShipping(); quote(); }));
            });
            if (!opts.length) renderShipHint();
        });
    };

    const $totals = $('#pos-totals');
    const $err = $('#pos-quote-error');
    const row = (label, value, cls = '') => $('<div class="flex justify-between">').addClass(cls).append($('<dt class="text-slate-500">').text(label), $('<dd>').text(value));
    let quoteSeq = 0;
    const quote = () => {
        if (!cart.size) { $totals.empty(); $err.addClass('hidden'); return; }
        const seq = ++quoteSeq;
        ajax($pos.data('url-quote'), fd()).done((res) => {
            if (seq !== quoteSeq) return;
            $err.addClass('hidden');
            const q = res.data, x = q.totals;
            $totals.empty().append(row(t.subtotal, money(x.subtotal)));
            if (Number(x.discount) > 0) $totals.append(row(t.discount + (q.discount_code ? ` (${q.discount_code})` : ''), '- ' + money(x.discount), 'text-emerald-700'));
            $totals.append(row(t.shipping_fee, $form.find('[name=shipping_company_id]').val() ? money(x.shippingNet) : '—'));
            if (Number(x.packagingFee) > 0) $totals.append(row(t.packaging_fee, money(x.packagingFee)));
            if (Number(x.tax) > 0) $totals.append(row(t.tax, money(x.tax)));
            if (Number(x.codFee) > 0) $totals.append(row(t.cod_fee, money(x.codFee)));
            if (Number(x.paymentFeeCharged) > 0) $totals.append(row(t.payment_fee, money(x.paymentFeeCharged)));
            $totals.append(row(t.total, money(x.total), 'border-t border-slate-200 pt-2 text-lg font-bold text-slate-900'));
            if (Number(x.prepaidShipping) > 0) $totals.append(row(t.prepaid_shipping, money(x.prepaidShipping)));
            if (q.needs_prepay || Number(x.codAmount) > 0 && Number(x.prepaidShipping) > 0) $totals.append(row(t.collect_on_delivery, money(x.codAmount), 'font-semibold'));
            $('#pos-prepay').toggleClass('hidden', !q.needs_prepay);
            $('#pos-proof').toggleClass('hidden', !q.needs_proof);
        }).fail((xhr) => {
            if (seq !== quoteSeq) return;
            const errs = xhr.responseJSON?.errors;
            $err.text(errs ? Object.values(errs)[0][0] : xhr.responseJSON?.message || window.__t?.error).removeClass('hidden');
        });
    };
    const quoteSoon = debounce(quote, 300);
    const shipSoon = debounce(loadShipping, 200);
    function changed(cartChanged) { if (cartChanged) shipSoon(); quoteSoon(); }
    $form.on('change', '[name=city_id]', () => { shipSoon(); quoteSoon(); });
    $form.on('change', '[name=payment_method_id], [name=prepay_method_id], [name=packaging_option_id]', quoteSoon);
    $form.on('input', '[name=discount_code]', quoteSoon);

    // ---------- place order ----------
    $form.on('submit', function (e) {
        e.preventDefault();
        if ($form.data('busy')) return;
        clearErrors($form);
        const busy = (b) => { $form.data('busy', b); $('#pos-submit').prop('disabled', b); };
        busy(true);
        ajax($form.attr('action'), fd()).done((res) => {
            toastr.success(res.message);
            setTimeout(() => (location.href = res.redirect), 600);
        }).fail((xhr) => {
            busy(false);
            if (xhr.status === 422 && xhr.responseJSON?.errors) {
                const errs = xhr.responseJSON.errors;
                showErrors($form, errs);
                toastr.error(errs.items?.[0] || errs.cart?.[0] || window.__t?.fix_errors || 'Please fix the highlighted fields.');
            } else toastr.error(xhr.responseJSON?.message || window.__t?.error || 'Error');
        });
    });
}
