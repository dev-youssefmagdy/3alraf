{{-- Marketing tags configured in Admin → SEO & Analytics. Ids are regex-validated on save (SeoSettingsRequest), so they are safe to echo.
     Nothing loads until the page is idle (first interaction or 3s after window load) so tags never hurt Core Web Vitals. --}}
@php
    $skipStaff = auth('tenant')->check() && ! $seo['track_staff'];
    $trk = array_filter(['ga4' => $seo['ga4_id'], 'gtm' => $seo['gtm_id'], 'ads' => $seo['google_ads_id'], 'adsLabel' => $seo['google_ads_label'], 'meta' => $seo['meta_pixel_id'],
        'tiktok' => $seo['tiktok_pixel_id'], 'snap' => $seo['snapchat_pixel_id'], 'clarity' => $seo['clarity_id']]);
    $currency = current_tenant()->currency_code;
@endphp
@if ($trk && ! $skipStaff)
<script>
window.__trk = {{ Illuminate\Support\Js::from($trk + ['currency' => $currency]) }};
(function (w, d) {
    var c = w.__trk, q = [], loaded = false;
    w.dataLayer = w.dataLayer || [];
    w.gtag = function () { w.dataLayer.push(arguments); };
    // normalised ecommerce event -> every configured platform (queued until the libraries are loaded)
    w.sfTrack = function (name, p) { p = p || {}; if (loaded) fan(name, p); else q.push([name, p]); };
    function items(p) { return (p.items || []).map(function (i) { return { item_id: String(i.id), item_name: i.name, price: i.price, quantity: i.qty || 1, item_brand: i.brand, item_category: i.category }; }); }
    function ids(p) { return (p.items || []).map(function (i) { return String(i.id); }); }
    function fan(name, p) {
        var cur = c.currency, val = Number(p.value || 0);
        if (c.gtm) {
            w.dataLayer.push({ event: name, ecommerce: { currency: cur, value: val, transaction_id: p.transaction_id, items: items(p) } });
        } else if (c.ga4 || c.ads) {
            w.gtag('event', name, { currency: cur, value: val, transaction_id: p.transaction_id, items: items(p) });
            if (name === 'purchase' && c.ads && c.adsLabel) w.gtag('event', 'conversion', { send_to: c.ads + '/' + c.adsLabel, value: val, currency: cur, transaction_id: p.transaction_id });
        }
        if (c.meta && w.fbq) {
            var m = { view_item: 'ViewContent', add_to_cart: 'AddToCart', begin_checkout: 'InitiateCheckout', purchase: 'Purchase', search: 'Search' }[name];
            if (m) w.fbq('track', m, { content_ids: ids(p), content_type: 'product', value: val, currency: cur, search_string: p.search_term }, p.transaction_id ? { eventID: p.transaction_id } : undefined);
        }
        if (c.tiktok && w.ttq) {
            var t = { view_item: 'ViewContent', add_to_cart: 'AddToCart', begin_checkout: 'InitiateCheckout', purchase: 'CompletePayment', search: 'Search' }[name];
            if (t) w.ttq.track(t, { contents: items(p).map(function (i) { return { content_id: i.item_id, quantity: i.quantity, price: i.price }; }), content_type: 'product', value: val, currency: cur });
        }
        if (c.snap && w.snaptr) {
            var s = { view_item: 'VIEW_CONTENT', add_to_cart: 'ADD_CART', begin_checkout: 'START_CHECKOUT', purchase: 'PURCHASE', search: 'SEARCH' }[name];
            if (s) w.snaptr('track', s, { item_ids: ids(p), price: val, currency: cur, transaction_id: p.transaction_id });
        }
    }
    function script(src, attrs) { var s = d.createElement('script'); s.async = true; s.src = src; for (var k in attrs || {}) s.setAttribute(k, attrs[k]); d.head.appendChild(s); return s; }
    function load() {
        if (loaded) return; loaded = true;
        if (c.gtm) {
            w.dataLayer.push({ 'gtm.start': Date.now(), event: 'gtm.js' }); script('https://www.googletagmanager.com/gtm.js?id=' + c.gtm);
        } else if (c.ga4 || c.ads) {
            script('https://www.googletagmanager.com/gtag/js?id=' + (c.ga4 || c.ads));
            w.gtag('js', new Date());
            if (c.ga4) w.gtag('config', c.ga4, { transport_type: 'beacon' });
            if (c.ads) w.gtag('config', c.ads);
        }
        if (c.meta) {
            (function (f, b, e, v, n, t, s) { if (f.fbq) return; n = f.fbq = function () { n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments); }; if (!f._fbq) f._fbq = n; n.push = n; n.loaded = !0; n.version = '2.0'; n.queue = []; t = b.createElement(e); t.async = !0; t.src = v; s = b.getElementsByTagName(e)[0]; s.parentNode.insertBefore(t, s); })(w, d, 'script', 'https://connect.facebook.net/en_US/fbevents.js');
            w.fbq('init', c.meta); w.fbq('track', 'PageView');
        }
        if (c.tiktok) {
            (function (t, e) { var n = 'ttq', ttq = t[n] = t[n] || []; ttq.methods = ['page', 'track', 'identify', 'instances', 'debug', 'on', 'off', 'once', 'ready', 'alias', 'group', 'enableCookie', 'disableCookie']; ttq.setAndDefer = function (t, e) { t[e] = function () { t.push([e].concat([].slice.call(arguments, 0))); }; }; for (var i = 0; i < ttq.methods.length; i++) ttq.setAndDefer(ttq, ttq.methods[i]); ttq.load = function (e) { ttq._i = ttq._i || {}; ttq._i[e] = []; script('https://analytics.tiktok.com/i18n/pixel/events.js?sdkid=' + e + '&lib=ttq'); }; ttq.load(c.tiktok); ttq.page(); })(w);
        }
        if (c.snap) {
            (function (e, t, n) { if (e.snaptr) return; var a = e.snaptr = function () { a.handleRequest ? a.handleRequest.apply(a, arguments) : a.queue.push(arguments); }; a.queue = []; script('https://sc-static.net/scevent.min.js'); })(w, d);
            w.snaptr('init', c.snap); w.snaptr('track', 'PAGE_VIEW');
        }
        if (c.clarity) {
            (function (c2, l, a, r, i, t, y) { c2[a] = c2[a] || function () { (c2[a].q = c2[a].q || []).push(arguments); }; t = l.createElement(r); t.async = 1; t.src = 'https://www.clarity.ms/tag/' + i; y = l.getElementsByTagName(r)[0]; y.parentNode.insertBefore(t, y); })(w, d, 'clarity', 'script', c.clarity);
        }
        q.splice(0).forEach(function (e) { fan(e[0], e[1]); });
    }
    ['pointerdown', 'keydown', 'scroll', 'touchstart'].forEach(function (ev) { w.addEventListener(ev, load, { once: true, passive: true }); });
    w.addEventListener('load', function () { setTimeout(function () { (w.requestIdleCallback || setTimeout)(load); }, 3000); });
})(window, document);
</script>
@else
<script>window.sfTrack = function () {};</script>
@endif
