// Product edit → AI tools (resources/views/admin/products/_product-ai.blade.php, PRODUCT_AI_FREELLMAPI_PLAN.md §7): Prices by image,
// Marketplace prices and Title ideas on Admin\ProductAiController. Everything that comes from the server, the web or the AI models is
// untrusted: it is set with textContent / attributes only, never as HTML. The dialog is an x-ui.modal clone, opened through
// window.panelModal so ui.js owns the focus trap, Esc and focus return.
const root = document.querySelector('[data-product-ai]');

if (root) {
    const cfg = JSON.parse(root.dataset.config);
    const t = cfg.i18n;
    const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
    const $ = (s, el = root) => el.querySelector(s);
    const $$ = (s, el = root) => [...el.querySelectorAll(s)];
    const isHttp = (u) => /^https?:\/\//i.test(u || '');
    const TABS = ['image', 'market', 'titles'];
    const SOURCES = ['structured_data', 'page_text', 'snippet', 'none'];
    const TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    const MARKET_CURRENCY = cfg.market?.currency || 'EGP';
    const uiLocale = document.documentElement.lang || 'en';
    const num = new Intl.NumberFormat(`${uiLocale}-u-nu-latn`, { maximumFractionDigits: 2 });
    const isRtl = () => getComputedStyle(root).direction === 'rtl';
    const tr = (k, params = {}) => Object.entries(params).reduce((s, [p, v]) => s.split(`:${p}`).join(String(v)), t[k] ?? k);
    const STEPS_PRICE = ['step_searching', 'step_reading', 'step_extracting'];
    const STEP_TIMES = [6000, 20000]; // 0 s searching → 6 s reading pages → 20 s extracting (the last step stays current until the answer)

    // ---------------------------------------------------------------- DOM helpers (text only)
    function h(tag, props = {}, ...children) {
        const e = document.createElement(tag);
        for (const [k, v] of Object.entries(props)) {
            if (v === null || v === undefined || v === false) continue;
            if (k === 'class') e.className = v;
            else if (k === 'text') e.textContent = v;
            else if (k.startsWith('on')) e.addEventListener(k.slice(2), v);
            else e.setAttribute(k, v === true ? '' : String(v));
        }
        children.flat().forEach((c) => { if (c !== null && c !== undefined && c !== false) e.append(c); });
        return e;
    }
    const tpl = (name) => $(`template[data-pai-tpl="${name}"]`).content.firstElementChild.cloneNode(true);
    const hint = (text) => h('p', { class: 'py-10 text-center text-sm text-panel-text-subtle', text });
    const linkButton = (text, onclick) => h('button', { type: 'button', class: 'text-sm font-bold text-panel-accent-text underline', text, onclick });
    const priceOf = (r) => (typeof r.price === 'number' && Number.isFinite(r.price) ? r.price : null);
    const money = (p, cur) => `${num.format(p)}${cur ? ` ${cur}` : ''}`;
    const when = (iso) => { try { return new Date(iso).toLocaleString(`${uiLocale}-u-nu-latn`, { dateStyle: 'medium', timeStyle: 'short' }); } catch { return iso; } };

    // ---------------------------------------------------------------- network ({success, data} | {message, code} | Laravel validation)
    async function send(url, body) {
        const form = body instanceof FormData;
        const headers = { Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' };
        if (!form) headers['Content-Type'] = 'application/json';
        try {
            const res = await fetch(url, { method: 'POST', credentials: 'same-origin', headers, body: form ? body : JSON.stringify(body) });
            const json = await res.json().catch(() => ({}));
            return { ok: res.ok && json.success !== false, status: res.status, json, retryAfter: parseInt(res.headers.get('Retry-After'), 10) || null };
        } catch {
            return { ok: false, status: 0, json: {}, retryAfter: null };
        }
    }
    const CODES = { not_configured: 'not_configured', gateway_down: 'error_gateway_down', quota_exceeded: 'error_quota', no_vision_model: 'error_no_vision', timeout: 'error_timeout' };
    function errorOf(r) {
        const j = r.json || {};
        if (r.status === 429 && (j.code === 'throttled' || !j.code)) {
            const seconds = r.retryAfter || parseInt(String(j.message || '').match(/\d+/)?.[0], 10) || 60;
            return { code: 'throttled', seconds, message: tr('error_throttled', { seconds }) };
        }
        if (CODES[j.code]) return { code: j.code, message: t[CODES[j.code]] };
        if (r.status === 504) return { code: 'timeout', message: t.error_timeout };
        if (r.status === 422 && j.errors) return { code: 'validation', message: Object.values(j.errors)[0]?.[0] || j.message || t.error };
        if (r.status === 0) return { code: 'network', message: t.error };
        return { code: j.code || 'error', message: j.message || t.error };
    }

    // ---------------------------------------------------------------- throttle cool-down (the limiter is per admin, so it blocks every run button)
    let coolUntil = 0;
    let coolTimer = null;
    const coolLeft = () => Math.max(0, Math.ceil((coolUntil - Date.now()) / 1000));
    function startCooldown(seconds) {
        coolUntil = Date.now() + seconds * 1000;
        clearInterval(coolTimer);
        coolTimer = setInterval(() => { if (!coolLeft()) clearInterval(coolTimer); syncButtons(); }, 1000);
        syncButtons();
    }

    // ---------------------------------------------------------------- state: per target, per tab (kept in memory while the page is open)
    const S = { tab: 'image', ti: 0, mem: new Map(), seq: 0, pending: {} };
    const target = () => cfg.targets[S.ti];
    const keyOf = (x) => `${x.type}:${x.id}`;
    const req = () => ({ state: 'idle', data: null, error: null, step: 0, steps: [], token: 0, timers: [] });
    const priceReq = () => ({ ...req(), includeOther: true, sort: 'price_asc' });
    function mem(x = target()) {
        const k = keyOf(x);
        if (!S.mem.has(k)) {
            S.mem.set(k, {
                image: { source: x.thumb ? 'primary' : 'upload', file: null, preview: null, fileError: '', query: '', identify: req(), prices: priceReq() },
                market: { query: x.query || '', prices: priceReq() },
                titles: { use: false, req: req(), choice: {}, edit: {}, saving: {} },
            });
        }
        return S.mem.get(k);
    }
    const targetBody = (x) => (x.type === 'variant' ? { target_type: 'variant', target_id: x.id } : { target_type: 'product' });

    /**
     * One running request per tab. The stepper is time-driven (requests are synchronous) and never shows "done" before the answer.
     * A result that comes back after the target changed is dropped and that target goes back to what it showed before.
     */
    async function request(tab, rs, url, body, steps, start = 0, times = []) {
        const x = target();
        const k = keyOf(x);
        const token = ++S.seq;
        const before = { state: rs.state, data: rs.data, error: rs.error };
        rs.timers.forEach(clearTimeout);
        Object.assign(rs, { state: 'loading', error: null, steps, step: start, token });
        S.pending[tab] = token;
        rs.timers = times.map((ms, i) => setTimeout(() => {
            if (rs.token !== token || rs.state !== 'loading') return;
            rs.step = start + i + 1;
            if (S.tab === tab && keyOf(target()) === k) updateLoading(tab, rs);
        }, ms));
        render(tab);
        const r = await send(url, body);
        rs.timers.forEach(clearTimeout);
        if (rs.token !== token) return null;
        if (S.pending[tab] !== token || keyOf(target()) !== k) {
            Object.assign(rs, before);
            return null;
        }
        if (r.ok) {
            Object.assign(rs, { state: 'done', data: r.json.data || {}, error: null });
        } else {
            Object.assign(rs, { state: 'error', error: errorOf(r) });
            if (rs.error.code === 'throttled') startCooldown(rs.error.seconds);
        }
        return r.ok ? rs.data : null;
    }

    // ---------------------------------------------------------------- shared status pieces
    function stepper(labels, current) {
        const ol = h('ol', { class: 'flex items-center gap-2', 'aria-label': cfg.steps_label, 'aria-live': 'off' });
        labels.forEach((key, i) => {
            const n = i + 1;
            const done = i < current;
            const on = i === current;
            const dot = h('span', { class: `grid size-8 shrink-0 place-items-center rounded-full text-sm font-bold ${done ? 'bg-panel-success-solid text-white' : (on ? 'bg-panel-accent text-panel-on-accent' : 'border-2 border-panel-border-strong text-panel-text-subtle')}` });
            dot.append(done ? tpl('check') : String(n));
            const li = h('li', { class: 'flex min-w-0 flex-1 items-center gap-2', 'aria-current': on ? 'step' : null }, dot,
                h('span', { class: `hidden truncate text-sm font-semibold sm:block ${on ? 'text-panel-text' : 'text-panel-text-subtle'}`, text: t[key] }));
            if (i < labels.length - 1) li.append(h('span', { class: `h-px flex-1 ${done ? 'bg-panel-success-solid' : 'bg-panel-border'}` }));
            ol.append(li);
        });
        return ol;
    }
    function loadingView(rs) {
        return h('div', { class: 'space-y-3', 'data-loading': '' },
            stepper(rs.steps, rs.step),
            h('p', { class: 'flex items-center gap-2 text-sm text-panel-text-muted' }, tpl('spinner'),
                h('span', { 'data-step-name': '', class: 'font-semibold text-panel-text sm:hidden', text: t[rs.steps[rs.step]] }),
                h('span', { text: t.steps_note })),
            h('span', { class: 'sr-only', 'data-step-sr': '', text: t[rs.steps[rs.step]] }));
    }
    /** Step change only: swap the stepper in place and update one short text, so the live region does not read the whole block again. */
    function updateLoading(tab, rs) {
        const box = $('[data-pai-status] [data-loading]', panel(tab));
        if (!box) { render(tab); return; }
        box.querySelector('ol').replaceWith(stepper(rs.steps, rs.step));
        box.querySelector('[data-step-name]').textContent = t[rs.steps[rs.step]];
        box.querySelector('[data-step-sr]').textContent = t[rs.steps[rs.step]];
    }
    function errorView(err, retry, extra = null) {
        const a = tpl('danger');
        $('[data-msg]', a).textContent = err.message;
        const acts = $('[data-actions]', a);
        acts.append(h('button', { type: 'button', class: 'btn-sm btn-secondary', 'data-pai-retry': '', text: t.try_again, onclick: retry }));
        if (extra) acts.append(extra);
        return a;
    }
    function warningView(text) {
        const a = tpl('warning');
        $('[data-msg]', a).textContent = text;
        return a;
    }
    const skeletons = () => h('div', { class: 'rounded-xl border border-panel-border' }, [0, 1, 2, 3].map(() => tpl('skeleton')));
    const panel = (tab) => $(`[data-pai-panel="${tab}"]`);
    const statusBox = (tab) => $('[data-pai-status]', panel(tab));
    const resultsBox = (tab) => $('[data-pai-results]', panel(tab));
    const useMarket = () => linkButton(t.use_market, () => showTab('market', true));

    // ---------------------------------------------------------------- price table (A1 + A2)
    const visibleRows = (rs) => (rs.data?.rows || []).filter((r) => rs.includeOther || r.allowed !== false);
    /** "Price not shown" always last. By price, rows in another currency come after the market currency ones (numbers are not comparable). */
    function sortRows(rows, sort, cur) {
        const name = (r) => String(r.marketplace || r.domain || '');
        const byName = (a, b) => name(a).localeCompare(name(b), uiLocale);
        const foreign = (r) => ((r.currency || cur) === cur ? 0 : 1);
        const byPrice = (a, b) => foreign(a) - foreign(b) || (sort === 'price_desc' ? priceOf(b) - priceOf(a) : priceOf(a) - priceOf(b));
        const priced = rows.filter((r) => priceOf(r) !== null).sort(sort === 'marketplace' ? byName : byPrice);
        const none = rows.filter((r) => priceOf(r) === null).sort(sort === 'marketplace' ? byName : () => 0);
        return [...priced, ...none];
    }
    /** Price status of a finished run: partial (failed engines / unread pages), empty (no listings / no prices) or plain success. */
    function priceStatus(rs) {
        const d = rs.data;
        const rows = d.rows || [];
        const failed = Object.keys(d.errors || {}).map((k) => (k === 'ai' ? 'AI' : k));
        if (d.pages_failed > 0) failed.push(tr('pages_failed', { count: d.pages_failed }));
        const out = [];
        if (failed.length) out.push(warningView(tr('partial', { sources: failed.join(isRtl() ? '، ' : ', ') })));
        if (!rows.length) out.push(hint(t.no_results));
        else if (!rows.some((r) => priceOf(r) !== null)) out.push(h('p', { class: 'text-sm text-panel-text-muted', text: t.no_prices }));
        else out.push(h('p', { class: 'text-sm text-panel-text-muted', text: tr('results_found', { count: rows.length }) }));
        return out;
    }
    function priceView(rs, refresh) {
        const d = rs.data;
        const wrap = h('div', { class: 'space-y-3' });
        if (!(d.rows || []).length) return wrap;
        if (d.cached_at) {
            wrap.append(h('p', { class: 'text-xs text-panel-text-subtle' }, `${tr('cached', { time: when(d.cached_at) })} `,
                h('button', { type: 'button', class: 'font-bold text-panel-accent-text underline', 'data-pai-retry': '', text: t.search_again, onclick: refresh })));
        }
        const body = h('div', { class: 'space-y-3' });
        const draw = (focusSel = null) => {
            body.replaceChildren(summaryView(rs), tableView(rs, draw), cardsView(rs));
            if (focusSel) $(focusSel, body)?.focus();
        };
        const controls = h('div', { class: 'flex flex-wrap items-center justify-between gap-3' });
        if ((d.rows || []).some((r) => r.allowed === false)) {
            const cb = h('input', { type: 'checkbox', class: 'size-[22px] shrink-0 rounded-md border-panel-border-strong' });
            cb.checked = rs.includeOther;
            cb.addEventListener('change', () => { rs.includeOther = cb.checked; draw(); });
            controls.append(h('label', { class: 'flex min-h-11 w-fit cursor-pointer items-center gap-3 text-sm text-panel-text' }, cb, h('span', { text: t.include_other })));
        } else {
            controls.append(h('span'));
        }
        const sel = h('select', { class: 'form-control !h-9 !w-auto !py-1 text-sm', 'data-pai-sort': '' },
            ['price_asc', 'price_desc', 'marketplace'].map((v) => h('option', { value: v, text: t[`sort_${v}`] })));
        sel.value = rs.sort;
        sel.addEventListener('change', () => { rs.sort = sel.value; draw(); });
        controls.append(h('label', { class: 'flex items-center gap-2 text-xs font-bold text-panel-text-muted' }, h('span', { text: t.sort_by }), sel));
        wrap.append(controls, body);
        draw();
        rs.syncSort = () => { sel.value = rs.sort; };
        return wrap;
    }
    function summaryView(rs) {
        const rows = visibleRows(rs);
        const priced = rows.filter((r) => priceOf(r) !== null);
        if (!priced.length) return '';
        const cur = rs.data.currency || MARKET_CURRENCY;
        const counted = priced.filter((r) => (r.currency || cur) === cur).map(priceOf).sort((a, b) => a - b);
        const tile = (label, value, ltr = true) => h('div', { class: 'rounded-xl border border-panel-border bg-panel-surface-sunken px-3 py-2' },
            h('dt', { class: 'text-xs text-panel-text-muted', text: label }),
            h('dd', { class: 'font-bold text-panel-text tabular-nums', dir: ltr ? 'ltr' : null, text: value }));
        const dl = h('dl', { class: 'grid grid-cols-2 gap-2 sm:grid-cols-4' });
        if (counted.length) {
            const mid = Math.floor(counted.length / 2);
            const median = counted.length % 2 ? counted[mid] : (counted[mid - 1] + counted[mid]) / 2;
            dl.append(tile(t.summary_lowest, money(counted[0], cur)), tile(t.summary_median, money(median, cur)), tile(t.summary_highest, money(counted[counted.length - 1], cur)));
        }
        const count = h('div', { class: 'flex items-center rounded-xl border border-panel-border px-3 py-2 text-sm text-panel-text-muted', text: tr('summary_count', { priced: priced.length, total: rows.length }) });
        dl.append(count);
        const box = h('div', { class: 'space-y-1' }, dl);
        if (counted.length < priced.length) box.append(h('p', { class: 'text-xs text-panel-text-subtle', text: tr('summary_currency_note', { currency: cur }) }));
        return box;
    }
    /** The price inside the quote: same digits with optional thousands separators (Latin or Arabic-Indic). */
    function quoteNode(text, price) {
        const span = h('span', { dir: 'auto' });
        let m = null;
        if (price !== null) {
            const ar = '٠١٢٣٤٥٦٧٨٩';
            const dig = (c) => `[${c}${ar[c]}]`;
            const [int, dec] = String(price).split('.');
            const src = int.split('').map(dig).join('[,.\\s\\u066C\\u00A0\\u202F]?') + (dec ? `[.,\\u066B]${dec.split('').map(dig).join('')}\\d*` : '(?:[.,\\u066B]\\d{1,2})?');
            try { m = new RegExp(`(?<![\\d٠-٩])${src}(?![\\d٠-٩])`, 'u').exec(text); } catch { m = null; }
        }
        if (!m) { span.textContent = text; return span; }
        span.append(text.slice(0, m.index), h('mark', { class: 'rounded bg-panel-warning-bg px-0.5 font-bold text-panel-text', text: m[0] }), text.slice(m.index + m[0].length));
        return span;
    }
    let quoteSeq = 0;
    /** Price source badge; page_text / snippet with a quote are toggle buttons for the detail element. */
    function sourceBadge(r, detail) {
        const src = priceOf(r) === null ? 'none' : (SOURCES.includes(r.price_source) ? r.price_source : 'none');
        const props = { class: `badge-src badge-src-${src}`, title: t[`source_${src}_hint`], text: t[`source_${src}`] };
        if (!detail || !r.quote || !['page_text', 'snippet'].includes(src)) return h('span', props);
        const id = `pai-quote-${++quoteSeq}`;
        detail.id = id;
        const b = h('button', { type: 'button', ...props, 'aria-expanded': 'false', 'aria-controls': id });
        b.addEventListener('click', () => {
            const open = b.getAttribute('aria-expanded') !== 'true';
            b.setAttribute('aria-expanded', String(open));
            detail.hidden = !open;
        });
        return b;
    }
    const quoteDetail = (r, tag, cls) => {
        const el = h(tag, { class: cls, hidden: true });
        const inner = h('span', {}, h('strong', { text: `${t.quote}: ` }), quoteNode(String(r.quote || ''), priceOf(r)));
        if (tag === 'tr') el.append(h('td', { colspan: '6', class: 'bg-panel-surface-sunken px-3 py-2 text-xs text-panel-text' }, inner));
        else el.append(inner);
        return el;
    };
    const storeOf = (r) => String(r.marketplace || r.domain || '');
    function storeCell(r) {
        return h('div', { class: 'min-w-0' },
            h('span', { dir: 'ltr', class: `break-all ${r.allowed !== false ? 'font-semibold text-panel-text' : 'text-panel-text'}`, text: storeOf(r) }),
            r.allowed === false ? h('span', { class: 'mt-1 block' }, tpl('other')) : null);
    }
    function priceCell(r, big = false) {
        const p = priceOf(r);
        if (p === null) return h('span', { class: 'italic text-panel-text-subtle', text: t.price_not_shown });
        return h('span', { dir: 'ltr', class: `whitespace-nowrap font-bold tabular-nums text-panel-text ${big ? 'text-lg' : ''}`, text: money(p, r.currency || '') });
    }
    function stockCell(r) {
        if (r.in_stock === true) return tpl('in_stock');
        if (r.in_stock === false) return tpl('out_of_stock');
        return h('span', { class: 'text-xs text-panel-text-muted', text: t.stock_unknown });
    }
    function linkCell(r) {
        if (!isHttp(r.url)) return '';
        return h('a', { href: r.url, target: '_blank', rel: 'noopener noreferrer nofollow', referrerpolicy: 'no-referrer', class: 'inline-flex min-h-11 items-center gap-1 text-sm font-bold text-panel-accent-text underline md:min-h-0' },
            h('span', { 'aria-hidden': 'true', text: t.open_listing }), tpl('external'), h('span', { class: 'sr-only', text: tr('open_listing_sr', { store: storeOf(r) }) }));
    }
    const titleCell = (r) => h('p', { class: 'line-clamp-2 text-panel-text', dir: 'auto', title: r.title || null, text: r.title || r.snippet || r.domain || '' });
    function tableView(rs, redraw) {
        const rows = sortRows(visibleRows(rs), rs.sort, rs.data.currency || MARKET_CURRENCY);
        const priceSorted = rs.sort === 'price_asc' || rs.sort === 'price_desc';
        const th = (text, extra = {}) => h('th', { scope: 'col', class: 'px-3 py-2 text-start text-xs font-bold text-panel-text-subtle', ...extra }, text);
        const sortBtn = h('button', { type: 'button', 'data-pai-price-sort': '', class: 'inline-flex items-center gap-1 font-bold hover:text-panel-text', text: t.col_price });
        sortBtn.append(h('span', { 'aria-hidden': 'true', text: rs.sort === 'price_asc' ? ' ↑' : (rs.sort === 'price_desc' ? ' ↓' : '') }));
        sortBtn.addEventListener('click', () => { rs.sort = rs.sort === 'price_asc' ? 'price_desc' : 'price_asc'; rs.syncSort?.(); redraw('[data-pai-price-sort]'); });
        const tbody = h('tbody');
        rows.forEach((r) => {
            const detail = quoteDetail(r, 'tr', '');
            tbody.append(h('tr', { class: 'border-t border-panel-border align-top' },
                h('td', { class: 'px-3 py-2' }, storeCell(r)),
                h('td', { class: 'max-w-80 px-3 py-2' }, titleCell(r)),
                h('td', { class: 'px-3 py-2' }, priceCell(r)),
                h('td', { class: 'px-3 py-2' }, stockCell(r)),
                h('td', { class: 'px-3 py-2' }, sourceBadge(r, detail)),
                h('td', { class: 'px-3 py-2' }, linkCell(r))), detail);
        });
        return h('div', { class: 'hidden overflow-x-auto rounded-xl border border-panel-border md:block' },
            h('table', { class: 'w-full text-sm' },
                h('thead', {}, h('tr', {}, th(t.col_marketplace), th(t.col_title),
                    th(sortBtn, { 'aria-sort': priceSorted ? (rs.sort === 'price_asc' ? 'ascending' : 'descending') : 'none' }),
                    th(t.col_stock), th(t.col_source), th(t.col_link))),
                tbody));
    }
    function cardsView(rs) {
        const rows = sortRows(visibleRows(rs), rs.sort, rs.data.currency || MARKET_CURRENCY);
        return h('ul', { class: 'space-y-2 md:hidden' }, rows.map((r) => {
            const detail = quoteDetail(r, 'div', 'rounded-lg bg-panel-surface-sunken px-3 py-2 text-xs text-panel-text');
            return h('li', { class: 'space-y-1.5 rounded-xl border border-panel-border p-3 text-sm' },
                storeCell(r), titleCell(r),
                h('div', { class: 'flex flex-wrap items-center justify-between gap-2' }, priceCell(r, true), stockCell(r)),
                h('div', { class: 'flex flex-wrap items-center justify-between gap-2' }, sourceBadge(r, detail), linkCell(r)),
                detail);
        }));
    }

    // ---------------------------------------------------------------- A1: Prices by image
    const fileInput = $('[data-pai-file]');
    function renderImage() {
        const x = target();
        const m = mem().image;
        const p = panel('image');
        if (!x.thumb) m.source = 'upload';
        $$('[data-pai-source]', p).forEach((r) => {
            r.checked = r.value === m.source;
            if (r.value === 'primary') r.disabled = !x.thumb;
        });
        $('[data-pai-primary-label]', p).classList.toggle('cursor-not-allowed', !x.thumb);
        $('[data-pai-primary-label]', p).classList.toggle('opacity-50', !x.thumb);
        $('[data-pai-no-primary]', p).hidden = !!x.thumb;
        $('[data-pai-upload]', p).hidden = m.source !== 'upload';
        const err = $('[data-pai-file-error]', p);
        err.textContent = m.fileError;
        err.hidden = !m.fileError;
        if (m.fileError) fileInput.setAttribute('aria-invalid', 'true'); else fileInput.removeAttribute('aria-invalid');
        if (!m.file) fileInput.value = '';
        const preview = $('[data-pai-preview]', p);
        const src = m.source === 'primary' ? x.thumb : m.preview;
        preview.hidden = !src;
        if (src) preview.src = src; else preview.removeAttribute('src');

        // identified product + confirm the search text
        const id = m.identify;
        const found = id.state === 'done' && id.data?.found;
        $('[data-pai-identified]', p).hidden = !found;
        if (found) {
            ['brand', 'name', 'model', 'size'].forEach((f) => {
                const dd = $(`[data-pai-id="${f}"]`, p);
                const v = id.data[f];
                dd.textContent = v || t.not_found;
                dd.dir = v ? 'auto' : '';
                dd.classList.toggle('font-normal', !v);
                dd.classList.toggle('text-panel-text-subtle', !v);
            });
            const cached = $('[data-pai-id-cached]', p);
            cached.hidden = !id.data.cached_at;
            cached.textContent = id.data.cached_at ? tr('cached', { time: when(id.data.cached_at) }) : '';
            $('[data-pai-image-query]', p).value = m.query;
            const others = (id.data.search_queries || []).slice(1).filter((q) => typeof q === 'string' && q.trim());
            $('[data-pai-chips-wrap]', p).hidden = !others.length;
            $('[data-pai-chips]', p).replaceChildren(...others.map((q) => h('button', {
                type: 'button', dir: 'auto', 'aria-pressed': 'false', text: q,
                class: 'inline-flex min-h-9 items-center gap-1.5 whitespace-nowrap rounded-full border border-panel-border-strong bg-panel-surface px-3.5 text-[13px] font-semibold text-panel-text transition hover:border-panel-accent',
                onclick: () => { m.query = q; $('[data-pai-image-query]', p).value = q; syncButtons(); $('[data-pai-image-query]', p).focus(); },
            })));
        }

        // status: the price run when there is one, else the identify run
        const pr = m.prices;
        const status = [];
        if (pr.state === 'loading') status.push(loadingView(pr));
        else if (pr.state === 'error') status.push(errorView(pr.error, () => runImagePrices()));
        else if (pr.state === 'done') status.push(...priceStatus(pr));
        else if (id.state === 'loading') status.push(loadingView(id));
        else if (id.state === 'error') status.push(errorView(id.error, () => runIdentify(), id.error.code === 'no_vision_model' ? useMarket() : null));
        else if (id.state === 'done' && !found) status.push(h('div', { class: 'py-10 text-center text-sm text-panel-text-subtle' }, h('p', { text: t.identify_empty }), h('p', { class: 'mt-2' }, useMarket())));
        else if (id.state === 'done') status.push(h('span', { class: 'sr-only', text: t.identified }));
        else status.push(hint(t.image_idle));
        statusBox('image').replaceChildren(...status);
        resultsBox('image').replaceChildren(pr.state === 'loading' ? skeletons() : (pr.state === 'done' ? priceView(pr, () => runImagePrices(true)) : ''));
    }
    async function runIdentify() {
        const x = target();
        const m = mem().image;
        if (coolLeft() || (m.source === 'upload' && !m.file)) return;
        const fd = new FormData();
        if (m.source === 'upload') { fd.append('source', 'upload'); fd.append('image', m.file); }
        else if (x.type === 'variant') { fd.append('source', 'variant'); fd.append('variant_id', String(x.id)); }
        else fd.append('source', 'product');
        m.prices = priceReq();
        const steps = ['step_identifying', ...STEPS_PRICE];
        const data = await request('image', m.identify, cfg.identify, fd, steps, 0);
        if (data) m.query = (data.search_queries || []).find((q) => typeof q === 'string' && q.trim()) || '';
        render('image');
        if (data?.found) setTimeout(() => $('[data-pai-image-query]').focus(), 0);
    }
    async function runImagePrices(fresh = false) {
        const m = mem().image;
        const query = m.query.trim();
        if (!query || coolLeft()) return;
        await request('image', m.prices, cfg.prices, { query, ...targetBody(target()), fresh }, ['step_identifying', ...STEPS_PRICE], 1, STEP_TIMES);
        render('image');
    }

    // ---------------------------------------------------------------- A2: Marketplace prices
    function renderMarket() {
        const x = target();
        const m = mem().market;
        const p = panel('market');
        const th = $('[data-pai-thumb]', p);
        th.hidden = !x.thumb;
        if (x.thumb) th.src = x.thumb;
        $('[data-pai-query]', p).value = m.query;
        const pr = m.prices;
        let status = [hint(t.market_idle)];
        if (pr.state === 'loading') status = [loadingView(pr)];
        else if (pr.state === 'error') status = [errorView(pr.error, () => runMarket())];
        else if (pr.state === 'done') status = priceStatus(pr);
        statusBox('market').replaceChildren(...status);
        resultsBox('market').replaceChildren(pr.state === 'loading' ? skeletons() : (pr.state === 'done' ? priceView(pr, () => runMarket(true)) : ''));
    }
    async function runMarket(fresh = false) {
        const m = mem().market;
        const query = m.query.trim();
        if (!query || coolLeft()) return;
        await request('market', m.prices, cfg.prices, { query, ...targetBody(target()), fresh }, STEPS_PRICE, 0, STEP_TIMES);
        render('market');
    }

    // ---------------------------------------------------------------- A3: Title ideas
    /** Listing titles of the visible rows of this target's last price run (Marketplace prices first, else Prices by image). */
    function competitorTitles() {
        const m = mem();
        const rs = [m.market.prices, m.image.prices].find((r) => r.state === 'done' && (r.data?.rows || []).length);
        if (!rs) return [];
        return [...new Set(visibleRows(rs).map((r) => String(r.title || '').trim()).filter(Boolean))].slice(0, 10).map((s) => s.slice(0, 300));
    }
    const lengthOf = (v) => [...String(v || '').trim()].length;
    const validity = (v) => { const n = lengthOf(v); return n < 10 ? 'too_short' : (n > 120 ? 'too_long' : ''); };
    function titleCard(loc, data, m, k) {
        const titles = (data.titles?.[loc] || []).filter((s) => typeof s === 'string' && s.trim());
        const cur = data.target?.current?.[loc];
        const label = data.target?.label || target().label;
        const card = h('section', { class: 'space-y-3 rounded-xl border border-panel-border p-4', dir: loc === 'ar' ? 'rtl' : 'ltr', 'aria-labelledby': `pai-tt-${loc}` },
            h('h3', { id: `pai-tt-${loc}`, class: 'font-semibold text-panel-text', text: t[`lang_${loc}`] }),
            h('p', { class: 'text-sm text-panel-text-muted' }, `${t.current}: `,
                cur ? h('span', { lang: loc, class: 'font-semibold text-panel-text', text: cur }) : h('span', { text: tr('current_none', { label }) })));
        if (!titles.length) { card.append(h('p', { class: 'text-sm text-panel-text-subtle', text: t.titles_empty })); return card; }
        const name = `pai-title-${k.replace(':', '-')}-${loc}`;
        const fs = h('fieldset', {}, h('legend', { class: 'form-label', text: t.choose }));
        titles.forEach((s, i) => {
            const r = h('input', { type: 'radio', name, value: String(i), class: 'size-[22px] shrink-0 border-panel-border-strong' });
            r.checked = m.choice[loc] === i;
            r.addEventListener('change', () => { m.choice[loc] = i; m.edit[loc] = s; editWrap.hidden = false; input.value = s; check(true); });
            fs.append(h('label', { class: 'flex min-h-11 cursor-pointer items-center gap-3 text-sm text-panel-text' }, r, h('span', { lang: loc, text: s })));
        });
        const fid = `pai-edit-${loc}`;
        const input = h('input', { id: fid, type: 'text', maxlength: '120', lang: loc, class: 'form-control', autocomplete: 'off', 'aria-describedby': `${fid}-count` });
        input.value = m.edit[loc] ?? '';
        const counter = h('p', { id: `${fid}-count`, class: 'text-xs tabular-nums' });
        const live = h('span', { class: 'sr-only', 'aria-live': 'polite' });
        const saveBtn = h('button', { type: 'button', class: 'btn-sm btn-primary', 'data-save': loc, text: t.save });
        let last = validity(input.value);
        function check(announce = false) {
            const v = validity(input.value);
            counter.textContent = `${tr('counter', { count: lengthOf(input.value) })}${v ? ` · ${t[v]}` : ''}`;
            counter.classList.toggle('text-panel-danger-fg', !!v);
            counter.classList.toggle('text-panel-text-subtle', !v);
            if (v) input.setAttribute('aria-invalid', 'true'); else input.removeAttribute('aria-invalid');
            if (announce && v !== last) live.textContent = v ? t[v] : counter.textContent;
            last = v;
            const saving = !!m.saving[loc];
            saveBtn.disabled = !!v || saving;
            saveBtn.textContent = saving ? t.saving : t.save;
            if (saving) { saveBtn.setAttribute('aria-busy', 'true'); saveBtn.setAttribute('aria-label', t.save); } else { saveBtn.removeAttribute('aria-busy'); saveBtn.removeAttribute('aria-label'); }
        }
        input.addEventListener('input', () => { m.edit[loc] = input.value; check(true); });
        saveBtn.addEventListener('click', () => saveTitle(loc, input.value, check));
        const editWrap = h('div', { class: 'space-y-1.5', hidden: m.choice[loc] === undefined },
            h('label', { for: fid, class: 'form-label', text: t.edit_chosen }), input,
            h('div', { class: 'flex items-center justify-between gap-3' }, counter, live, saveBtn));
        check();
        card.append(fs, editWrap);
        return card;
    }
    function renderTitles() {
        const m = mem().titles;
        const p = panel('titles');
        const comp = competitorTitles();
        const cb = $('[data-pai-competitors]', p);
        if (!comp.length) m.use = false;
        cb.disabled = !comp.length;
        cb.checked = m.use;
        $('[data-pai-competitors-label]', p).classList.toggle('cursor-not-allowed', !comp.length);
        $('[data-pai-competitors-none]', p).hidden = !!comp.length;
        const rs = m.req;
        let status = [hint(t.titles_idle)];
        let cards = [];
        if (rs.state === 'loading') status = [loadingView(rs)];
        else if (rs.state === 'error') status = [errorView(rs.error, () => runTitles())];
        else if (rs.state === 'done') {
            const any = cfg.locales.some((l) => (rs.data.titles?.[l] || []).length);
            status = any ? [] : [hint(t.titles_empty)];
            if (any) cards = cfg.locales.map((l) => titleCard(l, rs.data, m, keyOf(target())));
        }
        statusBox('titles').replaceChildren(...status);
        resultsBox('titles').replaceChildren(...cards);
    }
    async function runTitles() {
        const m = mem().titles;
        if (coolLeft()) return;
        const body = { ...targetBody(target()), use_competitor_titles: !!m.use };
        if (m.use) body.competitor_titles = competitorTitles();
        m.choice = {}; m.edit = {};
        await request('titles', m.req, cfg.titles, body, ['step_writing'], 0);
        render('titles');
    }
    async function saveTitle(loc, value, check) {
        const x = target();
        const m = mem(x).titles;
        const title = String(value || '').trim();
        if (validity(title) || m.saving[loc]) return;
        m.saving[loc] = true;
        check();
        const r = await send(cfg.apply, { ...targetBody(x), locale: loc, title });
        m.saving[loc] = false;
        if (!r.ok) {
            const e = r.status === 422 && r.json.errors ? errorOf(r).message : (r.json.message || t.save_failed);
            window.toastr?.error(e);
            check();
            return;
        }
        const d = r.json.data || {};
        window.toastr?.success(r.json.message || tr('saved', { lang: loc.toUpperCase(), target: x.label }));
        if (m.req.data && d.target) m.req.data.target = d.target;
        applyToPage(x, d, loc, title);
        if (keyOf(target()) === keyOf(x) && S.tab === 'titles') {
            render('titles');
            $(`[data-save="${loc}"]`, panel('titles'))?.focus();
        }
    }
    /** Keep the page in step with the saved name, so a later save of the product form does not overwrite it. */
    function applyToPage(x, d, loc, title) {
        const label = d.target?.label || x.label;
        if (x.type === 'product') {
            document.querySelectorAll(`form[data-ajax-form] [name="name[${CSS.escape(loc)}]"]`).forEach((i) => { i.value = d.title || title; i.defaultValue = d.title || title; });
            const h1 = document.querySelector('[data-page-header] h1');
            if (h1) h1.textContent = `${cfg.edit_prefix}: ${label}`;
        } else {
            document.querySelectorAll('[data-repeater-item]').forEach((row) => {
                const idInput = row.querySelector('input[name^="variants["][name$="[id]"]');
                if (idInput && String(idInput.value) === String(x.id)) { const l = row.querySelector('[data-label]'); if (l) l.textContent = label; }
            });
        }
        x.label = label;
        fillTargets();
    }

    // ---------------------------------------------------------------- run buttons (label, disabled, aria-busy, throttle countdown)
    function syncButtons() {
        const m = mem();
        const left = coolLeft();
        const set = (b, base, busyLabel, again, busy, disabled) => {
            if (!b) return;
            b.disabled = disabled || busy || left > 0;
            b.textContent = busy ? busyLabel : `${again ? t[again] : base}${left && !busy ? ` (${left})` : ''}`;
            if (busy) { b.setAttribute('aria-busy', 'true'); b.setAttribute('aria-label', base); } else { b.removeAttribute('aria-busy'); b.removeAttribute('aria-label'); }
        };
        const loading = (rs) => rs.state === 'loading';
        const doneAgain = (rs, k) => (rs.state === 'done' ? k : null);
        const im = m.image;
        set($('[data-pai-run="identify"]'), t.identify, t.identifying, null, loading(im.identify), loading(im.prices) || (im.source === 'upload' && !im.file));
        set($('[data-pai-run="image-prices"]'), t.search_prices, t.searching, doneAgain(im.prices, 'search_again'), loading(im.prices), !im.query.trim() || loading(im.identify));
        set($('[data-pai-run="market"]'), t.search_prices, t.searching, doneAgain(m.market.prices, 'search_again'), loading(m.market.prices), !m.market.query.trim());
        set($('[data-pai-run="titles"]'), t.suggest, t.suggesting, doneAgain(m.titles.req, 'suggest_again'), loading(m.titles.req), false);
        $$('[data-pai-retry]').forEach((b) => { b.disabled = left > 0; });
    }
    function render(tab = S.tab) {
        if (tab !== S.tab) return; // hidden panels render when shown
        ({ image: renderImage, market: renderMarket, titles: renderTitles })[tab]();
        syncButtons();
    }

    // ---------------------------------------------------------------- target select
    const sel = $('[data-pai-target]');
    function fillTargets() {
        sel.replaceChildren(...cfg.targets.map((x, i) => h('option', { value: String(i), text: `${x.type === 'product' ? t.product : t.variant}: ${x.label}${x.sku ? ` (${x.sku})` : ''}` })));
        sel.value = String(S.ti);
    }
    function setTarget(i) {
        S.ti = cfg.targets[i] ? i : 0;
        sel.value = String(S.ti);
        render();
    }
    fillTargets();
    sel.addEventListener('change', () => setTarget(Number(sel.value)));

    // ---------------------------------------------------------------- tabs (roving tabindex, ←/→ follow the reading direction, Home/End)
    const tabBtns = $$('[data-pai-tab]');
    const ON = ['border-panel-accent', 'text-panel-accent-text'];
    const OFF = ['border-transparent', 'text-panel-text-muted', 'hover:text-panel-text'];
    function showTab(name, focusField = false) {
        S.tab = TABS.includes(name) ? name : 'image';
        tabBtns.forEach((b) => {
            const on = b.dataset.paiTab === S.tab;
            b.setAttribute('aria-selected', on ? 'true' : 'false');
            b.tabIndex = on ? 0 : -1;
            b.classList.remove(...(on ? OFF : ON));
            b.classList.add(...(on ? ON : OFF));
        });
        TABS.forEach((k) => { panel(k).hidden = k !== S.tab; });
        render();
        if (focusField) focusFirst();
    }
    function focusFirst() {
        const p = panel(S.tab);
        const el = S.tab === 'image' ? $('[data-pai-source]:checked:not(:disabled)', p) || $('[data-pai-source]:not(:disabled)', p)
            : (S.tab === 'market' ? $('[data-pai-query]', p) : $('[data-pai-run="titles"]', p));
        el?.focus();
    }
    tabBtns.forEach((b) => b.addEventListener('click', () => showTab(b.dataset.paiTab)));
    $('[role=tablist]').addEventListener('keydown', (e) => {
        const i = tabBtns.indexOf(document.activeElement);
        if (i < 0) return;
        let n = null;
        if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') n = i + ((e.key === 'ArrowRight') !== isRtl() ? 1 : -1);
        else if (e.key === 'Home') n = 0;
        else if (e.key === 'End') n = tabBtns.length - 1;
        if (n === null) return;
        e.preventDefault();
        const b = tabBtns[(n + tabBtns.length) % tabBtns.length];
        showTab(b.dataset.paiTab);
        b.focus();
    });

    // ---------------------------------------------------------------- form events
    $$('[data-pai-source]').forEach((r) => r.addEventListener('change', () => { mem().image.source = r.value; render('image'); }));
    fileInput.addEventListener('change', () => {
        const m = mem().image;
        const f = fileInput.files?.[0] || null;
        if (m.preview) URL.revokeObjectURL(m.preview);
        Object.assign(m, { file: null, preview: null, fileError: '' });
        if (f && !TYPES.includes(f.type)) m.fileError = t.upload_bad_type;
        else if (f && f.size > (cfg.upload_max_kb || 5120) * 1024) m.fileError = t.upload_too_big;
        else if (f) Object.assign(m, { file: f, preview: URL.createObjectURL(f) });
        if (!m.file) fileInput.value = '';
        render('image');
    });
    $('[data-pai-run="identify"]').addEventListener('click', () => runIdentify());
    $('[data-pai-image-query]').addEventListener('input', (e) => { mem().image.query = e.target.value; syncButtons(); });
    $('[data-pai-image-query]').addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); runImagePrices(); } });
    $('[data-pai-run="image-prices"]').addEventListener('click', () => runImagePrices());
    $('[data-pai-query]').addEventListener('input', (e) => { mem().market.query = e.target.value; syncButtons(); });
    $('[data-pai-market-form]').addEventListener('submit', (e) => { e.preventDefault(); runMarket(); });
    $('[data-pai-competitors]').addEventListener('change', (e) => { mem().titles.use = e.target.checked; });
    $('[data-pai-run="titles"]').addEventListener('click', () => runTitles());

    // ---------------------------------------------------------------- open (focus trap, Esc and focus return: ui.js)
    document.addEventListener('click', (e) => {
        const b = e.target.closest('[data-product-ai-open]');
        if (!b || b.disabled) return;
        e.preventDefault();
        const type = b.dataset.targetType || 'product';
        const i = cfg.targets.findIndex((x) => x.type === type && (type === 'product' || String(x.id) === String(b.dataset.targetId)));
        S.ti = i < 0 ? 0 : i;
        sel.value = String(S.ti);
        showTab(b.dataset.tab);
        window.panelModal?.open('product-ai');
        setTimeout(focusFirst, 60); // after ui.js focuses the first field
    });
}
