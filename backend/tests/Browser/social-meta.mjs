// Social-media library + Meta drafts browser check (P6). Run ONLY against a throw-away database (same setup as suggestions.mjs), with Meta unset so no call can reach Meta:
//   export DB_DATABASE=3alraf_smoke APP_URL=http://3alraf.test:8000 META_ACCESS_TOKEN= META_AD_ACCOUNT_ID= ; php artisan migrate:fresh --seed ; php artisan serve --port=8000 &
//   TOKEN=<mkt_… with marketing:read + meta:read + meta:draft> FIX='{"p1":1,"p2":2}' IMG=/path/a.png MP4=/path/a.mp4 PORT=8000 OUT=/path node tests/Browser/social-meta.mjs
// Covers: every new page ar/en × 390/1366 (direction, no overflow, no JS/HTTP errors), upload from the product tab, protected preview, approve, guest refused,
// signed preview, API draft + no approve route, draft form in the UI, approve, edit invalidates the approval, publish absent without a Meta connection.
import { chromium } from 'playwright-core';
import { mkdirSync } from 'node:fs';

const P = process.env.PORT || '8000';
const DOMAIN = process.env.SITE || '3alraf.test';
const OUT = process.env.OUT || '/tmp/social-meta-screens';
const TOKEN = process.env.TOKEN;
const FIX = JSON.parse(process.env.FIX || '{}');
mkdirSync(OUT, { recursive: true });
const browser = await chromium.launch({ executablePath: process.env.CHROME || '/usr/bin/google-chrome', headless: true, args: ['--no-sandbox', `--host-resolver-rules=MAP ${DOMAIN} 127.0.0.1`] });
const site = `http://${DOMAIN}:${P}`;
const failed = [];
let checks = 0;
const ok = (c, m) => { checks++; console.log(c ? '  ok  ' : '  FAIL', m); if (!c) failed.push(m); };
const views = { mobile: { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, deviceScaleFactor: 2 }, desktop: { viewport: { width: 1366, height: 900 } } };

const ctx = await browser.newContext(views.desktop);
const page = await ctx.newPage();
const errs = [];
const watch = (p) => {
    p.on('pageerror', (e) => errs.push(e.message));
    p.on('console', (m) => { if (m.type() === 'error' && !/Failed to load resource|WebSocket connection/.test(m.text())) errs.push(m.text()); });
    p.on('response', (r) => { if (r.status() >= 500 && !r.url().endsWith('/meta/campaigns')) errs.push(`HTTP ${r.status()} ${r.url()}`); });   // 503 there is asserted below (no Meta connection)
};
watch(page);
page.on('dialog', (d) => d.accept());
await page.goto(`${site}/ar`, { waitUntil: 'domcontentloaded' });
const api = (method, path, body) => page.evaluate(async ([m, p, b, t]) => {
    const r = await fetch('/api/internal/marketing/' + p, { method: m, credentials: 'omit', headers: { Authorization: `Bearer ${t}`, Accept: 'application/json', 'Content-Type': 'application/json' }, body: b ? JSON.stringify(b) : undefined });
    return [r.status, await r.json()];
}, [method, path, body, TOKEN]);
const soon = (d) => new Date(Date.now() + d * 86400000).toISOString().slice(0, 19).replace('T', ' ');

// ---------------------------------------------------------------- 1. API (as the MCP server)
const [stS, status] = await api('GET', 'meta/status');
ok(stS === 200 && status.data.configured === false && status.data.safety.activation_supported === false, `meta/status: not configured, no activation (${stS})`);
ok(!JSON.stringify(status).includes('access_token'), 'meta/status returns no token field');
const [stC] = await api('GET', 'meta/campaigns');
ok(stC === 503, `meta/campaigns without a connection is 503 (${stC})`);
const [stD, draft] = await api('POST', 'meta/drafts', { name: 'حملة المتصفح', objective: 'OUTCOME_SALES', product_ids: [FIX.p1], budget_type: 'daily', budget_amount: 200, start_at: soon(2), cta: 'SHOP_NOW',
    copy: { primary_text: 'نص إعلان تجريبي', headline: 'عنوان' }, audience: { age_min: 21, age_max: 45, genders: 'all' }, expected_kpis: { ctr: '1.5%' }, risks: 'مخزون محدود', assumptions: 'طلب موسمي' });
ok(stD === 201 && draft.data?.status === 'draft', `API creates a draft (${stD} ${draft.error || ''} ${JSON.stringify(draft.errors || '')})`);
const apiDraft = draft.data?.id;
const [stSub, sub] = await api('POST', `meta/drafts/${apiDraft}/submit`, {});
ok(stSub === 200 && sub.data?.status === 'pending_approval', `API submits the draft (${stSub})`);
for (const a of ['approve', 'publish', 'activate']) {
    const [s] = await api('POST', `meta/drafts/${apiDraft}/${a}`, {});
    ok(s === 404 || s === 405, `API has no ${a} endpoint (${s})`);
}

