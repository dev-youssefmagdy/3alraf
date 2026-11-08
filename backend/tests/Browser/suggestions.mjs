// Suggestions Center browser check (P5). Run ONLY against a throw-away database (same setup as smoke.mjs / marketer.mjs):
//   export DB_DATABASE=3alraf_smoke APP_URL=http://3alraf.test:8000 ; php artisan migrate:fresh --seed ; php artisan serve --port=8000 &
//   TOKEN=<mkt_… with marketing:read + suggestions:write> FIX='{"p1":1,"p2":2,"cat":3}' PORT=8000 OUT=/path node tests/Browser/suggestions.mjs
// Creates one suggestion per type through the API (as the MCP server would), then in the panel: every suggestions page ar/en × mobile/desktop
// (direction, no overflow, no JS/HTTP errors), detail + edit pages, edit a product suggestion, approve + apply product / SEO / coupon / theme /
// blog, publish the blog draft, the seasonal theme preview on the storefront (admin only) and the applied theme for guests. Screenshots in OUT.
import { chromium } from 'playwright-core';
import { mkdirSync } from 'node:fs';

const P = process.env.PORT || '8000';
const DOMAIN = process.env.SITE || '3alraf.test';
const OUT = process.env.OUT || '/tmp/suggestion-screens';
const TOKEN = process.env.TOKEN;
const FIX = JSON.parse(process.env.FIX || '{}');
mkdirSync(OUT, { recursive: true });
const browser = await chromium.launch({ executablePath: process.env.CHROME || '/usr/bin/google-chrome', headless: true, args: ['--no-sandbox', `--host-resolver-rules=MAP ${DOMAIN} 127.0.0.1`] });
const site = `http://${DOMAIN}:${P}`;
const failed = [];
let checks = 0;
const ok = (c, m) => { checks++; console.log(c ? '  ok  ' : '  FAIL', m); if (!c) failed.push(m); };
const views = { mobile: { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, deviceScaleFactor: 2 }, desktop: { viewport: { width: 1366, height: 900 } } };
const pages = ['/marketer/suggestions', '/marketer/suggestions/products', '/marketer/suggestions/bundles', '/marketer/suggestions/banners', '/marketer/suggestions/offers',
    '/marketer/suggestions/coupons', '/marketer/suggestions/themes', '/marketer/suggestions/seo', '/marketer/suggestions/blog', '/marketer/suggestions/social', '/marketer/suggestions/log'];

const ctx = await browser.newContext(views.desktop);
const page = await ctx.newPage();
const errs = [];
const watch = (p) => {
    p.on('pageerror', (e) => errs.push(e.message));
    p.on('console', (m) => { if (m.type() === 'error' && !/Failed to load resource|WebSocket connection/.test(m.text())) errs.push(m.text()); });
    p.on('response', (r) => { if (r.status() >= 500) errs.push(`HTTP ${r.status()} ${r.url()}`); });
};
watch(page);
await page.goto(`${site}/ar`, { waitUntil: 'domcontentloaded' });
const api = (method, path, body) => page.evaluate(async ([m, p, b, t]) => {
    const r = await fetch('/api/internal/marketing/' + p, { method: m, credentials: 'omit', headers: { Authorization: `Bearer ${t}`, Accept: 'application/json', 'Content-Type': 'application/json' }, body: b ? JSON.stringify(b) : undefined });
    return [r.status, await r.json()];
}, [method, path, body, TOKEN]);

