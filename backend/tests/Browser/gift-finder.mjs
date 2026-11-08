// Gift Finder browser check (P3): /ar and /en, mobile + desktop. Opens the page, checks the header nav link, picks a budget preset,
// runs a search, checks the unified list (products + bundles), opens the share link, adds a bundle, and saves screenshots.
// Same setup as smoke.mjs (throw-away DB + `php artisan serve --port=8000`):
//   PORT=8000 OUT=/path/to/screens node tests/Browser/gift-finder.mjs     (SITE=host, CHROME=/path/to/chrome)
import { chromium } from 'playwright-core';
import { mkdirSync } from 'node:fs';

const P = process.env.PORT || '8000';
const DOMAIN = process.env.SITE || '3alraf.test';
const OUT = process.env.OUT || '/tmp/gift-finder-screens';
mkdirSync(OUT, { recursive: true });
const browser = await chromium.launch({ executablePath: process.env.CHROME || '/usr/bin/google-chrome', headless: true, args: ['--no-sandbox', `--host-resolver-rules=MAP ${DOMAIN} 127.0.0.1`] });
const site = `http://${DOMAIN}:${P}`;
const failed = [];
const ok = (c, m) => { console.log(c ? '  ok  ' : '  FAIL', m); if (!c) failed.push(m); };

const views = { mobile: { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, deviceScaleFactor: 2 }, desktop: { viewport: { width: 1366, height: 900 } } };
for (const locale of ['ar', 'en']) {
    for (const [vname, opts] of Object.entries(views)) {
        const ctx = await browser.newContext(opts);
        const page = await ctx.newPage();
        const errs = [];
        page.on('pageerror', (e) => errs.push(e.message));
        page.on('console', (m) => { if (m.type() === 'error' && !/Failed to load resource/.test(m.text())) errs.push(m.text()); });
        const tag = `${locale}-${vname}`;
        console.log('STEP', tag);

        await page.goto(`${site}/${locale}`, { waitUntil: 'networkidle' });
        const navSel = vname === 'mobile' ? '[data-bottom-gift-finder]' : '[data-nav-gift-finder]';   // phones: bottom bar (+ drawer); sm and up: header top nav
        ok(await page.locator(navSel).isVisible(), `${tag}: gift finder link visible (${navSel})`);
        ok(await page.locator('[data-home-gift-finder]').count() === 1, `${tag}: home CTA present`);
        await page.locator(navSel).click();
        await page.waitForURL(/gift-finder/);
        ok(await page.locator('html').getAttribute('dir') === (locale === 'ar' ? 'rtl' : 'ltr'), `${tag}: direction`);
        await page.screenshot({ path: `${OUT}/${tag}-form.png`, fullPage: true });

        await page.locator('[data-preset]').nth(2).click();
        await page.locator('#gf-form [type=submit]').click();
        await page.waitForSelector('#gf-out [data-card], #gf-out [role=alert], #gf-out p.rounded-xl', { timeout: 20000 });
        const cards = await page.locator('#gf-out [data-card]').count();
        const bundles = await page.locator('#gf-out [data-card^="bundle:"]').count();
        ok(cards > 0, `${tag}: ${cards} suggestions rendered`);
        ok(bundles > 0, `${tag}: ${bundles} bundle cards in the unified list`);
        ok((await page.locator('#gf-status').textContent()).trim() !== '', `${tag}: results announced in the live region`);
        await page.screenshot({ path: `${OUT}/${tag}-results.png`, fullPage: true });
        if (bundles) await page.locator('#gf-out [data-card^="bundle:"]').first().screenshot({ path: `${OUT}/${tag}-bundle-card.png` });

        if (vname === 'desktop' && bundles) {
            const [resp] = await Promise.all([page.waitForResponse((r) => /\/cart$/.test(r.url()) && r.request().method() === 'POST'), page.locator('[data-add-gift-bundle]').first().click()]);
            ok(resp.ok(), `${tag}: add bundle to cart -> ${resp.status()}`);
            await page.waitForTimeout(600);
            ok(Number(await page.locator('[data-cart-count]').first().textContent()) > 0, `${tag}: header cart count updated`);
        }

        const share = await page.locator('[data-share]').first().getAttribute('data-share');
        ok(/\/gift-finder\/r\/[A-Za-z0-9]+$/.test(share || ''), `${tag}: share link ${share}`);
        await page.goto(share, { waitUntil: 'networkidle' });
        ok(await page.locator('#gf-out [data-card]').count() === cards, `${tag}: share page re-renders the same ${cards} suggestions`);
        await page.screenshot({ path: `${OUT}/${tag}-shared.png`, fullPage: true });
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        ok(overflow <= 1, `${tag}: no horizontal scroll (${overflow}px)`);
        ok(errs.length === 0, `${tag}: no JS errors ${errs.join(' | ')}`);
        await ctx.close();
    }
}
await browser.close();
console.log(failed.length ? `FAILED ${failed.length}` : 'ALL OK', '-> screenshots in', OUT);
process.exit(failed.length ? 1 : 0);
