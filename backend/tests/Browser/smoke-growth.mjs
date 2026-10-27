// Growth browser smoke (playwright-core + system Chrome): opens every GET `tenant.growth.*` admin page in ar and en as the tenant owner with all
// Growth switches ON, asserts no JS error / HTTP >= 400, html dir (rtl for ar, ltr for en), no horizontal overflow, no raw lang keys; plus the missed-opportunity
// edit modal. Screenshots go to storage/app/smoke-growth (git-ignored).
//
// SAFETY: run it ONLY against a separate database, e.g.
//   mysql -e "CREATE DATABASE 3alraf_smoke"; export DB_DATABASE=3alraf_smoke
//   php artisan migrate:fresh --seed --force          # never on the dev DB
//   (enable growth + all switches for tenant egy, see docs/n8n/README.md)
//   DB_DATABASE=3alraf_smoke APP_CENTRAL_DOMAIN=3alraf.test php artisan serve --port=8010 &
//   PORT=8010 npm run smoke:growth
import { chromium } from 'playwright-core';
import { execSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';

const P = process.env.PORT || '8010';
const DOMAIN = process.env.CENTRAL || '3alraf.test';
const SHOTS = process.env.SHOTS || 'storage/app/smoke-growth';
mkdirSync(SHOTS, { recursive: true });
const rules = `MAP ${DOMAIN} 127.0.0.1, MAP *.${DOMAIN} 127.0.0.1`;
const browser = await chromium.launch({ executablePath: process.env.CHROME || '/usr/bin/google-chrome', headless: true, args: ['--no-sandbox', `--host-resolver-rules=${rules}`] });
const egy = `http://egy.${DOMAIN}:${P}`;
const errs = [], failed = [];
let checks = 0;
const ok = (c, m) => { checks++; if (!c) { failed.push(m); console.log('  FAIL', m); } else console.log('  ok  ', m); };

const routes = JSON.parse(execSync('php artisan route:list --name=tenant.growth --json', { encoding: 'utf8', env: { ...process.env } }));
const pages = [...new Set(routes.filter(r => r.method.split('|').includes('GET') && !r.uri.includes('{') && !/datatable|\/data$/.test(r.uri)).map(r => '/' + r.uri))].sort();
console.log(`${pages.length} growth pages`);

const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
const page = await ctx.newPage();
page.on('pageerror', e => errs.push(`pageerror ${page.url()} :: ${e.message}`));
page.on('console', m => { if (m.type() === 'error' && !/Failed to load resource/.test(m.text())) errs.push(`console ${page.url()} :: ${m.text()}`); });
page.on('response', r => { if (r.status() >= 400) errs.push(`HTTP ${r.status()} ${r.url()}`); });

try {
  await page.goto(`${egy}/admin/login`, { waitUntil: 'networkidle' });
  await page.fill('[name=email]', 'owner@egy.test'); await page.fill('[name=password]', 'password');
  await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {}), page.click('button[type=submit]')]);
  ok(!/login/.test(page.url()), 'owner signed in');
  for (const loc of ['ar', 'en']) {
    await page.goto(`${egy}/admin/locale/${loc}`, { waitUntil: 'networkidle' });
    for (const u of pages) {
      await page.goto(`${egy}${u}`, { waitUntil: 'networkidle' }); await page.waitForTimeout(500);
      const m = await page.evaluate(() => ({ dir: document.documentElement.dir, lang: document.documentElement.lang, over: document.documentElement.scrollWidth - window.innerWidth, text: document.body.innerText }));
      const tag = `[${loc}] ${u}`;
      ok(m.dir === (loc === 'ar' ? 'rtl' : 'ltr'), `${tag} dir=${m.dir}`);
      ok(m.over <= 1, `${tag} no horizontal overflow (${m.over}px)`);
      ok(!/\b(growth_hub|growth_capital|growth_gaps|growth)\.[a-z_]+\.[a-z_]+/.test(m.text), `${tag} no raw lang keys`);
      await page.screenshot({ path: `${SHOTS}/${loc}${u.replace(/\//g, '_')}.png`, fullPage: true });
    }
    // missed-opportunity edit modal
    await page.goto(`${egy}/admin/growth/missed`, { waitUntil: 'networkidle' }); await page.waitForTimeout(800);
    const btn = page.locator('[data-missed-edit]').first();
    if (await btn.count()) {
      await btn.click();
      ok(await page.locator('#missed-edit').evaluate(d => d.open), `[${loc}] missed edit modal opens`);
      await page.fill('#missed-edit-form [name=result_note]', `smoke ${loc}`);
      await page.selectOption('#missed-edit-form [name=status]', 'accepted');
      await page.screenshot({ path: `${SHOTS}/${loc}_missed_modal.png` });
      await page.click('#missed-edit-form button[type=submit]'); await page.waitForTimeout(1200);
      ok(!(await page.locator('#missed-edit').evaluate(d => d.open)), `[${loc}] missed edit saved and closed`);
    } else ok(false, `[${loc}] missed edit button present`);
  }
} catch (e) { failed.push(`exception: ${e.message}`); console.log('EXCEPTION', e); }
console.log(`\n${checks} checks, ${failed.length} failed, ${errs.length} browser errors`);
errs.forEach(e => console.log(' ', e));
await browser.close();
process.exit(failed.length || errs.length ? 1 : 0);