// ---------------------------------------------------------------- 1. create one suggestion per type through the API
const soon = (d) => new Date(Date.now() + d * 86400000).toISOString().slice(0, 19).replace('T', ' ');
const bodies = {
    product: { title: 'تحسين اسم المنتج وتثبيته في المقترحات', reasoning: 'مبيعات جيدة ومخزون متاح', target_entity_type: 'product', target_entity_id: FIX.p1,
        payload: { name: { ar: 'اسم تسويقي أوضح للمنتج' }, pin: { list: 'recommended', position: 1 } } },
    bundle: { title: 'باقة العناية', payload: { name: { ar: 'باقة العناية', en: 'Care pack' }, price: FIX.bundlePrice, items: [{ product_id: FIX.p1, quantity: 1 }, { product_id: FIX.p2, quantity: 1 }] } },
    slider: { title: 'بنر رمضان', payload: { placement: 'home_hero', title: { ar: 'رمضان كريم' }, link_url: '/ar/gift-finder' } },
    offer: { title: 'عرض نهاية الأسبوع', payload: { name: { ar: 'عرض نهاية الأسبوع' }, starts_at: soon(1), ends_at: soon(3), items: [{ product_id: FIX.p1, discount_type: 'percent', value: 5 }] } },
    coupon: { title: 'كوبون 5%', payload: { code: 'SMOKE5', type: 'percent', value: 5, scope: 'products', product_ids: [FIX.p1], usage_limit: 50 } },
    seo: { title: 'SEO للمنتج', target_entity_type: 'product', target_entity_id: FIX.p1, payload: { meta_title: { ar: 'عنوان SEO تجريبي' }, meta_description: { ar: 'وصف ميتا تجريبي واضح ومفيد للعملاء يشرح المنتج ومميزاته بشكل مختصر' }, notes: { keywords: ['هدية'] } } },
    blog: { title: 'مقال: دليل الهدايا', payload: { title: { ar: 'دليل الهدايا للمناسبات' }, slug: 'smoke-gift-guide', content: { ar: '<h2>مقدمة</h2><p>نص المقال <a href="/ar/gift-finder">مستكشف الهدايا</a></p><script>alert(1)</script>' }, outline: ['مقدمة', 'أفكار'] } },
    seasonal_theme: { title: 'ثيم رمضان', payload: { key: 'ramadan', name: { ar: 'رمضان', en: 'Ramadan' }, tokens: { 'color-brand-600': '#1e5b4f', 'color-brand-700': '#174a40', 'color-brand-800': '#0f3d34', 'color-accent': '#d4a017' } } },
    social_content: { title: 'ريل للمنتج', target_entity_type: 'product', target_entity_id: FIX.p1, payload: { platform: 'instagram', format: 'reel', caption: { ar: 'جربه الآن' }, hashtags: ['هدايا'] } },
    media_asset: { title: 'صورة للمنتج', target_entity_type: 'product', target_entity_id: FIX.p1, payload: { asset_kind: 'image', brief: 'Flat lay on marble', aspect_ratio: '4:5' } },
};
const ids = {};
for (const [type, body] of Object.entries(bodies)) {
    const [st, j] = await api('POST', `suggestions/${type}`, body);
    ok(st === 201 && j.data?.status === 'pending', `API create ${type} (${st} ${j.error || ''} ${JSON.stringify(j.errors || '')})`);
    ids[type] = j.data?.id;
}
const [stApprove] = await api('POST', `suggestions/${ids.product}/approve`, {});
ok(stApprove === 404 || stApprove === 405, `API has no approve endpoint (${stApprove})`);
const [stList, list] = await api('GET', 'suggestions?per_page=50');
ok(stList === 200 && list.meta.total >= 10, `API list (${list.meta?.total})`);

// ---------------------------------------------------------------- 2. sign in, pages ar/en × mobile/desktop
await page.goto(`${site}/admin/login`, { waitUntil: 'networkidle' });
await page.fill('[name=phone]', process.env.PHONE || '01000000001');
await page.fill('[name=password]', process.env.PASSWORD || 'password');
await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), page.click('button[type=submit]')]);
ok(page.url().includes('/admin/welcome'), `signed in (${page.url()})`);
const cookies = await ctx.cookies();

