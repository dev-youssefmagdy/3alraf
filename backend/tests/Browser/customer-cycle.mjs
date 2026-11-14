// Full customer cycle (P9), real browser, /ar and /en, 1366px and 390px: home -> nav -> catalog/category/brand/search -> product -> bundle ->
// gift finder (products + bundles, share link) -> cart (qty, remove) -> register/login -> COD checkout -> order page -> account -> totals vs
// docs/CALCULATIONS.md -> stock -> cancel flow (setting off / on) -> admin status change -> marketer panel crawl.
// Fails on JS console errors, failed requests (unless intended) and untranslated keys visible on a page.
//
// ONLY against the throw-away DB (the script refuses any other, it places orders and toggles a setting):
//   mysql -e 'CREATE DATABASE `3alraf_smoke_qa`'; export DB_DATABASE=3alraf_smoke_qa APP_URL=http://3alraf.test:8099
//   php artisan migrate --force && php artisan db:seed --force      # then opening stock + 2 active bundles (see docs/qa/marketer-release.md)
//   (the full `db:seed` gift catalogue is required: with the generic CatalogSeeder/DemoSeeder catalogue the test cart passes the shipping
//    rate's free_over (1000), shipping_net becomes 0, nothing is prepaid and COD never offers the prepaid wallet step - correct behaviour, wrong data)
//   PHP_CLI_SERVER_WORKERS=6 php artisan serve --port=8099 &
//   MYSQL_PWD=... DB_USERNAME=root PORT=8099 OUT=/path/to/screens node tests/Browser/customer-cycle.mjs   (ONLY=ar-desktop to limit browsing)
import { chromium } from 'playwright-core';
import { execFileSync } from 'node:child_process';
import { mkdirSync, readdirSync, writeFileSync, mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { deflateSync, crc32 } from 'node:zlib';

const DB = process.env.DB_DATABASE;
if (DB !== '3alraf_smoke_qa') { console.error('Refusing to run: DB_DATABASE must be 3alraf_smoke_qa'); process.exit(2); }
const P = process.env.PORT || '8099';
const DOMAIN = process.env.SITE || '3alraf.test';
const OUT = process.env.OUT || '/tmp/customer-cycle-screens';
mkdirSync(OUT, { recursive: true });
const backend = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const site = `http://${DOMAIN}:${P}`;
const browser = await chromium.launch({ executablePath: process.env.CHROME || '/usr/bin/google-chrome', headless: true, args: ['--no-sandbox', `--host-resolver-rules=MAP ${DOMAIN} 127.0.0.1`] });

// ---------- results ----------
const results = [];
let cur = null;
const ok = (c, m) => { cur.checks++; if (!c) { cur.fails.push(m); console.log('  FAIL', m); } else console.log('  ok  ', m); return !!c; };
const step = async (name, fn, page = null) => {
    cur = { name, checks: 0, fails: [] };
    results.push(cur);
    console.log('STEP', name);
    try { await fn(); } catch (e) {
        cur.fails.push(`exception: ${e.message.split('\n')[0]}`);
        console.log('  FAIL exception', e.message.split('\n').slice(0, 3).join(' / '));
        if (page) await page.screenshot({ path: `${OUT}/FAIL-${name.replace(/[^a-z0-9]+/gi, '-')}.png`, fullPage: true }).catch(() => {});
    }
};

// ---------- helpers ----------
const sql = (q) => execFileSync('mysql', ['-u', process.env.DB_USERNAME || 'root', '-h', process.env.DB_HOST || '127.0.0.1', '-N', '-B', DB, '-e', q], { encoding: 'utf8' }).trim().split('\n').filter(Boolean).map((l) => l.split('\t'));
const artisan = (code) => execFileSync('php', ['artisan', 'tinker', '--execute', code], { cwd: backend, encoding: 'utf8', env: process.env });
const setCancel = (on) => artisan(`app(App\\Services\\Orders\\CustomerCancellationService::class)->setEnabled(${on ? 'true' : 'false'});`);
const num = (t) => { const s = String(t).replace(/[٠-٩]/g, (d) => '٠١٢٣٤٥٦٧٨٩'.indexOf(d)).replace(/٬|,/g, '').replace(/٫/g, '.'); const m = s.match(/-?\d+(\.\d+)?/); return m ? Number(m[0]) : NaN; };
const eq = (a, b) => Math.abs(Number(a) - Number(b)) < 0.005;
const stock = () => Object.fromEntries(sql('select product_id, sum(on_hand), sum(reserved) from stock_levels group by product_id').map(([p, o, r]) => [p, { on: Number(o), res: Number(r) }]));
const groups = [...new Set(['lang/en', 'lang/ar', 'resources/lang-erp/en'].flatMap((d) => readdirSync(join(backend, d))).filter((f) => f.endsWith('.php')).map((f) => f.replace('.php', '')))];
const keyRe = new RegExp(`(?<![@\\w.:/-])(${groups.join('|')})\\.[a-z0-9_]+(\\.[a-z0-9_]+)*(?![\\w@/-])`, 'g');
const proofDir = mkdtempSync(join(tmpdir(), 'cycle-'));
let pngN = 0;
const uniquePng = () => {   // unique valid PNG per call: a proof hash already used by another order is refused
    const chunk = (t, d) => { const b = Buffer.alloc(12 + d.length); b.writeUInt32BE(d.length, 0); b.write(t, 4, 'ascii'); d.copy(b, 8); b.writeUInt32BE(crc32(b.subarray(4, 8 + d.length)) >>> 0, 8 + d.length); return b; };
    const ihdr = Buffer.alloc(13); ihdr.writeUInt32BE(2, 0); ihdr.writeUInt32BE(2, 4); ihdr[8] = 8; ihdr[9] = 2;
    const seed = (Date.now() + (++pngN) * 7919) % 16777216, px = Buffer.from([seed >> 16 & 255, seed >> 8 & 255, seed & 255]);
    const f = join(proofDir, `proof-${pngN}.png`);
    writeFileSync(f, Buffer.concat([Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]), chunk('IHDR', ihdr), chunk('IDAT', deflateSync(Buffer.concat([0, 1].map(() => Buffer.concat([Buffer.from([0]), px, px]))))), chunk('IEND', Buffer.alloc(0))]));
    return f;
};
const views = { desktop: { viewport: { width: 1366, height: 900 } }, mobile: { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, deviceScaleFactor: 2 } };
const open = async (opts) => {
    const ctx = await browser.newContext(opts);
    const page = await ctx.newPage();
    const errs = [];
    page.allow = [];   // regexes of intended failing requests
    const allowed = (u, st = 0) => st < 500 && (page.allow.some((r) => r.test(u)) || (/\/validate(\?|$)/.test(u) && (st === 0 || st === 422)));
    // keys rendered later by JS (toasts, cart lines): the body scan after a navigation would miss them
    await ctx.exposeFunction('__qaKey', (t, url) => errs.push(`untranslated key rendered ${url} :: ${t}`));
    await ctx.addInitScript((src) => { const re = new RegExp(src, 'g'); new MutationObserver((ms) => ms.forEach((m) => m.addedNodes.forEach((n) => { const t = n.nodeType === 1 ? n.innerText : ''; if (t && t.length < 400) { const h = t.match(re); if (h) window.__qaKey(h.join(', '), location.href); } }))).observe(document, { childList: true, subtree: true }); }, keyRe.source);
    page.on('dialog', (d) => d.accept());
    page.on('pageerror', (e) => errs.push(`pageerror ${page.url()} :: ${e.message}`));
    page.on('console', (m) => { if (m.type() === 'error' && !/Failed to load resource|WebSocket connection/.test(m.text())) errs.push(`console ${page.url()} :: ${m.text()}`); });
    page.on('response', (r) => { if (r.status() >= 400 && !allowed(r.url(), r.status())) errs.push(`HTTP ${r.status()} ${r.url()}`); });
    page.on('requestfailed', (r) => { const f = r.failure()?.errorText || ''; if (!/ERR_ABORTED/.test(f) && !allowed(r.url())) errs.push(`requestfailed ${f} ${r.url()}`); });
    return { ctx, page, errs };
};
const drain = (run, tag) => { ok(run.errs.length === 0, `${tag}: no JS / HTTP errors${run.errs.length ? ' :: ' + [...new Set(run.errs)].slice(0, 6).join(' | ') : ''}`); run.errs.length = 0; };
const keys = async (page, tag) => { const m = [...new Set(((await page.locator('body').innerText()).match(keyRe) || []))]; ok(m.length === 0, `${tag}: no untranslated keys${m.length ? ' :: ' + m.slice(0, 8).join(', ') : ''}`); };
const visit = async (page, url, tag, shot = null) => {
    const r = await page.goto(url.startsWith('http') ? url : site + url, { waitUntil: 'networkidle' });
    ok(r && r.status() < 400, `${tag}: ${url} -> ${r?.status()}`);
    await keys(page, tag);
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    ok(overflow <= 1, `${tag}: no horizontal overflow (${overflow}px)`);
    if (shot) await page.screenshot({ path: `${OUT}/${shot}.png`, fullPage: true });
};
const cartCount = async (page) => num(await page.locator('[data-cart-count]').first().textContent());
const waitCount = async (page, min) => { await page.waitForFunction((n) => { const e = document.querySelector('[data-cart-count]'); return e && Number(e.textContent.trim()) >= n; }, min, { timeout: 8000 }).catch(() => {}); return cartCount(page); };
const cardTotal = async (page) => num(await page.locator('[data-totals] dd span.text-brand-600').first().textContent());
const firstDd = async (page) => num(await page.locator('[data-totals] dd').first().textContent());
const statusOf = (id) => sql(`select status from orders where id=${id}`)[0][0];