// ---------------------------------------------------------------- 2. sign in, upload from the product tab
await page.goto(`${site}/admin/login`, { waitUntil: 'networkidle' });
await page.fill('[name=phone]', process.env.PHONE || '01000000001');
await page.fill('[name=password]', process.env.PASSWORD || 'password');
await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), page.click('button[type=submit]')]);
ok(page.url().includes('/admin/welcome'), `signed in (${page.url()})`);
await page.goto(`${site}/admin/locale/ar`, { waitUntil: 'networkidle' });
await page.goto(`${site}/admin/products/${FIX.p1}/edit`, { waitUntil: 'networkidle' });
ok((await page.locator('[data-social-tab]').innerText()).includes('محتوى السوشيال ميديا'), 'product edit shows the Arabic tab');
await page.locator('[data-social-tab]').click();
await page.waitForSelector('[data-media-library]');
ok(await page.locator('[data-internal-notice]').count() === 1, 'internal-only notice shown');
ok(await page.locator('[data-library-empty]').count() === 1, 'tab starts empty');
const upload = async (file, title) => {
    if (!(await page.locator('[data-upload-form]').isVisible())) await page.locator('[data-upload-panel] summary').click();
    await page.selectOption('[data-upload-form] [name=kind]', file === process.env.MP4 ? 'video' : 'image');
    await page.setInputFiles('[data-upload-form] [name=file]', file);
    await page.fill('[data-upload-form] [name=title]', title);
    await page.fill('[data-upload-form] [name=campaign]', 'Smoke');
    await page.fill('[data-upload-form] [name=tags]', 'gift, eid');
    await Promise.all([page.waitForResponse((r) => r.url().endsWith('/marketer/media') && r.request().method() === 'POST', { timeout: 20000 }).catch(() => {}), page.locator('[data-upload-form] [type=submit]').click()]);
    await page.waitForTimeout(1500);
    await page.waitForLoadState('networkidle');
};
await upload(process.env.IMG, 'صورة البرنامج');
ok(await page.locator('[data-asset]').count() === 1, 'image uploaded and listed in the product tab');
ok(await page.locator('[data-asset] img').evaluate((i) => i.complete && i.naturalWidth > 0).catch(() => false), 'protected thumbnail renders');
await upload(process.env.MP4, 'فيديو البرنامج');
ok(await page.locator('[data-asset]').count() === 2, 'video uploaded and listed');
const ids = await page.locator('[data-asset]').evaluateAll((e) => e.map((x) => x.dataset.asset));
const [videoId, imageId] = ids;

// ---------------------------------------------------------------- 3. every page ar/en × mobile/desktop
const cookies = await ctx.cookies();
const pages = (extra) => ['/marketer/media', '/marketer/media?view=list', `/marketer/media/${imageId}`, `/marketer/media/${videoId}`, '/marketer/meta-ads', '/marketer/meta-ads/create', '/marketer/meta-ads/insights', `/marketer/meta-ads/${extra}`, `/marketer/meta-ads/${extra}/edit`];
for (const [vname, opts] of Object.entries(views)) {
    const c2 = await browser.newContext(opts);
    await c2.addCookies(cookies);
    const p2 = await c2.newPage();
    watch(p2);
    for (const locale of ['ar', 'en']) {
        await p2.goto(`${site}/admin/locale/${locale}`, { waitUntil: 'domcontentloaded' });
        for (const url of [...pages(apiDraft), `/admin/products/${FIX.p1}/edit?tab=social`]) {
            const res = await p2.goto(`${site}${url}`, { waitUntil: 'networkidle' });
            const tag = `${locale}-${vname} ${url}`;
            ok(res.status() === 200, `${tag}: 200 (${res.status()})`);
            ok(await p2.locator('html').getAttribute('dir') === (locale === 'ar' ? 'rtl' : 'ltr'), `${tag}: direction`);
            const overflow = await p2.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
            ok(overflow <= 1, `${tag}: no horizontal overflow (${overflow}px)`);
            const name = url.replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '');
            await p2.screenshot({ path: `${OUT}/${name}-${locale}-${vname}.png`, fullPage: true });
        }
    }
    await c2.close();
}

