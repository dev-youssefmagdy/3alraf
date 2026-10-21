// Browser smoke test (playwright-core + system Chrome). Asserts key storefront/admin flows and exits non-zero on any
// failed assertion, JS error or HTTP >= 400 (except expected ones).
//
//   cd backend && php artisan migrate:fresh --seed          # demo data: tenants egy + sa
//   APP_CENTRAL_DOMAIN=3alraf.test php artisan serve --port=8000 &
//   PORT=8000 npm run smoke          (CHROME=/path/to/chrome to override; no /etc/hosts edit needed)
import { chromium } from 'playwright-core';
import { writeFileSync, mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const P = process.env.PORT || '8000';
const DOMAIN = process.env.CENTRAL || '3alraf.test';
const rules = `MAP ${DOMAIN} 127.0.0.1, MAP *.${DOMAIN} 127.0.0.1`;
const browser = await chromium.launch({ executablePath: process.env.CHROME || '/usr/bin/google-chrome', headless: true, args: ['--no-sandbox', `--host-resolver-rules=${rules}`] });

const errs = [];
const failed = [];
let checks = 0;
const ok = (cond, msg) => { checks++; if (!cond) { failed.push(msg); console.log('  FAIL', msg); } else console.log('  ok  ', msg); };
const step = (s) => console.log('STEP', s);
const egy = `http://egy.${DOMAIN}:${P}`;
const central = `http://${DOMAIN}:${P}`;

const mk = async (name, opts = {}) => {
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, ...opts });
  const page = await ctx.newPage();
  page.on('pageerror', e => errs.push(`[${name}] pageerror ${page.url()} :: ${e.message}`));
  page.on('console', m => { if (m.type() === 'error' && !/Failed to load resource/.test(m.text())) errs.push(`[${name}] console ${page.url()} :: ${m.text()}`); });
  page.on('response', r => { if (r.status() >= 400 && !r.url().includes('/validate')) errs.push(`[${name}] HTTP ${r.status()} ${r.url()}`); });
  return page;
};
const login = async (page, url, email, password) => {
  await page.goto(url, { waitUntil: 'networkidle' });
  await page.fill('[name=email]', email); await page.fill('[name=password]', password);
  await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), page.click('button[type=submit]')]);
};
const tinyPng = join(mkdtempSync(join(tmpdir(), 'smoke-')), 'proof.png');
writeFileSync(tinyPng, Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64'));

try {
  // ---- storefront (English) ----
  const sf = await mk('sf');
  step('home');
  await sf.goto(`${egy}/en`, { waitUntil: 'networkidle' });
  ok(await sf.locator('[data-product-card]').count() > 0, 'home shows product cards');
  ok((await sf.locator('html').getAttribute('dir')) === 'ltr', 'english page is ltr');
  ok(await sf.locator('footer').innerText().then(t => /Cash on delivery|Vodafone/i.test(t)), 'footer lists active payment methods');

  step('brand page');
  const brandLink = sf.locator('a[href*="/brand/"]').first();
  if (await brandLink.count()) {
    await brandLink.click(); await sf.waitForLoadState('networkidle');
    ok(/\/brand\//.test(sf.url()) && await sf.locator('[data-product-card]').count() > 0, 'brand page lists products');
  } else ok(false, 'home has a brand link');

  step('search');
  await sf.goto(`${egy}/en/search?q=phone`, { waitUntil: 'networkidle' });
  ok(await sf.locator('[data-product-card]').count() > 0, 'fulltext search "phone" finds products');
  await sf.goto(`${egy}/en/search?q=zzzzqqq`, { waitUntil: 'networkidle' });
  ok(await sf.locator('[data-product-card]').count() === 0, 'search for nonsense is empty');

  step('product + variant');
  await sf.goto(`${egy}/en/product/android-phone-128gb`, { waitUntil: 'networkidle' });
  const opts = sf.locator('[data-option]');
  const n = await opts.count();
  ok(n > 0, 'variant options rendered');
  const skuBefore = await sf.locator('[data-sku]').innerText();
  for (let i = 0; i < n; i++) await opts.nth(i).click().catch(() => {});
  await sf.click('[data-add-to-cart-page]'); await sf.waitForTimeout(1200);
  ok(parseInt(await sf.locator('[data-cart-count]').first().innerText(), 10) >= 1, `cart count updated (sku ${skuBefore})`);

  step('checkout with coupon + wallet proof');
  await sf.goto(`${egy}/en/checkout`, { waitUntil: 'networkidle' });
  await sf.fill('[name=name]', 'QA Tester'); await sf.fill('[name=phone]', '01012345678'); await sf.fill('[name=email]', 'qa@example.com');
  await sf.selectOption('[name=city_id]', { index: 1 }); await sf.fill('[name=address]', '1 Test St');
  await sf.waitForTimeout(1500);
  const ship = sf.locator('[data-shipping-options] input[type=radio]');
  ok(await ship.count() > 0, 'shipping options appear after choosing a city');
  if (await ship.count()) await ship.first().check();
  const before = await sf.locator('[data-totals]').innerText();
  await sf.fill('[data-code-input]', 'WELCOME10'); await sf.click('[data-apply-code]'); await sf.waitForTimeout(1500);
  const after = await sf.locator('[data-totals]').innerText();
  ok(/discount/i.test(after) && after !== before, 'coupon WELCOME10 adds a discount line');
  const wallet = sf.locator('[data-payment-options] input[data-type=wallet]').first();
  ok(await wallet.count() > 0, 'wallet payment method offered');
  await wallet.check(); await sf.waitForTimeout(800);
  await Promise.all([sf.waitForURL(/order\//, { timeout: 15000 }).catch(() => {}), sf.click('button[type=submit]')]);
  ok(/order\//.test(sf.url()), `order placed (${sf.url()})`);
  const proofInput = sf.locator('input[type=file][name=proof]');
  ok(await proofInput.count() > 0, 'order page asks for the wallet payment proof');
  if (await proofInput.count()) {
    await proofInput.setInputFiles(tinyPng);
    await sf.locator('form[action*="/proof"] button').click();
    await sf.waitForLoadState('networkidle'); await sf.waitForTimeout(1200);
    await sf.reload({ waitUntil: 'networkidle' });
    ok(await sf.locator('a[href*="/proof"]').count() > 0, 'uploaded proof is linked on the order page');
  }
  ok(await sf.locator('a[href*="invoice.pdf"]').count() > 0, 'order page links the PDF invoice');
  // (APIRequestContext ignores --host-resolver-rules, so fetch from inside the page)
  const pdf = await sf.evaluate(async (u) => { const r = await fetch(u); const b = new Uint8Array(await r.arrayBuffer()); return { s: r.status, t: r.headers.get('content-type'), magic: String.fromCharCode(...b.slice(0, 4)) }; },
    await sf.locator('a[href*="invoice.pdf"]').first().getAttribute('href'));
  ok(pdf.s === 200 && /pdf/.test(pdf.t) && pdf.magic === '%PDF', 'invoice PDF downloads');

  // ---- Arabic / RTL ----
  step('arabic rtl');
  const ar = await mk('ar');
  await ar.goto(`${egy}/ar`, { waitUntil: 'networkidle' });
  ok((await ar.locator('html').getAttribute('dir')) === 'rtl', 'arabic page is rtl');
  ok((await ar.locator('html').getAttribute('lang')) === 'ar', 'arabic page lang=ar');
  ok(/[؀-ۿ]/.test(await ar.locator('body').innerText()), 'arabic text rendered');
  await ar.goto(`${egy}/ar/search?q=phone`, { waitUntil: 'networkidle' });
  ok(await ar.locator('[data-product-card]').count() > 0, 'search works on the arabic storefront');

  // ---- customer ----
  step('customer account');
  const cu = await mk('customer');
  await login(cu, `${egy}/en/login`, 'customer@egy.test', 'password');
  await cu.goto(`${egy}/en/account`, { waitUntil: 'networkidle' });
  ok(await cu.locator('a[href*="/order/"]').count() > 0, 'customer sees their orders');

  // ---- affiliate ----
  step('affiliate');
  const af = await mk('affiliate');
  await login(af, `${egy}/en/affiliate/login`, 'aff@egy.test', 'password');
  await af.goto(`${egy}/en/affiliate`, { waitUntil: 'networkidle' });
  ok(/affiliate/.test(af.url()) && !/login/.test(af.url()), 'affiliate dashboard opens after login');

  // ---- tenant admin ----
  step('tenant admin');
  const ap = await mk('admin');
  await login(ap, `${egy}/admin/login`, 'owner@egy.test', 'password');
  ok(!/login/.test(ap.url()), 'owner signed in');
  for (const path of ['orders', 'catalog', 'cities', 'stock', 'audit']) {
    await ap.goto(`${egy}/admin/${path}`, { waitUntil: 'networkidle' }); await ap.waitForTimeout(800);
    ok(await ap.locator('table tbody tr').count() > 0, `admin ${path} table has rows`);
  }
  await ap.goto(`${egy}/admin/reports/cod-settlement`, { waitUntil: 'networkidle' });
  ok(await ap.locator('table').count() > 0, 'cod-settlement report renders');

  // ---- central admin ----
  step('central admin');
  const cp = await mk('central');
  await login(cp, `${central}/admin/login`, 'admin@3alraf.test', 'password');
  ok(!/login/.test(cp.url()), 'central admin signed in');
  for (const path of ['products', 'tenants', 'categories', 'tags', 'audit', '']) {
    await cp.goto(`${central}/admin/${path}`, { waitUntil: 'networkidle' }); await cp.waitForTimeout(600);
    ok(!/login/.test(cp.url()), `central /${path} reachable`);
  }
  const health = await cp.evaluate(async () => { const r = await fetch('/api/v1/health'); return { s: r.status, j: await r.json() }; });
  ok(health.s === 200 && health.j.status === 'ok', 'central health endpoint ok');
} catch (e) {
  failed.push(`exception: ${e.message}`); console.log('EXCEPTION', e);
}

console.log(`\n${checks} checks, ${failed.length} failed, ${errs.length} browser errors`);
errs.forEach(e => console.log(' ', e));
await browser.close();
process.exit(failed.length || errs.length ? 1 : 0);