// ---------- catalog data ----------
const stocked = sql(`select p.id, p.slug, p.name_en, p.name_ar, coalesce(p.sale_price, p.price) from products p join stock_levels s on s.product_id=p.id where s.on_hand>0 and p.is_active=1 and p.brand_id is not null order by p.id limit 10`);
const product = stocked[6];
const bundles = sql('select id, slug, price from bundles where is_active=1 and deleted_at is null order by id');
const cat = sql(`select c.slug from categories c join category_product cp on cp.category_id=c.id where cp.product_id=${product[0]} limit 1`)[0]?.[0];
const brand = sql(`select b.slug from brands b join products p on p.brand_id=b.id where p.id=${product[0]}`)[0]?.[0];
const rate = sql('select city_id, shipping_company_id, price, free_over, cost from shipping_rates order by id limit 1')[0];
if (!product || bundles.length < 2) { console.error('Seed stock and 2 active bundles first'); process.exit(2); }

const ts = Date.now();
const password = `Qa-${ts}-pw`;
const combos = [['ar', 'desktop'], ['ar', 'mobile'], ['en', 'desktop'], ['en', 'mobile']].filter(([l, v]) => !process.env.ONLY || process.env.ONLY === `${l}-${v}`);
const runs = {};

// ---------- browse + cart + register + checkout ----------
for (const [locale, vname] of combos) {
    const tag = `${locale}-${vname}`;
    const run = await open(views[vname]);
    Object.assign(run, { tag, locale, vname });
    runs[tag] = run;
    const { page } = run;
    const L = `/${locale}`;

    await step(`${tag} home + nav`, async () => {
        await visit(page, L, `${tag} home`, `${tag}-home`);
        ok(await page.locator('html').getAttribute('dir') === (locale === 'ar' ? 'rtl' : 'ltr'), `${tag}: direction`);
        ok(await page.locator('[data-product-card]').count() > 0, `${tag}: home product cards`);
        const nav = vname === 'mobile' ? '[data-bottom-gift-finder]' : '[data-nav-gift-finder]';
        ok(await page.locator(nav).isVisible(), `${tag}: gift finder visible in nav (${nav})`);
        ok(await page.locator('[data-home-gift-finder]').count() === 1, `${tag}: home gift finder CTA`);
        await Promise.all([page.waitForURL(/gift-finder/), page.locator(nav).click()]);
        ok(/gift-finder/.test(page.url()), `${tag}: nav link opens gift finder`);
        drain(run, tag);
    }, page);

    await step(`${tag} catalog / category / brand / search`, async () => {
        await visit(page, `${L}/category/${cat}`, `${tag} category`, `${tag}-category`);
        ok(await page.locator('[data-product-card]').count() > 0, `${tag}: category lists products`);
        await visit(page, `${L}/brands`, `${tag} brands`);
        await visit(page, `${L}/brand/${brand}`, `${tag} brand`, `${tag}-brand`);
        ok(await page.locator('[data-product-card]').count() > 0, `${tag}: brand lists products`);
        const word = (locale === 'ar' ? product[3] : product[2]).split(/\s+/).filter((w) => w.length > 3)[0];
        await visit(page, `${L}/search?q=${encodeURIComponent(word)}`, `${tag} search`, `${tag}-search`);
        ok(await page.locator('[data-product-card]').count() > 0, `${tag}: search "${word}" finds products`);
        await visit(page, `${L}/search?q=zzzzqqqxx`, `${tag} search empty`);
        ok(await page.locator('[data-product-card]').count() === 0, `${tag}: nonsense search is empty`);
        drain(run, tag);
    }, page);

    await step(`${tag} product page add to cart`, async () => {
        await visit(page, `${L}/product/${product[1]}`, `${tag} product`, `${tag}-product`);
        ok(await page.locator('[data-add-to-cart-page]').isEnabled(), `${tag}: add to cart enabled (stock)`);
        await page.locator('[data-add-to-cart-page]').click();
        ok(await waitCount(page, 1) === 1, `${tag}: cart count 1 after product`);
        drain(run, tag);
    }, page);

    await step(`${tag} bundle pages`, async () => {
        await visit(page, `${L}/bundles`, `${tag} bundles`, `${tag}-bundles`);
        ok(await page.locator('a[href*="/bundle/"]').count() >= 2, `${tag}: bundles listed`);
        await visit(page, `${L}/bundle/${bundles[0][1]}`, `${tag} bundle`, `${tag}-bundle`);
        await page.locator('[data-add-bundle]').click();
        ok(await waitCount(page, 2) === 2, `${tag}: cart count 2 after bundle`);
        drain(run, tag);
    }, page);

    await step(`${tag} gift finder`, async () => {
        await visit(page, `${L}/gift-finder`, `${tag} gift finder`);
        await page.locator('[data-preset]').nth(2).click();
        await page.locator('#gf-form [type=submit]').click();
        await page.waitForSelector('#gf-out [data-card]', { timeout: 20000 });
        const cards = await page.locator('#gf-out [data-card]').count();
        const bs = await page.locator('#gf-out [data-card^="bundle:"]').count();
        const ps = await page.locator('#gf-out [data-card^="product:"]').count();
        ok(ps > 0 && bs > 0, `${tag}: results hold products (${ps}) and bundles (${bs})`);
        await keys(page, `${tag} gift results`);
        await page.screenshot({ path: `${OUT}/${tag}-gift-results.png`, fullPage: true });
        const share = await page.locator('[data-share]').first().getAttribute('data-share');
        const [bResp] = await Promise.all([page.waitForResponse((r) => /gift-finder\/[A-Za-z0-9]+\/cart$/.test(r.url())), page.locator('[data-add-gift-bundle]').last().click()]);
        ok(bResp.ok(), `${tag}: add bundle from results -> ${bResp.status()}`);
        ok(await waitCount(page, 3) >= 3, `${tag}: cart count grew after gift bundle`);
        // keep exactly one checked product, no bundles; the cart button posts and redirects to the cart
        const boxes = page.locator('#gf-out [data-pick]');
        for (let i = 0, n = await boxes.count(), first = true; i < n; i++) {
            const b = boxes.nth(i); const isP = await b.getAttribute('data-pick') === 'product';
            if (isP && first) { first = false; continue; }
            if (await b.isChecked()) await b.uncheck();
        }
        await Promise.all([page.waitForURL(/\/cart/, { timeout: 15000 }), page.locator('#gf-cart').click()]);
        ok(/\/cart/.test(page.url()), `${tag}: gift finder cart button lands on the cart`);
        await page.waitForLoadState('networkidle');
        await visit(page, share, `${tag} gift share`, `${tag}-gift-shared`);
        ok(await page.locator('#gf-out [data-card]').count() === cards, `${tag}: share page shows the same ${cards} suggestions`);
        drain(run, tag);
    }, page);

    await step(`${tag} cart quantity + remove`, async () => {
        await visit(page, `${L}/cart`, `${tag} cart`, `${tag}-cart`);
        await page.waitForSelector('[data-line]');
        const lines = await page.locator('[data-line]').count();
        ok(lines >= 3, `${tag}: cart has the added lines (${lines})`);
        const before = await firstDd(page);
        const quote = () => page.waitForResponse((r) => /cart\/items\/\d+/.test(r.url()) && r.request().method() !== 'GET');
        await Promise.all([quote(), page.locator('[data-line]').first().locator('[data-qty-plus]').click()]);
        await page.waitForTimeout(400);
        const up = await firstDd(page);
        ok(up > before, `${tag}: + raises the subtotal (${before} -> ${up})`);
        await Promise.all([quote(), page.locator('[data-line]').first().locator('[data-qty-minus]').click()]);
        await page.waitForTimeout(400);
        ok(eq(await firstDd(page), before), `${tag}: - restores the subtotal`);
        await Promise.all([quote(), page.locator('[data-line]').last().locator('[data-remove]').click()]);
        await page.waitForTimeout(400);
        ok(await page.locator('[data-line]').count() === lines - 1, `${tag}: remove drops the line`);
        await Promise.all([quote(), page.locator('[data-line]').first().locator('[data-qty-plus]').click()]);   // leave qty 2 on line 1 for the totals check
        await page.waitForTimeout(400);
        run.cartSubtotal = await firstDd(page);
        run.lineCount = await page.locator('[data-line]').count();
        drain(run, tag);
    }, page);

    await step(`${tag} guest checkout redirect, register, logout, login`, async () => {
        await page.goto(`${site}${L}/checkout`, { waitUntil: 'networkidle' });
        ok(/\/login/.test(page.url()), `${tag}: guest checkout is sent to login (${page.url()})`);
        await visit(page, `${L}/register`, `${tag} register`, `${tag}-register`);
        run.email = `qa-${tag}-${ts}@example.com`;
        await page.fill('[name=name]', 'QA Customer');
        await page.fill('[name=email]', run.email);
        await page.fill('[name=phone]', `010${String(ts + Object.keys(runs).length).slice(-8)}`);
        await page.fill('[name=password]', password);
        await page.fill('[name=password_confirmation]', password);
        await Promise.all([page.waitForURL((u) => !/\/register/.test(u.pathname), { timeout: 15000 }), page.locator('form[data-ajax-form] button[type=submit]').first().click()]);
        ok(!/\/register/.test(page.url()), `${tag}: registered (${page.url()})`);
        await page.waitForLoadState('networkidle');
        await visit(page, `${L}/cart`, `${tag} cart after register`);
        await page.waitForSelector('[data-line]', { timeout: 8000 });
        ok(await page.locator('[data-line]').count() === run.lineCount, `${tag}: guest cart survives registration`);
        ok(eq(await firstDd(page), run.cartSubtotal), `${tag}: subtotal unchanged after registration`);
        // logout then login: the customer's cart must come back
        await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), page.evaluate(() => document.querySelector('form[action$="/logout"]').submit())]);
        await page.goto(`${site}${L}`, { waitUntil: 'networkidle' });
        ok(await cartCount(page) === 0, `${tag}: guest sees an empty cart after logout`);
        await page.goto(`${site}${L}/login`, { waitUntil: 'networkidle' });
        ok(await page.locator('[name=email]').count() === 1, `${tag}: logged out, login form shown`);
        await page.fill('[name=email]', run.email);
        await page.fill('[name=password]', password);
        await Promise.all([page.waitForURL((u) => !/\/login/.test(u.pathname), { timeout: 15000 }), page.locator('form[data-ajax-form] button[type=submit]').first().click()]);
        ok(!/\/login/.test(page.url()), `${tag}: logged in`);
        await page.waitForLoadState('networkidle');
        await visit(page, `${L}/cart`, `${tag} cart after login`);
        await page.waitForSelector('[data-line]', { timeout: 8000 });
        ok(await page.locator('[data-line]').count() === run.lineCount && eq(await firstDd(page), run.cartSubtotal), `${tag}: the customer's cart is back after login`);
        drain(run, tag);
    }, page);

    await step(`${tag} checkout COD + order page`, async () => {
        await visit(page, `${L}/checkout`, `${tag} checkout`);
        ok(/\/checkout/.test(page.url()), `${tag}: checkout opens for the customer`);
        await page.locator('[data-address-add]').click();
        await page.selectOption('#address-modal [name=city_id]', rate[0]);
        await page.fill('#address-modal [name=address]', '12 QA Street, Floor 3');
        await Promise.all([page.waitForSelector('[data-address-list] input[name=address_id]', { timeout: 10000 }), page.locator('#address-modal [type=submit]').click()]);
        await page.waitForSelector('[data-shipping-options] input[type=radio]', { timeout: 10000 });
        await page.locator('[data-shipping-options] input[type=radio]').first().check();
        await page.locator('input[name=payment_method_id][data-type=cod]').check();
        await page.waitForTimeout(1200);   // quote refresh
        const wallet = page.locator('input[name=prepay_method_id][data-type=wallet]').first();
        ok(await wallet.isVisible(), `${tag}: COD asks for the prepaid shipping leg (upfront default)`);
        await wallet.check();
        await page.waitForTimeout(600);
        await page.locator('input[type=file][name=proof]').setInputFiles(uniquePng());
        await page.waitForTimeout(800);
        run.checkoutTotal = await cardTotal(page);
        await page.screenshot({ path: `${OUT}/${tag}-checkout.png`, fullPage: true });
        run.stockBefore = stock();
        await Promise.all([page.waitForURL(/\/order\//, { timeout: 25000 }), page.locator('form[data-checkout] button[type=submit]').click()]);
        await page.waitForLoadState('networkidle');
        ok(/\/order\//.test(page.url()), `${tag}: order placed`);
        run.token = page.url().match(/\/order\/([^/?#]+)/)[1];
        await keys(page, `${tag} order`);
        await page.screenshot({ path: `${OUT}/${tag}-order.png`, fullPage: true });
        const row = sql(`select id, number, subtotal, discount, shipping, shipping_discount, shipping_net, shipping_cost, tax, packaging_fee, payment_fee_charged, cod_fee, total, cod_amount, prepaid_shipping, status, payment_status, cogs, net_profit from orders where public_token='${run.token}'`)[0];
        const [id, number, subtotal, discount, shipping, sd, shipNet, shipCost, tax, pack, feeCh, codFee, total, codAmt, prepaid, status, payStatus, cogs, net] = row;
        Object.assign(run, { orderId: id, number });
        ok((await page.locator('body').innerText()).includes(number), `${tag}: order number shown`);
        // ---- totals vs CALCULATIONS.md ----
        const items = sql(`select product_id, quantity, unit_price, line_subtotal, bundle_id, bundle_group, unit_cost from order_items where order_id=${id}`);
        run.items = items;
        ok(eq(subtotal, run.cartSubtotal), `${tag}: order subtotal ${subtotal} = cart subtotal ${run.cartSubtotal}`);
        ok(eq(total, run.checkoutTotal), `${tag}: order total ${total} = checkout total ${run.checkoutTotal}`);
        ok(items.every((i) => eq(Number(i[2]) * Number(i[1]), i[3])), `${tag}: every line_subtotal = unit_price x qty`);
        ok(eq(items.reduce((a, i) => a + Number(i[3]), 0), subtotal), `${tag}: subtotal = sum of lines`);
        ok(eq(items.reduce((a, i) => a + Number(i[6]) * Number(i[1]), 0), cogs), `${tag}: cogs = sum unit_cost x qty`);
        const free = rate[3] !== 'NULL' && Number(subtotal) - Number(discount) >= Number(rate[3]);
        ok(eq(shipping, free ? 0 : rate[2]), `${tag}: shipping ${shipping} = rate ${rate[2]} (free_over ${rate[3]})`);
        ok(eq(shipNet, Number(shipping) - Number(sd)), `${tag}: shipping_net = shipping - shipping_discount`);
        ok(eq(total, Number(subtotal) - Number(discount) + Number(shipNet) + Number(tax) + Number(pack) + Number(feeCh) + Number(codFee)), `${tag}: total = subtotal - discount + shipping_net + tax + packaging + fee + cod_fee`);
        ok(eq(prepaid, shipNet), `${tag}: prepaid_shipping = shipping_net (COD upfront)`);
        ok(eq(codAmt, Number(total) - Number(prepaid)), `${tag}: cod_amount = total - prepaid_shipping`);
        ok(status === 'pending' && eq(net, 0), `${tag}: pending order, net_profit 0`);
        ok(payStatus === 'pending_review', `${tag}: wallet prepay awaits proof review (${payStatus})`);
        for (const [gid, bid] of [...new Set(items.filter((i) => i[5] !== 'NULL').map((i) => `${i[5]}|${i[4]}`))].map((s) => s.split('|'))) {
            const sum = items.filter((i) => i[5] === gid).reduce((a, i) => a + Number(i[3]), 0);
            const q = sum / Number(bundles.find((b) => b[0] === bid)?.[2]);
            ok(q >= 1 && Math.abs(q - Math.round(q)) < 1e-6, `${tag}: bundle ${bid} lines sum to bundle price x whole quantity (${sum}, q=${q})`);
        }
        const body = await page.locator('main, body').first().innerText();
        for (const v of [subtotal, total]) ok(body.replace(/,/g, '').includes(Number(v).toFixed(2)), `${tag}: order page shows ${v}`);
        // ---- stock ----
        const after = stock();
        const need = {}; items.forEach((i) => { need[i[0]] = (need[i[0]] || 0) + Number(i[1]); });
        ok(Object.entries(need).every(([p, q]) => after[p].res - run.stockBefore[p].res === q && after[p].on === run.stockBefore[p].on), `${tag}: stock reserved at placement, on_hand untouched`);
        run.need = need;
        const pdf = await page.evaluate(async (u) => { const r = await fetch(u); const b = new Uint8Array(await r.arrayBuffer()); return { s: r.status, magic: String.fromCharCode(...b.slice(0, 4)) }; }, await page.locator('a[href*="invoice.pdf"]').first().getAttribute('href'));
        ok(pdf.s === 200 && pdf.magic === '%PDF', `${tag}: invoice PDF downloads`);
        await visit(page, `${L}/account`, `${tag} account`, `${tag}-account`);
        ok((await page.locator('body').innerText()).includes(number), `${tag}: order listed in account`);
        await page.locator(`a[href*="/order/${run.token}"]`).first().click();
        await page.waitForURL(/\/order\//);
        await page.waitForLoadState('networkidle');
        await keys(page, `${tag} order detail`);
        ok((await page.locator('body').innerText()).includes(number), `${tag}: account -> order detail opens`);
        ok(await page.locator('form[action$="/cancel"]').count() === 0, `${tag}: no cancel form while the setting is OFF`);
        drain(run, tag);
    }, page);
}

// guest items must join the customer's cart at login
if (runs['en-mobile']?.email) {
    const D = runs['en-mobile'];
    await step('guest cart merges into the customer cart at login', async () => {
        const { page } = D;
        await page.goto(`${site}/en`, { waitUntil: 'networkidle' });
        await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), page.evaluate(() => document.querySelector('form[action$="/logout"]').submit())]);
        await visit(page, `/en/product/${stocked[8][1]}`, 'merge product');
        await page.locator('[data-add-to-cart-page]').click();
        ok(await waitCount(page, 1) === 1, 'guest added one item');
        await page.goto(`${site}/en/login`, { waitUntil: 'networkidle' });
        await page.fill('[name=email]', D.email);
        await page.fill('[name=password]', password);
        await Promise.all([page.waitForURL((u) => !/\/login/.test(u.pathname), { timeout: 15000 }), page.locator('form[data-ajax-form] button[type=submit]').first().click()]);
        await page.waitForLoadState('networkidle');
        await visit(page, '/en/cart', 'merge cart');
        await page.waitForSelector('[data-line]', { timeout: 8000 });
        ok(await page.locator('[data-line]').count() === 1, 'guest line is in the customer cart after login');
        drain(D, 'merge');
    }, D.page);
}

const csrfPost = (page, path, body) => page.evaluate(async ([p, b]) => { const r = await fetch(p, { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/json' }, body: JSON.stringify(b) }); return r.status; }, [path, body]);

// ---------- cancel flow ----------
const A = runs['ar-desktop'], B = runs['en-desktop'], C = runs['ar-mobile'];
if (A && B && C) {
    await step('cancel OFF: refused', async () => {
        const { page } = A;
        await page.goto(`${site}/ar/order/${A.token}`, { waitUntil: 'networkidle' });
        page.allow.push(/\/cancel$/);
        const st = await csrfPost(page, `/ar/order/${A.token}/cancel`, { reason_code: 'changed_mind' });
        page.allow.pop();
        ok(st === 404, `cancel endpoint refused with the setting OFF (${st})`);
        ok(statusOf(A.orderId) === 'pending', 'order still pending');
        drain(A, 'cancel-off');
    }, A.page);

    setCancel(true);
    for (const [run, label] of [[B, 'en-desktop'], [C, 'ar-mobile']]) {
        await step(`cancel ON: ${label}`, async () => {
            const { page, locale } = run;
            await visit(page, `/${locale}/order/${run.token}`, `${label} order`, `${label}-cancel-form`);
            const form = page.locator('form[action$="/cancel"]');
            ok(await form.count() === 1, `${label}: cancel form visible with the setting ON`);
            page.allow.push(/\/cancel$/);
            await form.locator('button').click();   // no reason chosen -> validation message, nothing cancelled
            await page.waitForTimeout(1000);
            page.allow.pop();
            ok(statusOf(run.orderId) === 'pending', `${label}: a reason is required`);
            const code = await form.locator('select[name=reason_code] option').nth(1).getAttribute('value');
            await form.locator('select[name=reason_code]').selectOption(code);
            await Promise.all([page.waitForResponse((r) => /\/cancel$/.test(r.url())), form.locator('button').click()]);
            await page.waitForTimeout(1200);
            ok(statusOf(run.orderId) === 'cancelled', `${label}: order cancelled`);
            ok(sql(`select cancel_reason_code from orders where id=${run.orderId}`)[0][0] === code, `${label}: reason code stored`);
            const now = stock();
            ok(Object.entries(run.need).every(([p]) => now[p].on === run.stockBefore[p].on), `${label}: on_hand unchanged by a pre-shipment cancel`);
            await visit(page, `/${locale}/order/${run.token}`, `${label} order after cancel`, `${label}-cancelled`);
            ok(await page.locator('form[action$="/cancel"]').count() === 0, `${label}: no cancel form on a cancelled order`);
            drain(run, label);
        }, run.page);
    }
    await step('cancel ON: reservations released', async () => {
        // A (pending) and D (pending) still hold theirs; B and C must be gone: reserved = Sum over remaining open orders
        const open = sql(`select i.product_id, sum(i.quantity) from order_items i join orders o on o.id=i.order_id where o.status in ('pending','confirmed','processing') group by i.product_id`);
        const map = Object.fromEntries(open.map(([p, q]) => [p, Number(q)]));
        const now = stock();
        const touched = [...new Set([...Object.keys(B.need), ...Object.keys(C.need)])];
        ok(touched.every((p) => now[p].res === (map[p] || 0)), 'reserved stock equals the open orders only (cancelled orders released theirs)');
    });
    setCancel(false);
}

// ---------- admin lifecycle + marketer ----------
const admin = await open(views.desktop);
const adminPage = admin.page;
await step('admin login', async () => {
    await adminPage.goto(`${site}/admin/login`, { waitUntil: 'networkidle' });
    await adminPage.fill('[name=phone]', process.env.PHONE || '01000000001');
    await adminPage.fill('[name=password]', process.env.PASSWORD || 'password');
    await Promise.all([adminPage.waitForURL(/\/admin\/welcome/, { timeout: 15000 }), adminPage.click('button[type=submit]')]);
    ok(/\/admin\/welcome/.test(adminPage.url()), 'admin signed in');
    drain(admin, 'admin login');
}, adminPage);

if (A) {
    await step('admin: order listed, proof approved, status change, customer sees it', async () => {
        await adminPage.goto(`${site}/admin/locale/en`, { waitUntil: 'networkidle' });
        await visit(adminPage, '/admin/orders', 'admin orders', 'admin-orders');
        await adminPage.waitForFunction((n) => document.body.innerText.includes(n), A.number, { timeout: 15000 });
        ok((await adminPage.locator('body').innerText()).includes(A.number), 'order appears in the admin list');
        await visit(adminPage, `/admin/orders/${A.orderId}`, 'admin order', 'admin-order');
        const body = await adminPage.locator('body').innerText();
        const total = sql(`select total from orders where id=${A.orderId}`)[0][0];
        ok(body.replace(/,/g, '').includes(Number(total).toFixed(2)), 'admin order page shows the same total');
        await Promise.all([adminPage.waitForResponse((r) => /proof\/approve/.test(r.url())), adminPage.locator('[data-post-action*="proof/approve"]').click()]);
        await adminPage.waitForTimeout(1200);
        const paySt = sql(`select payment_status from orders where id=${A.orderId}`)[0][0];
        ok(paySt === 'partially_paid', `prepay proof approved -> partially_paid (${paySt})`);
        const change = async (to) => {
            await adminPage.goto(`${site}/admin/orders/${A.orderId}`, { waitUntil: 'networkidle' });
            await adminPage.selectOption('select[name=status]', to);
            await Promise.all([adminPage.waitForResponse((r) => new RegExp(`/orders/${A.orderId}/status$`).test(r.url())), adminPage.locator('form[action$="/status"] button[type=submit]').click()]);
            await adminPage.waitForTimeout(900);
            ok(statusOf(A.orderId) === to, `admin status -> ${to} (${statusOf(A.orderId)})`);
            await A.page.goto(`${site}/ar/order/${A.token}`, { waitUntil: 'networkidle' });
            // the timeline always lists all 5 labels; a reached step is the bold one, so count those
            const reached = await A.page.locator('ol li span.font-bold').count();
            ok(reached === { confirmed: 2, processing: 3, shipped: 4, delivered: 5 }[to], `customer sees "${to}" on the order timeline (${reached} steps reached)`);
        };
        await change('confirmed');
        const [sub, disc, cogs, shipNet, shipCost, fee, feeCh, comm, cod, pf, pc, net] = sql(`select subtotal, discount, cogs, shipping_net, shipping_cost, payment_fee, payment_fee_charged, affiliate_commission, cod_fee, packaging_fee, packaging_cost, net_profit from orders where id=${A.orderId}`)[0].map(Number);
        const want = sub - disc - cogs + shipNet - shipCost - fee + feeCh - comm + cod + pf - pc;
        ok(eq(net, want), `net_profit at confirmation = formula (${net} vs ${want})`);
        const before = stock();
        await change('processing');
        await change('shipped');
        const after = stock();
        ok(Object.entries(A.need).every(([p, q]) => before[p].on - after[p].on === q && before[p].res - after[p].res === q), 'stock on_hand and reserved decreased at shipment');
        await A.page.goto(`${site}/ar/order/${A.token}`, { waitUntil: 'networkidle' });
        ok(await A.page.locator('form[action$="/cancel"]').count() === 0, 'no cancel form after shipment');
        await A.page.screenshot({ path: `${OUT}/ar-desktop-order-shipped.png`, fullPage: true });
        await change('delivered');
        ok(sql(`select paid_amount=total, payment_status from orders where id=${A.orderId}`)[0][0] === '1', 'delivered COD order is fully paid');
        drain(admin, 'admin');
        drain(A, 'customer');
    }, adminPage);
}

await step('marketer panel crawl', async () => {
    await adminPage.goto(`${site}/admin/locale/ar`, { waitUntil: 'networkidle' });
    const seen = new Set(['/marketer']);
    const queue = ['/marketer'];
    const pages = [];
    const skip = /\/(file|thumb|download|logout)(\?|$)|media-preview|\/datatable/;
    while (queue.length && pages.length < 120) {
        const url = queue.shift();
        const r = await adminPage.goto(site + url, { waitUntil: 'networkidle' });
        pages.push(url);
        ok(r.status() === 200, `crawl ${url} -> ${r.status()}`);
        ok(await adminPage.locator('[data-panel="marketer"]').count() === 1, `crawl ${url}: marketer shell`);
        await keys(adminPage, `crawl ${url}`);
        const links = await adminPage.evaluate(() => [...document.querySelectorAll('a[href]')].map((a) => a.href));
        for (const l of links) {
            let u; try { u = new URL(l); } catch { continue; }
            if (u.host !== `${DOMAIN}:${P}` || !u.pathname.startsWith('/marketer') || skip.test(u.pathname)) continue;
            const key = u.pathname + u.search;
            if (!seen.has(key) && !seen.has(u.pathname)) { seen.add(key); seen.add(u.pathname); queue.push(key); }
        }
    }
    writeFileSync(`${OUT}/marketer-pages.json`, JSON.stringify(pages, null, 1));
    drain(admin, 'marketer ar desktop');
    const mkt = { list: pages };
    await adminPage.screenshot({ path: `${OUT}/marketer-ar-desktop.png`, fullPage: true });
    await adminPage.goto(`${site}/admin/locale/en`, { waitUntil: 'networkidle' });
    for (const url of mkt.list) {
        const r = await adminPage.goto(site + url, { waitUntil: 'networkidle' });
        ok(r.status() === 200, `en desktop ${url} -> ${r.status()}`);
        await keys(adminPage, `en desktop ${url}`);
    }
    drain(admin, 'marketer en desktop');
    await adminPage.setViewportSize({ width: 390, height: 844 });
    for (const locale of ['ar', 'en']) {
        await adminPage.goto(`${site}/admin/locale/${locale}`, { waitUntil: 'networkidle' });
        for (const url of mkt.list) {
            const r = await adminPage.goto(site + url, { waitUntil: 'networkidle' });
            ok(r.status() === 200, `${locale} 390px ${url} -> ${r.status()}`);
            const overflow = await adminPage.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
            ok(overflow <= 1, `${locale} 390px ${url}: no horizontal overflow (${overflow}px)`);
        }
        await adminPage.screenshot({ path: `${OUT}/marketer-${locale}-mobile.png`, fullPage: true });
    }
    drain(admin, 'marketer 390px');
}, adminPage);

await browser.close();
const checks = results.reduce((a, r) => a + r.checks, 0);
const fails = results.reduce((a, r) => a + r.fails.length, 0);
writeFileSync(`${OUT}/results.json`, JSON.stringify(results, null, 1));
console.log(`\n${results.filter((r) => !r.fails.length).length}/${results.length} steps passed, ${checks - results.reduce((a, r) => a + r.fails.filter((f) => !f.startsWith('exception')).length, 0)}/${checks} checks passed, ${fails} failure(s)`);
process.exit(fails ? 1 : 0);