// ---------------------------------------------------------------- 4. library: approve, access control
await page.goto(`${site}/admin/locale/ar`, { waitUntil: 'domcontentloaded' });
await page.goto(`${site}/marketer/media/${imageId}`, { waitUntil: 'networkidle' });
ok(await page.locator('img[data-preview]').evaluate((i) => i.complete && i.naturalWidth > 0), 'protected preview loads for an authorised admin');
await Promise.all([page.waitForResponse((r) => r.url().endsWith('/approve'), { timeout: 15000 }), page.locator('[data-action="approve"]').click()]);
await page.waitForTimeout(1200);
await page.waitForLoadState('networkidle');
ok(await page.locator('[data-media-asset]').getAttribute('data-status') === 'approved', 'library item approved');
const guest = await browser.newContext(views.desktop);
const g = await guest.newPage();
const r1 = await g.goto(`${site}/marketer/media/${imageId}/file`, { waitUntil: 'domcontentloaded' });
ok(g.url().includes('/admin/login') || r1.status() === 403, `guest cannot open the file (${r1.status()} ${g.url()})`);
const [stM, med] = await api('GET', `media/${imageId}`);
ok(stM === 200 && !!med.data.preview_url && !JSON.stringify(med).includes('social-media/'), 'API returns metadata + signed URL, no storage path');
const r2 = await g.goto(med.data.preview_url);
ok(r2.status() === 200 && r2.headers()['x-robots-tag']?.includes('noindex'), `signed preview works without a session (${r2.status()})`);
const r3 = await g.goto(med.data.preview_url.replace(/signature=[^&]+/, 'signature=bad'));
ok(r3.status() === 403, `tampered signature refused (${r3.status()})`);
const pub = await g.goto(`${site}/ar/product/smoke-one`, { waitUntil: 'domcontentloaded' });
ok(pub.status() === 200 && !(await g.content()).includes('صورة البرنامج'), 'library item absent from the public product page');
await guest.close();

// ---------------------------------------------------------------- 5. draft in the UI, approve, edit invalidates, no publish without a connection
await page.goto(`${site}/marketer/meta-ads/create`, { waitUntil: 'networkidle' });
await page.fill('[name=name]', 'مسودة من الواجهة');
await page.evaluate(([p, a]) => {
    const set = (sel, v) => { const el = document.querySelector(sel); if (el.tomselect) el.tomselect.setValue(v); else { [...el.options].forEach((o) => { o.selected = v.includes(o.value); }); } };
    set('#f-products', [String(p)]); set('#f-assets', [String(a)]);
}, [FIX.p1, imageId]);
await page.fill('[name=budget_amount]', '150');
await page.fill('[name="copy[primary_text]"]', 'نص من الواجهة');
await page.check('[name=submit]');
await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle', timeout: 20000 }).catch(() => {}), page.locator('[data-action="save"]').click()]);
ok(/\/marketer\/meta-ads\/\d+$/.test(page.url()), `draft saved from the form (${page.url()})`);
ok(await page.locator('[data-meta-draft]').getAttribute('data-status') === 'pending_approval', 'saved and submitted');
await page.screenshot({ path: `${OUT}/draft-pending-ar-desktop.png`, fullPage: true });
await Promise.all([page.waitForResponse((r) => r.url().endsWith('/approve'), { timeout: 15000 }), page.locator('[data-action="approve"]').click()]);
await page.waitForTimeout(1200);
await page.waitForLoadState('networkidle');
ok(await page.locator('[data-meta-draft]').getAttribute('data-status') === 'approved', 'draft approved');
ok(await page.locator('[data-approval]').count() === 1, 'approval banner shows version and expiry');
ok(await page.locator('[data-not-configured]').count() === 1 && await page.locator('[data-action="publish"]').count() === 0, 'publish is not offered without a Meta connection');
await page.screenshot({ path: `${OUT}/draft-approved-ar-desktop.png`, fullPage: true });
await page.locator('[data-action="edit"]').click();
await page.waitForLoadState('networkidle');
await page.fill('[name=budget_amount]', '175');
await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle', timeout: 20000 }).catch(() => {}), page.locator('[data-action="save"]').click()]);
ok(await page.locator('[data-meta-draft]').getAttribute('data-status') === 'draft', 'edit returned the draft to draft');
ok(await page.locator('[data-approval]').count() === 0, 'edit invalidated the approval');
ok((await page.locator('[data-approvals]').innerText()).includes('invalidated'), 'approval history shows the invalidation');
await page.goto(`${site}/marketer`, { waitUntil: 'networkidle' });
ok(await page.locator('a[href$="/marketer/media"]').count() >= 1 && await page.locator('a[href$="/marketer/meta-ads"]').count() >= 1, 'nav shows the library and Meta drafts');

ok(errs.length === 0, `no JS / 5xx errors ${errs.join(' | ')}`);
await browser.close();
console.log(`\n${checks - failed.length}/${checks} checks passed`);
process.exit(failed.length ? 1 : 0);
