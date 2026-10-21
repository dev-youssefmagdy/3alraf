import { chromium } from 'playwright-core';
const P = process.env.PORT || '8000';
const rules = `MAP 3alraf.test 127.0.0.1, MAP *.3alraf.test 127.0.0.1`;
const browser = await chromium.launch({ executablePath: '/usr/bin/google-chrome', headless: true, args: ['--no-sandbox', `--host-resolver-rules=${rules}`] });
const errs = [];
const mk = async (name) => {
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const page = await ctx.newPage();
  page.on('pageerror', e => errs.push(`[${name}] pageerror ${page.url()} :: ${e.message}`));
  page.on('console', m => { if (m.type() === 'error') errs.push(`[${name}] console ${page.url()} :: ${m.text()}`); });
  page.on('response', r => { if (r.status() >= 400) errs.push(`[${name}] HTTP ${r.status()} ${r.url()}`); });
  return page;
};
const step = (s) => console.log('STEP', s);
const egy = `http://egy.3alraf.test:${P}`;
let page = await mk('sf');
step('home'); await page.goto(`${egy}/en`, { waitUntil: 'networkidle' });
console.log(' imgs', await page.locator('img').count(), 'cards', await page.locator('[data-product-card], article').count());
step('product'); await page.goto(`${egy}/en/product/android-phone-128gb`, { waitUntil: 'networkidle' });
const opts = page.locator('[data-option]');
console.log(' options', await opts.count());
const before = await page.locator('[data-sku]').innerText();
const n = await opts.count();
for (let i = 0; i < n; i++) { await opts.nth(i).click().catch(()=>{}); }
console.log(' sku', before, '->', await page.locator('[data-sku]').innerText(), 'stock:', await page.locator('[data-stock]').innerText());
await page.click('[data-add-to-cart-page]'); await page.waitForTimeout(1200);
console.log(' toast/cart count', await page.locator('.toast, #toast-container').innerText().catch(()=> 'n/a'));
step('cart'); await page.goto(`${egy}/en/cart`, { waitUntil: 'networkidle' });
console.log(' cart rows', await page.locator('[data-cart-item], [data-cart-row], tr').count());
step('checkout'); await page.goto(`${egy}/en/checkout`, { waitUntil: 'networkidle' });
await page.fill('[name=name]', 'QA Tester'); await page.fill('[name=phone]', '01012345678'); await page.fill('[name=email]', 'qa@example.com');
await page.selectOption('[name=city_id]', { index: 1 }); await page.fill('[name=address]', '1 Test St');
await page.waitForTimeout(1500);
const ship = page.locator('[data-shipping-options] input[type=radio]');
console.log(' shipping options', await ship.count()); if (await ship.count()) await ship.first().check();
await page.locator('[data-payment-options] input[type=radio]').first().check();
await page.waitForTimeout(800);
console.log(' totals', (await page.locator('[data-totals]').innerText()).replace(/\n/g, ' | '));
await Promise.all([page.waitForURL(/order\//, { timeout: 10000 }).catch(e => console.log(' no redirect', e.message)), page.click('button[type=submit]')]);
console.log(' after place url', page.url());
// admin
const ap = await mk('admin');
step('admin login'); await ap.goto(`${egy}/admin/login`, { waitUntil: 'networkidle' });
await ap.fill('[name=email]', 'owner@egy.test'); await ap.fill('[name=password]', 'password');
await Promise.all([ap.waitForNavigation({ waitUntil: 'networkidle' }), ap.click('button[type=submit]')]);
console.log(' url', ap.url());
for (const path of ['orders', 'catalog', 'cities', 'stock']) {
  step('admin ' + path); await ap.goto(`${egy}/admin/${path}`, { waitUntil: 'networkidle' });
  await ap.waitForTimeout(800);
  console.log(' rows', await ap.locator('table.dataTable tbody tr, table tbody tr').count(), ap.url());
}
const cp = await mk('central');
step('central'); await cp.goto(`http://3alraf.test:${P}/admin/login`, { waitUntil: 'networkidle' });
await cp.fill('[name=email]', 'admin@3alraf.test'); await cp.fill('[name=password]', 'password');
await Promise.all([cp.waitForNavigation({ waitUntil: 'networkidle' }), cp.click('button[type=submit]')]);
for (const path of ['products', 'tenants', 'categories', '']) { await cp.goto(`http://3alraf.test:${P}/admin/${path}`, { waitUntil: 'networkidle' }); await cp.waitForTimeout(600); console.log(' central', path, await cp.locator('table tbody tr').count()); }
console.log('\nERRORS', errs.length); errs.forEach(e => console.log(e));
await browser.close();
