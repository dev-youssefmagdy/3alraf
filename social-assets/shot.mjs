import { chromium } from '/var/www/3alraf/backend/node_modules/playwright-core/index.mjs';
const d = '/var/www/3alraf/social-assets/';
const b = await chromium.launch({ executablePath: '/usr/bin/google-chrome', headless: true, args: ['--no-sandbox', '--allow-file-access-from-files'] });
const shot = async (file, w, h, out, body) => {
  const p = await b.newPage({ viewport: { width: w, height: h } });
  await p.goto('file://' + d + file, { waitUntil: 'networkidle' });
  if (body) await p.evaluate((h) => { document.body.innerHTML = h; }, body);
  await p.evaluate(() => document.fonts.ready); await p.waitForTimeout(300);
  await p.screenshot({ path: d + out }); await p.close();
};
await shot('facebook-cover.html', 1640, 624, 'facebook-cover.png');
await shot('facebook-profile.html', 720, 720, 'facebook-profile.png');
await shot('previews.html', 1290, 610, 'preview-desktop.png', '<div class="desk"><div class="c"><div class="shade"></div><div class="p"></div></div><div class="gap"></div></div>');
await shot('previews.html', 420, 344, 'preview-mobile.png', '<div class="mob"><div class="c"><div class="p"></div></div><div class="gap"></div></div>');
await b.close();
