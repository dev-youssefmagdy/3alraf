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
import { deflateSync, crc32 } from 'node:zlib';

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
  page.on('response', r => { if (r.status() >= 400 && !r.url().includes('/validate') && !(name === 'flow-b' && [422, 429].includes(r.status()))) errs.push(`[${name}] HTTP ${r.status()} ${r.url()}`); });
  return page;
};
const login = async (page, url, email, password) => {
  await page.goto(url, { waitUntil: 'networkidle' });
  await page.fill('[name=email]', email); await page.fill('[name=password]', password);
  await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), page.click('button[type=submit]')]);
};
const proofDir = mkdtempSync(join(tmpdir(), 'smoke-'));
let pngN = 0;
// unique valid 2x2 PNG per call (the server rejects a proof whose sha256 was already used by another order)
const uniquePng = () => {
  const chunk = (t, d) => { const b = Buffer.alloc(12 + d.length); b.writeUInt32BE(d.length, 0); b.write(t, 4, 'ascii'); d.copy(b, 8); b.writeUInt32BE(crc32(b.subarray(4, 8 + d.length)) >>> 0, 8 + d.length); return b; };
  const ihdr = Buffer.alloc(13); ihdr.writeUInt32BE(2, 0); ihdr.writeUInt32BE(2, 4); ihdr[8] = 8; ihdr[9] = 2;
  const seed = (Date.now() + (++pngN) * 7919) % 16777216, px = Buffer.from([seed >> 16 & 255, seed >> 8 & 255, seed & 255]);
  const raw = Buffer.concat([0, 1].map(() => Buffer.concat([Buffer.from([0]), px, px])));
  const f = join(proofDir, `proof-${pngN}.png`);
  writeFileSync(f, Buffer.concat([Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]), chunk('IHDR', ihdr), chunk('IDAT', deflateSync(raw)), chunk('IEND', Buffer.alloc(0))]));
  return f;
};

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
  await sf.fill('[name=name]', 'QA Tester'); const mainPhone = '010' + String(Date.now() % 100000000).padStart(8, '0'); await sf.fill('[name=phone]', mainPhone); await sf.fill('[name=email]', `qa${mainPhone}@example.com`);
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
  await sf.locator('input[type=file][name=proof]').setInputFiles(uniquePng()); // offline proof is mandatory at checkout
  await Promise.all([sf.waitForURL(/order\//, { timeout: 15000 }).catch(() => {}), sf.click('button[type=submit]')]);
  ok(/order\//.test(sf.url()), `order placed (${sf.url()})`);
  ok(await sf.locator('a[href*="/proof"]').count() > 0, 'proof uploaded at checkout is linked on the order page');
  ok(/review|pending|under/i.test(await sf.locator('body').innerText()), 'wallet order is pending proof review');
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

  // ================= bundles / packaging / offline proof (QA round) =================
  const SHOTS = process.env.SHOTS || '/tmp/claude-1001/-var-www-3alraf/2f1a9ec4-4e74-4d13-b082-812643fccc59/scratchpad';
  const uniq = () => String(Date.now() % 100000000).padStart(8, '0');
  const rawKey = /\b(?:store|orders|admin|ops|validation|auth|common)\.[a-z_]+(?:\.[a-z_]+)*\b/;
  const noOverflow = async (page) => page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1);
  const fillCheckout = async (page, phone, email) => {
    await page.fill('[name=name]', 'QA Flow'); await page.fill('[name=phone]', phone); await page.fill('[name=email]', email);
    await page.selectOption('[name=city_id]', { index: 1 }); await page.fill('[name=address]', '2 Test St');
    await page.waitForTimeout(1500);
    const sh = page.locator('[data-shipping-options] input[type=radio]'); if (await sh.count()) await sh.first().check();
  };
  const checkCod = async (page) => { // pick a shipping option that supports COD, then select COD
    const sh = page.locator('[data-shipping-options] input[type=radio]'); const cod = page.locator('[data-payment-options] input[data-type=cod]').first();
    for (let i = 0; i < await sh.count(); i++) { await sh.nth(i).check(); await page.waitForTimeout(700); if (await cod.isEnabled()) break; }
    await cod.check();
  };
  const addProduct = async (page, lang = 'en') => {
    await page.goto(`${egy}/${lang}/product/android-phone-128gb`, { waitUntil: 'networkidle' });
    const o = page.locator('[data-option]'); for (let i = 0; i < await o.count(); i++) await o.nth(i).click().catch(() => {});
    await page.click('[data-add-to-cart-page]'); await page.waitForTimeout(1200);
  };

  step('(a) offline proof input at checkout');
  const fa = await mk('flow-a');
  await addProduct(fa);
  await fa.goto(`${egy}/en/checkout`, { waitUntil: 'networkidle' });
  const phoneA = '010' + uniq();
  await fillCheckout(fa, phoneA, `qa${uniq()}@example.com`);
  const proofIn = fa.locator('input[type=file][name=proof]');
  ok(!(await proofIn.isVisible()), 'proof input hidden before choosing a method');
  await fa.locator('[data-payment-options] input[data-type=wallet]').first().check(); await fa.waitForTimeout(500);
  ok(await proofIn.isVisible() && await proofIn.isEnabled(), 'offline method shows the proof file input');
  await fa.locator('[data-payment-options] input[data-type=wallet]').first().check(); await fa.waitForTimeout(500);
  await fa.screenshot({ path: `${SHOTS}/a-checkout-offline-en.png`, fullPage: true });
  await fa.click('button[type=submit]'); await fa.waitForTimeout(1800);
  ok(/checkout/.test(fa.url()) && !/order\//.test(fa.url()), 'submit without proof is blocked');
  ok(await fa.locator('.field-error, .is-invalid, #toast-container .toast-error').count() > 0, 'missing-proof error is shown to the user');
  await proofIn.setInputFiles(uniquePng());
  await Promise.all([fa.waitForURL(/order\//, { timeout: 15000 }).catch(() => {}), fa.click('button[type=submit]')]);
  ok(/order\//.test(fa.url()), 'offline order with proof is created');
  ok(/review|pending|under/i.test(await fa.locator('body').innerText()), 'offline order is pending review');

  step('(a2) COD hides proof input (tenant sa has COD enabled)');
  const sa = `http://sa.${DOMAIN}:${P}`;
  const fs = await mk('flow-a2');
  await fs.goto(`${sa}/en`, { waitUntil: 'networkidle' });
  const saProd = await fs.locator('[data-product-card] a[href*="/product/"]').first().getAttribute('href');
  await fs.goto(new URL(saProd, sa).href, { waitUntil: 'networkidle' });
  const so = fs.locator('[data-option]'); for (let i = 0; i < await so.count(); i++) await so.nth(i).click().catch(() => {});
  await fs.click('[data-add-to-cart-page]'); await fs.waitForTimeout(1200);
  await fs.goto(`${sa}/en/checkout`, { waitUntil: 'networkidle' });
  await fillCheckout(fs, '050' + uniq(), `qa${uniq()}@example.com`);
  const sproof = fs.locator('input[type=file][name=proof]');
  await checkCod(fs); await fs.waitForTimeout(500);
  ok(!(await sproof.isVisible()), 'COD hides the proof input');
  const swal = fs.locator('[data-payment-options] input[data-type=wallet]');
  if (await swal.count()) { await swal.first().check(); await fs.waitForTimeout(500); ok(await sproof.isVisible(), 'switching to offline shows it again'); await checkCod(fs); await fs.waitForTimeout(300); ok(!(await sproof.isVisible()) && await sproof.isDisabled(), 'back to COD: hidden + disabled (not submitted)'); }

  step('(c) bundle -> cart -> checkout');
  const fc = await mk('flow-c');
  await fc.goto(`${egy}/en/bundles`, { waitUntil: 'networkidle' });
  ok(await fc.locator('a[href*="/bundle/"]').count() > 0, 'bundles list shows a bundle');
  await fc.goto(`${egy}/en/bundle/demo-bundle`, { waitUntil: 'networkidle' });
  ok(await fc.locator('[data-bundle-page] ul li').count() >= 2, 'bundle page lists components');
  const bPrice = (await fc.locator('[data-bundle-page] .text-3xl').innerText()).trim();
  await fc.screenshot({ path: `${SHOTS}/c-bundle-en.png`, fullPage: true });
  await fc.click('[data-add-bundle]'); await fc.waitForTimeout(1200);
  await fc.goto(`${egy}/en/cart`, { waitUntil: 'networkidle' }); await fc.waitForTimeout(500);
  ok(await fc.locator('[data-cart-lines] ul li').count() >= 2, 'cart groups bundle components under one line');
  const cartTot = await fc.locator('[data-cart-summary] [data-totals]').innerText();
  ok(cartTot.replace(/[^\d]/g, '').includes(bPrice.replace(/[^\d]/g, '')), `cart subtotal reflects bundle price ${bPrice}`);
  await fc.screenshot({ path: `${SHOTS}/c-cart-en.png`, fullPage: true });
  await fc.goto(`${egy}/en/checkout`, { waitUntil: 'networkidle' });
  await fillCheckout(fc, '010' + uniq(), `qa${uniq()}@example.com`);
  await fc.locator('[data-payment-options] input[data-type=wallet]').first().check(); await fc.waitForTimeout(800);
  await fc.locator('input[type=file][name=proof]').setInputFiles(uniquePng());
  const chkTot = await fc.locator('[data-totals]').innerText();
  const subOf = (t) => (t.match(/(?:subtotal|sub-total)[^\d]*([\d,.]+)/i) || [])[1];
  ok(subOf(cartTot) && subOf(cartTot) === subOf(chkTot), `checkout subtotal equals cart subtotal (${subOf(cartTot)} vs ${subOf(chkTot)})`);

  step('(d) packaging checkbox at checkout');
  const pk = fc.locator('[data-packaging-toggle]');
  ok(await pk.count() > 0, 'packaging picker present');
  const t0 = await fc.locator('[data-totals]').innerText();
  await pk.check(); await fc.waitForTimeout(1800);
  const t1 = await fc.locator('[data-totals]').innerText();
  ok(/packag/i.test(t1) && t1 !== t0, 'packaging adds a separate line and changes total');
  await fc.screenshot({ path: `${SHOTS}/d-checkout-packaging-en.png`, fullPage: true });
  await pk.uncheck(); await fc.waitForTimeout(1800);
  const t2 = await fc.locator('[data-totals]').innerText();
  ok(!/packag/i.test(t2) && t2 === t0, 'unchecking packaging removes the line and restores the total');
  await pk.check(); await fc.waitForTimeout(1500);
  await Promise.all([fc.waitForURL(/order\//, { timeout: 15000 }).catch(() => {}), fc.click('button[type=submit]')]);
  ok(/order\//.test(fc.url()), 'offline order with bundle + packaging placed');
  const ordTxt = await fc.locator('body').innerText();
  ok(/packag/i.test(ordTxt) && /bundle/i.test(ordTxt), 'order page shows packaging and bundle group');
  await fc.screenshot({ path: `${SHOTS}/c-order-en.png`, fullPage: true });
  const bundleNo = (ordTxt.match(/ORD-\d+/) || [''])[0]; ok(!!bundleNo, `bundle order number ${bundleNo}`);

  step('(e) arabic / rtl new screens');
  const fe = await mk('flow-e', { viewport: { width: 390, height: 800 } });
  for (const [name, path] of [['bundles', '/ar/bundles'], ['bundle', '/ar/bundle/demo-bundle']]) {
    await fe.goto(`${egy}${path}`, { waitUntil: 'networkidle' });
    const txt = await fe.locator('body').innerText();
    ok(!rawKey.test(txt), `ar ${name}: no raw lang keys`);
    ok(await noOverflow(fe), `ar ${name}: no horizontal overflow (390px)`);
    await fe.screenshot({ path: `${SHOTS}/e-${name}-ar.png`, fullPage: true });
  }
  await fe.click('[data-add-bundle]'); await fe.waitForTimeout(1200);
  await fe.goto(`${egy}/ar/cart`, { waitUntil: 'networkidle' }); await fe.waitForTimeout(500);
  await fe.locator('[data-packaging-toggle]').check(); await fe.waitForTimeout(1500);
  ok(!rawKey.test(await fe.locator('body').innerText()), 'ar cart+packaging: no raw lang keys');
  ok(await noOverflow(fe), 'ar cart: no horizontal overflow');
  await fe.screenshot({ path: `${SHOTS}/e-cart-ar.png`, fullPage: true });
  await fe.goto(`${egy}/ar/checkout`, { waitUntil: 'networkidle' });
  await fillCheckout(fe, '010' + uniq(), `qa${uniq()}@example.com`);
  await fe.locator('[data-payment-options] input[data-type=wallet]').first().check(); await fe.waitForTimeout(600);
  ok(await fe.locator('input[type=file][name=proof]').isVisible(), 'ar checkout shows proof input');
  ok(!rawKey.test(await fe.locator('body').innerText()), 'ar checkout: no raw lang keys');
  ok(await noOverflow(fe), 'ar checkout: no horizontal overflow');
  await fe.screenshot({ path: `${SHOTS}/e-checkout-ar.png`, fullPage: true });

  step('(b) 6th rapid offline attempt -> 429 shown to user');
  const fb = await mk('flow-b');
  await addProduct(fb);
  await fb.goto(`${egy}/en/checkout`, { waitUntil: 'networkidle' });
  await fillCheckout(fb, '010' + uniq(), `qa${uniq()}@example.com`);
  await fb.locator('[data-payment-options] input[data-type=wallet]').first().check(); await fb.waitForTimeout(500);
  await fb.locator('input[type=file][name=proof]').setInputFiles(uniquePng());
  // attempts already used by this IP in the window: flow (a) = 1 + the main wallet order = 1 (+ any earlier run); burn the rest via fetch
  const statuses = await fb.evaluate(async () => {
    const f = document.querySelector('form[data-checkout]'); const out = [];
    for (let i = 0; i < 7; i++) {
      const fd = new FormData(f); fd.set('phone', '011' + String(Date.now() % 100000000 + i).padStart(8, '0')); fd.set('email', `b${Date.now()}${i}@example.com`);
      const r = await fetch(f.action, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' } });
      out.push(r.status); if (r.status === 429) { out.push(JSON.stringify(await r.json().catch(() => ({})))); break; }
    }
    return out;
  });
  console.log('   fetch statuses', statuses.map(x => String(x).slice(0, 160)).join(' '));
  ok(statuses.includes(429), 'rapid offline attempts hit HTTP 429');
  await addProduct(fb); // the burst's first request placed an order and emptied the cart
  await fb.goto(`${egy}/en/checkout`, { waitUntil: 'networkidle' });
  await fillCheckout(fb, '010' + uniq(), `qa${uniq()}@example.com`);
  await fb.locator('[data-payment-options] input[data-type=wallet]').first().check(); await fb.waitForTimeout(500);
  await fb.locator('input[type=file][name=proof]').setInputFiles(uniquePng());
  fb.on('response', async r => { if (/checkout\/validate/.test(r.url())) console.log('   validate ->', r.status(), (await r.text().catch(() => '')).slice(0, 200)); });
  await fb.click('button[type=submit]'); await fb.waitForTimeout(1800);
  console.log('   field errors:', JSON.stringify(await fb.locator('.field-error').allInnerTexts()));
  const toast = fb.locator('#toast-container .toast-error');
  console.log('   toasts:', JSON.stringify(await toast.allInnerTexts()));
  const toastTxt = (await toast.allInnerTexts()).join(' | ');
  ok(/too many|try again|minute/i.test(toastTxt), `429 message toasted to user: "${toastTxt.replace(/\s+/g, ' ')}"`);
  await fb.screenshot({ path: `${SHOTS}/b-429-en.png`, fullPage: true });

  step('(f) tenant admin');
  const fp = await mk('flow-f');
  await login(fp, `${egy}/admin/login`, 'owner@egy.test', 'password');
  for (const path of ['bundles', 'packaging']) {
    await fp.goto(`${egy}/admin/${path}`, { waitUntil: 'networkidle' }); await fp.waitForTimeout(800);
    ok(await fp.locator('table tbody tr').count() > 0, `admin ${path} list has rows`);
    ok(!rawKey.test(await fp.locator('body').innerText()), `admin ${path}: no raw lang keys`);
    await fp.screenshot({ path: `${SHOTS}/f-${path}-list.png`, fullPage: true });
    await fp.goto(`${egy}/admin/${path}/create`, { waitUntil: 'networkidle' });
    ok(!rawKey.test(await fp.locator('body').innerText()), `admin ${path} create form: no raw lang keys`);
    await fp.screenshot({ path: `${SHOTS}/f-${path}-create.png`, fullPage: true });
  }
  // packaging CRUD: create -> edit -> delete
  const pname = 'QA Pack ' + uniq();
  await fp.goto(`${egy}/admin/packaging/create`, { waitUntil: 'networkidle' });
  await fp.evaluate((n) => { document.querySelector('[name="name[en]"]').value = n; document.querySelector('[name="name[ar]"]').value = 'تغليف ' + n; }, pname); await fp.fill('[name=price]', '12.5');
  await Promise.all([fp.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), fp.click('button[type=submit]')]);
  await fp.goto(`${egy}/admin/packaging`, { waitUntil: 'networkidle' }); await fp.waitForTimeout(800);
  ok((await fp.locator('table').innerText()).includes(pname) || (await fp.locator('table').innerText()).includes('تغليف ' + pname), 'packaging created via admin form');
  const row = fp.locator('table tbody tr', { hasText: pname });
  const rowCount = await row.count(); ok(rowCount === 1, 'packaging row visible once');
  fp.on('dialog', d => d.accept());
  const del = row.locator('[data-delete], button[data-url], a[data-delete]').first();
  if (await del.count()) { await del.click(); await fp.waitForTimeout(1500); await fp.reload({ waitUntil: 'networkidle' }); await fp.waitForTimeout(800); ok(!(await fp.locator('table').innerText()).includes(pname), 'packaging deleted'); }
  else ok(false, 'packaging row has a delete control');
  // bundle edit form opens with components
  await fp.goto(`${egy}/admin/bundles`, { waitUntil: 'networkidle' }); await fp.waitForTimeout(800);
  const edit = fp.locator('a[href*="/admin/bundles/"][href$="/edit"]').first();
  ok(await edit.count() > 0, 'bundle row links to edit');
  if (await edit.count()) { await edit.click(); await fp.waitForLoadState('networkidle'); ok(await fp.locator('[data-repeater-list] select').count() >= 2, 'bundle edit shows its components'); }
  await fp.goto(`${egy}/admin/payment-methods`, { waitUntil: 'networkidle' }); await fp.waitForTimeout(800);
  ok(/offline|أوفلاين/i.test(await fp.locator('body').innerText()), 'payment methods page labels wallet as offline');
  await fp.screenshot({ path: `${SHOTS}/f-payment-methods.png`, fullPage: true });
  await fp.goto(`${egy}/admin/orders`, { waitUntil: 'networkidle' }); await fp.waitForTimeout(800);
  await fp.fill('div.dataTables_filter input, input[type=search]', bundleNo); await fp.waitForTimeout(1500);
  const view = fp.locator('table tbody tr', { hasText: bundleNo }).locator('a[href*="/admin/orders/"]').first();
  await view.click(); await fp.waitForLoadState('networkidle');
  const adm = await fp.locator('body').innerText();
  ok(/packag|تغليف/i.test(adm) && /bundle|saver|باقة/i.test(adm), 'admin order view shows bundle group + packaging');
  ok(!rawKey.test(adm), 'admin order view: no raw lang keys');
  await fp.screenshot({ path: `${SHOTS}/f-order-bundle.png`, fullPage: true });
  // wallet order with proof: open the offline order and look for proof/hash section
  await fp.goto(`${egy}/admin/orders`, { waitUntil: 'networkidle' });
  const ids = await fp.locator('table tbody tr a[href*="/admin/orders/"]').evaluateAll(a => [...new Set(a.map(x => x.href))].slice(0, 8));
  let sawProof = false;
  for (const u of ids) { await fp.goto(u, { waitUntil: 'networkidle' }); if (/proof|إثبات/i.test(await fp.locator('body').innerText())) { sawProof = true; await fp.screenshot({ path: `${SHOTS}/f-order-proof.png`, fullPage: true }); break; } }
  ok(sawProof, 'admin order view shows the payment proof section');
} catch (e) {
  failed.push(`exception: ${e.message}`); console.log('EXCEPTION', e);
}

console.log(`\n${checks} checks, ${failed.length} failed, ${errs.length} browser errors`);
errs.forEach(e => console.log(' ', e));
await browser.close();
process.exit(failed.length || errs.length ? 1 : 0);