for (const [vname, opts] of Object.entries(views)) {
    const c2 = await browser.newContext(opts);
    await c2.addCookies(cookies);
    const p2 = await c2.newPage();
    watch(p2);
    for (const locale of ['ar', 'en']) {
        await p2.goto(`${site}/admin/locale/${locale}`, { waitUntil: 'networkidle' });
        for (const url of [...pages, `/marketer/suggestions/${ids.seasonal_theme}`, `/marketer/suggestions/${ids.blog}`, `/marketer/suggestions/${ids.product}/edit`]) {
            await p2.goto(`${site}${url}`, { waitUntil: 'networkidle' });
            const tag = `${locale}-${vname} ${url}`;
            ok(await p2.locator('[data-panel="marketer"]').count() === 1, `${tag}: marketer shell`);
            ok(await p2.locator('html').getAttribute('dir') === (locale === 'ar' ? 'rtl' : 'ltr'), `${tag}: direction`);
            const overflow = await p2.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
            ok(overflow <= 1, `${tag}: no horizontal overflow (${overflow}px)`);
        }
        await p2.goto(`${site}/marketer/suggestions`, { waitUntil: 'networkidle' });
        await p2.waitForSelector('#sugg-table tbody tr', { timeout: 8000 }).catch(() => {});
        ok(await p2.locator('#sugg-table tbody tr').count() >= 10, `${locale}-${vname}: overview table rows`);
        await p2.screenshot({ path: `${OUT}/suggestions-overview-${locale}-${vname}.png`, fullPage: true });
        await p2.goto(`${site}/marketer/suggestions/${ids.seasonal_theme}`, { waitUntil: 'networkidle' });
        await p2.screenshot({ path: `${OUT}/suggestion-theme-${locale}-${vname}.png`, fullPage: true });
        await p2.goto(`${site}/marketer/suggestions/${ids.blog}`, { waitUntil: 'networkidle' });
        await p2.screenshot({ path: `${OUT}/suggestion-blog-${locale}-${vname}.png`, fullPage: true });
    }
    // filters
    if (vname === 'desktop') {
        await p2.goto(`${site}/marketer/suggestions/seo`, { waitUntil: 'networkidle' });
        await p2.waitForSelector('#sugg-table tbody tr', { timeout: 8000 }).catch(() => {});
        ok(await p2.locator('#sugg-table tbody tr').count() === 1, 'SEO page lists only the SEO suggestion');
        await p2.selectOption('#f-status', 'approved');
        await p2.locator('#sugg-filters [type=submit]').click();
        await p2.waitForTimeout(1200);
        ok(await p2.locator('#sugg-table tbody tr td.dt-empty, #sugg-table tbody tr td.dataTables_empty').count() === 1, 'status filter (approved) empties the SEO list');
    }
    await c2.close();
}

// ---------------------------------------------------------------- 3. edit, approve, apply in the UI (desktop, Arabic)
await page.goto(`${site}/admin/locale/ar`, { waitUntil: 'networkidle' });
page.on('dialog', (d) => d.accept(d.type() === 'prompt' ? 'موافقة من المتصفح' : undefined));
const act = async (id, action, form) => {
    await page.goto(`${site}/marketer/suggestions/${id}`, { waitUntil: 'networkidle' });
    if (form) await form();
    const btn = page.locator(`[data-action="${action}"]`).first();
    if (!(await btn.count())) return false;
    await Promise.all([page.waitForResponse((r) => r.request().method() === 'POST', { timeout: 15000 }).catch(() => {}), btn.click()]);
    await page.waitForTimeout(1500);
    await page.waitForLoadState('networkidle');
    return true;
};
// edit the product suggestion
await page.goto(`${site}/marketer/suggestions/${ids.product}/edit`, { waitUntil: 'networkidle' });
await page.fill('[name="payload[name][ar]"]', 'اسم تسويقي أوضح للمنتج (معدّل)');
await page.fill('[name=note]', 'تعديل من المتصفح');
await Promise.all([page.waitForURL(`**/marketer/suggestions/${ids.product}`, { timeout: 15000 }).catch(() => {}), page.locator('[data-edit-form] [type=submit]').click()]);
ok((await page.locator('[data-status]').first().getAttribute('data-status')) === 'edited', 'edit saved → status edited');
ok((await page.locator('[data-revisions] li').count()) === 2, 'edit created revision v2');

