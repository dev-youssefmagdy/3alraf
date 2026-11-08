// Marketer panel browser check (P4): signs in on /admin/login, opens every /marketer page in ar + en, mobile + desktop, checks direction,
// JS errors, horizontal overflow, the mobile nav toggle, creates + revokes an API token, and saves screenshots of the dashboard.
// Same setup as smoke.mjs (throw-away DB + `php artisan serve --port=8000`):
//   PORT=8000 OUT=/path/to/screens node tests/Browser/marketer.mjs     (SITE=host, CHROME=/path/to/chrome, PHONE / PASSWORD of an admin)
import { chromium } from 'playwright-core';
import { mkdirSync } from 'node:fs';

const P = process.env.PORT || '8000';
const DOMAIN = process.env.SITE || '3alraf.test';
const OUT = process.env.OUT || '/tmp/marketer-screens';
mkdirSync(OUT, { recursive: true });
const browser = await chromium.launch({ executablePath: process.env.CHROME || '/usr/bin/google-chrome', headless: true, args: ['--no-sandbox', `--host-resolver-rules=MAP ${DOMAIN} 127.0.0.1`] });
const site = `http://${DOMAIN}:${P}`;
const failed = [];
let checks = 0;
const ok = (c, m) => { checks++; console.log(c ? '  ok  ' : '  FAIL', m); if (!c) failed.push(m); };
const pages = ['/marketer', '/marketer/gift-finder', '/marketer/gift-finder/tags', '/marketer/api-tokens', '/marketer/api-log', '/marketer/integration-clients'];
const views = { mobile: { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, deviceScaleFactor: 2 }, desktop: { viewport: { width: 1366, height: 900 } } };

for (const [vname, opts] of Object.entries(views)) {
    const ctx = await browser.newContext(opts);
    const page = await ctx.newPage();
    const errs = [];
    page.on('pageerror', (e) => errs.push(e.message));
    page.on('console', (m) => { if (m.type() === 'error' && !/Failed to load resource|WebSocket connection/.test(m.text())) errs.push(m.text()); });   // realtime bell: no Reverb server in the smoke setup
    page.on('response', (r) => { if (r.status() >= 400 && !r.url().includes('/validate')) errs.push(`HTTP ${r.status()} ${r.url()}`); });

    await page.goto(`${site}/marketer`, { waitUntil: 'networkidle' });
    ok(page.url().includes('/admin/login'), `${vname}: guest is sent to the admin login`);
    await page.fill('[name=phone]', process.env.PHONE || '01000000001');
    await page.fill('[name=password]', process.env.PASSWORD || 'password');
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), page.click('button[type=submit]')]);
    ok(page.url().includes('/admin/welcome'), `${vname}: admins land on /admin/welcome after login (${page.url()})`);
    ok(await page.locator(`a[href$="/marketer"]`).count() > 0, `${vname}: admin shell links to the marketing panel`);

    for (const locale of ['ar', 'en']) {
        await page.goto(`${site}/admin/locale/${locale}`, { waitUntil: 'networkidle' });
        for (const url of pages) {
            await page.goto(`${site}${url}`, { waitUntil: 'networkidle' });
            const tag = `${locale}-${vname} ${url}`;
            ok(await page.locator('[data-panel="marketer"]').count() === 1, `${tag}: marketer shell`);
            ok(await page.locator('html').getAttribute('dir') === (locale === 'ar' ? 'rtl' : 'ltr'), `${tag}: direction`);
            const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
            ok(overflow <= 1, `${tag}: no horizontal overflow (${overflow}px)`);
        }
        await page.goto(`${site}/marketer`, { waitUntil: 'networkidle' });
        ok(await page.locator('[data-kpi]').count() >= 4, `${locale}-${vname}: dashboard KPI cards`);
        if (vname === 'mobile') {
            ok(!(await page.locator('#sidebar').isVisible()), `${locale}-mobile: sidebar hidden until toggled`);
            await page.locator('[data-sidebar-toggle]').click();
            ok(await page.locator('#sidebar').isVisible(), `${locale}-mobile: sidebar opens from the menu button`);
            await page.screenshot({ path: `${OUT}/marketer-${locale}-mobile-nav.png` });
            await page.locator('[data-sidebar-toggle]').click({ force: true }).catch(() => {});
            await page.goto(`${site}/marketer`, { waitUntil: 'networkidle' });
        }
        await page.screenshot({ path: `${OUT}/marketer-${locale}-${vname}.png`, fullPage: true });
    }

    if (vname === 'desktop') {
        await page.goto(`${site}/marketer/api-tokens`, { waitUntil: 'networkidle' });
        await page.fill('[name=name]', 'browser-check');
        await page.locator('form[data-ajax-form] [type=submit]').click();
        await page.waitForSelector('#token-box:not(.hidden)', { timeout: 10000 }).catch(() => {});
        const token = (await page.locator('#token-value').textContent() || '').trim();
        ok(/^mkt_[a-z0-9]+\.[A-Za-z0-9]{48}$/.test(token), 'token shown once after create');
        const call = (t) => page.evaluate(async (t) => { const r = await fetch('/api/internal/marketing/overview', { headers: { Authorization: `Bearer ${t}`, Accept: 'application/json' }, credentials: 'omit' }); return [r.status, (await r.json()).success]; }, t);
        const [st, success] = await call(token);
        ok(st === 200 && success === true, `API answers with the new token (${st})`);
        await page.screenshot({ path: `${OUT}/marketer-tokens-desktop.png`, fullPage: true });
        await page.reload({ waitUntil: 'networkidle' });
        page.once('dialog', (d) => d.accept());
        await page.locator('[data-gpost]').first().click();
        await page.waitForTimeout(800);
        const [after] = await call(token);
        ok(after === 401, `revoked token is refused (${after})`);
        errs.splice(0, errs.length, ...errs.filter((e) => !e.includes('/api/internal/marketing/')));
    }
    ok(errs.length === 0, `${vname}: no JS / HTTP errors ${errs.join(' | ')}`);
    await ctx.close();
}
await browser.close();
console.log(`\n${checks - failed.length}/${checks} checks passed`);
process.exit(failed.length ? 1 : 0);