for (const type of ['product', 'seo', 'coupon', 'seasonal_theme', 'blog', 'bundle', 'slider', 'offer']) {
    await act(ids[type], 'approve');
    ok((await page.locator('[data-suggestion]').getAttribute('data-status')) === 'approved', `${type}: approved in the UI`);
    if (type === 'seasonal_theme') {
        // storefront preview, admin only (before apply)
        const [pv] = await Promise.all([ctx.waitForEvent('page'), page.locator('[data-action="preview"]').click()]);
        await pv.waitForLoadState('networkidle');
        ok(await pv.locator('#seasonal-theme').count() === 1 && await pv.locator('[data-theme-preview-bar]').count() === 1, 'theme preview on the storefront (admin)');
        ok((await pv.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--color-brand-600').trim())) === '#1e5b4f', 'preview colours applied (--color-brand-600)');
        await pv.screenshot({ path: `${OUT}/storefront-theme-preview-ar.png` });
        await pv.setViewportSize({ width: 390, height: 844 });
        await pv.screenshot({ path: `${OUT}/storefront-theme-preview-ar-mobile.png` });
        await pv.goto(`${site}/ar?theme_preview=off`, { waitUntil: 'networkidle' });
        await pv.close();
        const guest = await browser.newContext(views.desktop);
        const gp = await guest.newPage();
        await gp.goto(page.url().replace(/\/marketer.*/, '') + `/ar?theme_preview=${ids.seasonal_theme}`, { waitUntil: 'networkidle' });
        ok(await gp.locator('#seasonal-theme').count() === 0, 'guest never sees an unapplied theme preview');
        await guest.close();
    }
    await act(ids[type], 'apply');
    ok((await page.locator('[data-suggestion]').getAttribute('data-status')) === 'applied', `${type}: applied in the UI`);
    await page.screenshot({ path: `${OUT}/applied-${type}.png`, fullPage: true });
}
// blog: publish now
await act(ids.blog, 'publish');
ok(((await page.locator('[data-post-state]').textContent()) || '').includes('منشور'), 'blog published by the explicit action');
const guest = await browser.newContext(views.mobile);
const gp = await guest.newPage();
watch(gp);
await gp.goto(`${site}/ar/blog/smoke-gift-guide`, { waitUntil: 'networkidle' });
ok(await gp.locator('a[href="/ar/gift-finder"]').count() >= 1 && !(await gp.content()).includes('alert(1)'), 'published article renders sanitised with its internal link');
await gp.goto(`${site}/ar`, { waitUntil: 'networkidle' });
ok((await gp.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--color-brand-600').trim())) === '#1e5b4f', 'applied theme live for guests');
await gp.screenshot({ path: `${OUT}/storefront-theme-applied-ar-mobile.png` });
await guest.close();
// rollback the theme from the panel
await act(ids.seasonal_theme, 'rollback');
ok((await page.locator('[data-suggestion]').getAttribute('data-status')) === 'archived', 'theme rolled back (archived)');
await page.goto(`${site}/marketer/suggestions/log`, { waitUntil: 'networkidle' });
await page.waitForSelector('#exec-table tbody tr', { timeout: 8000 }).catch(() => {});
ok(await page.locator('#exec-table tbody tr').count() >= 9, 'execution log lists the runs');
await page.screenshot({ path: `${OUT}/suggestions-log-ar-desktop.png`, fullPage: true });
await page.goto(`${site}/marketer`, { waitUntil: 'networkidle' });
ok(await page.locator('[data-pending-suggestions]').count() === 1, 'dashboard pending-suggestions card');

ok(errs.length === 0, `no JS / 5xx errors ${errs.join(' | ')}`);
await browser.close();
console.log(`\n${checks - failed.length}/${checks} checks passed`);
process.exit(failed.length ? 1 : 0);
