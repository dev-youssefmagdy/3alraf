// Product gallery (P8, Swiper "gallery" preset since the storefront redesign): real-browser check of autoplay, pause, swipe, arrows, thumbnails, RTL/LTR, reduced motion,
// autoplay refusal, variant images and the admin upload card. Arrows show from 1024px (design), so phones move with thumbnails / swipes.
// Run ONLY against a throw-away database. No autoplay flags are passed to Chrome, so its real autoplay policy applies.
//   export DB_DATABASE=3alraf_smoke APP_URL=http://3alraf.test:8000 MEDIA_ROOT=/scratch/media MEDIA_URL=http://3alraf.test:8001
//   php artisan migrate:fresh --seed --force ; php setup-script (products p8-*, prints FIX) ; php artisan serve --port=8000 &
//   FIX='<json>' VIDEO=/scratch/tiny.webm POSTER=/scratch/poster.jpg MEDIA_ROOT=/scratch/media MEDIA_PORT=8001 PORT=8000 OUT=/scratch/shots node tests/Browser/product-video.mjs
// The script serves MEDIA_ROOT itself with HTTP Range support, as nginx / Apache do in front of the media disk.
import { chromium } from 'playwright-core';
import { mkdirSync, createReadStream, statSync } from 'node:fs';
import { createServer } from 'node:http';
import { join, normalize, extname } from 'node:path';

const P = process.env.PORT || '8000';
const DOMAIN = process.env.SITE || '3alraf.test';
const OUT = process.env.OUT || '/tmp/product-video-screens';
const FIX = JSON.parse(process.env.FIX || '{}');
const MEDIA_ROOT = process.env.MEDIA_ROOT, MEDIA_PORT = Number(process.env.MEDIA_PORT || 8001);
mkdirSync(OUT, { recursive: true });

const TYPES = { '.webm': 'video/webm', '.mp4': 'video/mp4', '.avif': 'image/avif', '.webp': 'image/webp', '.jpg': 'image/jpeg', '.png': 'image/png' };
const mediaServer = createServer((req, res) => {
    const file = join(MEDIA_ROOT, normalize(decodeURIComponent(req.url.split('?')[0])).replace(/^(\.\.[/\\])+/, ''));
    let size;
    try { size = statSync(file).size; } catch { res.writeHead(404).end(); return; }
    const head = { 'Content-Type': TYPES[extname(file)] || 'application/octet-stream', 'Accept-Ranges': 'bytes' };
    const m = /bytes=(\d*)-(\d*)/.exec(req.headers.range || '');
    if (m) {
        const start = m[1] ? Number(m[1]) : 0, end = m[2] ? Math.min(Number(m[2]), size - 1) : size - 1;
        res.writeHead(206, { ...head, 'Content-Range': `bytes ${start}-${end}/${size}`, 'Content-Length': end - start + 1 });
        createReadStream(file, { start, end }).pipe(res);
    } else {
        res.writeHead(200, { ...head, 'Content-Length': size });
        createReadStream(file).pipe(res);
    }
}).listen(MEDIA_PORT);

const browser = await chromium.launch({ executablePath: process.env.CHROME || '/usr/bin/google-chrome', headless: true, args: ['--no-sandbox', `--host-resolver-rules=MAP ${DOMAIN} 127.0.0.1`] });
const site = `http://${DOMAIN}:${P}`;
const failed = [];
let checks = 0;
const ok = (c, m) => { checks++; console.log(c ? '  ok  ' : '  FAIL', m); if (!c) failed.push(m); };
const views = { 390: { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, deviceScaleFactor: 2 }, 1366: { viewport: { width: 1366, height: 900 } } };
const wait = (page, ms) => page.waitForTimeout(ms);
const SETTLE = 700;

const state = (page, i) => page.evaluate((k) => {
    const s = document.querySelectorAll('[data-gallery-slide]')[k], v = s.querySelector('video');
    const t = document.querySelector('[data-swiper-main]').getBoundingClientRect(), r = s.getBoundingClientRect();
    return { paused: v?.paused, muted: v?.muted, inline: v?.playsInline, t: v?.currentTime, preload: v?.preload, aligned: Math.abs(r.left - t.left) < 2, play: !s.querySelector('[data-gallery-play]')?.classList.contains('hidden') };
}, i);
// at least one increase over four samples (the clip loops, so a single pair could straddle the wrap)
const advancing = async (page, i) => {
    let prev = (await state(page, i)).t, up = false;
    for (let n = 0; n < 4; n++) { await wait(page, 300); const cur = (await state(page, i)).t; if (cur > prev) up = true; prev = cur; }
    return up;
};
const activeThumb = (page) => page.evaluate(() => [...document.querySelectorAll('[data-gallery-thumb]')].findIndex((t) => t.getAttribute('aria-current') === 'true'));
const playing = async (page, i, tag) => {
    await page.waitForFunction((k) => { const s = document.querySelectorAll('[data-gallery-slide]')[k], v = s.querySelector('video'), t = document.querySelector('[data-swiper-main]').getBoundingClientRect(); return v && !v.paused && v.currentTime > 0 && Math.abs(s.getBoundingClientRect().left - t.left) < 2; }, i, { timeout: 8000 }).catch(() => {});
    const s = await state(page, i);
    ok(s.paused === false, `${tag}: paused === false`);
    ok(s.muted === true, `${tag}: muted === true`);
    ok(s.inline === true, `${tag}: playsinline`);
    ok(s.aligned, `${tag}: slide fills the frame`);
    ok(await advancing(page, i), `${tag}: currentTime advancing`);
};
const stopped = async (page, i, tag) => { const s = await state(page, i); ok(s.paused === true, `${tag}: paused === true`); ok(s.t < 0.05, `${tag}: rewound (t=${s.t})`); };
// a real finger as the browser sees it: raw touch points, scrolled by the compositor
const swipe = async (ctx, page, forward, rtl) => {
    const c = await ctx.newCDPSession(page);
    const b = await page.locator('[data-swiper-main]').boundingBox();
    const y = b.y + b.height / 3, leftward = forward !== rtl, from = b.x + b.width * (leftward ? 0.85 : 0.15), step = (leftward ? -1 : 1) * b.width * 0.06;
    await c.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x: from, y }] });
    for (let i = 1; i <= 12; i++) { await c.send('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [{ x: from + i * step, y }] }); await page.waitForTimeout(16); }
    await c.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
    await c.detach();
};
const open = async (browserCtx, url) => {
    const page = await browserCtx.newPage();
    page.errs = [];
    page.videoReqs = [];
    page.on('pageerror', (e) => page.errs.push(e.message));
    page.on('console', (m) => { if (m.type() === 'error' && !/Failed to load resource/.test(m.text())) page.errs.push(m.text()); });
    page.on('request', (r) => { if (/\.(webm|mp4)(\?|$)/.test(r.url())) page.videoReqs.push(r.url()); });
    await page.goto(url, { waitUntil: 'load' });
    return page;
};
const noErrors = async (page, tag) => {
    const over = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    ok(over <= 1, `${tag}: no horizontal scroll (${over}px)`);
    ok(page.errs.length === 0, `${tag}: no JS errors ${page.errs.join(' | ')}`);
};
// next / previous slide the way a visitor would: the arrow when it is shown (≥1024), otherwise the neighbouring thumbnail
const step = async (page, dir) => {
    const arrow = page.locator(dir > 0 ? '[data-gallery-next]' : '[data-gallery-prev]');
    if (await arrow.isVisible()) { await arrow.click(); return; }
    const n = await page.locator('[data-gallery-thumb]').count(), cur = await activeThumb(page);
    await page.locator('[data-gallery-thumb]').nth((cur + dir + n) % n).click();
};
const urlOf = (page, i) => page.evaluate((k) => document.querySelectorAll('[data-gallery-slide]')[k].querySelector('video').src, i);

for (const locale of ['ar', 'en']) {
    const rtl = locale === 'ar';
    for (const [width, opts] of Object.entries(views)) {
        const tag = `${locale}-${width}`;
        console.log('STEP', tag);
        const ctx = await browser.newContext(opts);

        // ---- mixed product: image, video (poster), image, video (no poster)
        let page = await open(ctx, `${site}/${locale}/product/p8-mixed`);
        await wait(page, 900);
        ok(await page.locator('html').getAttribute('dir') === (rtl ? 'rtl' : 'ltr'), `${tag}: direction`);
        ok(await page.locator('[data-gallery-slide]:not([hidden])').count() === 4, `${tag}: four slides`);
        ok(await page.locator('[data-gallery-thumb]').count() === 4, `${tag}: four thumbnails`);
        ok(await page.locator('[data-gallery-thumb][data-type=video] [data-video-badge]').count() === 2 && await page.locator('[data-gallery-thumb][data-type=image] [data-video-badge]').count() === 0, `${tag}: play badge on video thumbnails only`);
        const u1 = await urlOf(page, 1), u3 = await urlOf(page, 3);
        ok((await state(page, 1)).preload === 'none' && (await state(page, 3)).preload === 'none', `${tag}: inactive videos have preload=none`);
        ok(page.videoReqs.length === 0, `${tag}: no video bytes requested while an image slide is active`);
        ok((await state(page, 1)).paused === true && (await state(page, 3)).paused === true, `${tag}: videos idle on an image slide`);
        await page.screenshot({ path: `${OUT}/${tag}-mixed-image.png` });

        await step(page, 1);
        await playing(page, 1, `${tag} next arrow -> video`);
        ok(await activeThumb(page) === 1, `${tag}: thumbnail follows the slide`);
        ok(page.videoReqs.includes(u1) && !page.videoReqs.includes(u3), `${tag}: only the active video was fetched`);
        await page.screenshot({ path: `${OUT}/${tag}-mixed-video.png` });

        // a user-unmuted video goes back to muted the next time it becomes active
        await page.evaluate(() => { document.querySelectorAll('[data-gallery-slide]')[1].querySelector('video').muted = false; });
        await step(page, 1);
        await wait(page, SETTLE);
        await stopped(page, 1, `${tag} next arrow -> left the video`);
        ok(await activeThumb(page) === 2, `${tag}: thumbnail on the image`);

        await page.locator('[data-gallery-thumb]').nth(3).click();
        await playing(page, 3, `${tag} thumbnail -> video without poster`);
        ok(page.videoReqs.includes(u3), `${tag}: second video fetched only when active`);
        await page.locator('[data-gallery-thumb]').nth(1).click();
        await wait(page, SETTLE);
        await stopped(page, 3, `${tag} thumbnail -> left the second video`);
        await playing(page, 1, `${tag} back on the first video (muted again)`);

        // off-screen pause + resume, keeping the position
        await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
        await wait(page, 600);
        let s = await state(page, 1);
        ok(s.paused === true, `${tag}: pauses while scrolled off-screen`);
        await page.evaluate(() => window.scrollTo(0, 0));
        await page.waitForFunction(() => !document.querySelectorAll('[data-gallery-slide]')[1].querySelector('video').paused, null, { timeout: 4000 }).catch(() => {});
        ok((await state(page, 1)).paused === false, `${tag}: resumes when back on screen`);

        // a visitor's own pause is respected
        await page.evaluate(() => document.querySelectorAll('[data-gallery-slide]')[1].querySelector('video').pause());
        await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight)); await wait(page, 500);
        await page.evaluate(() => window.scrollTo(0, 0)); await wait(page, 700);
        ok((await state(page, 1)).paused === true, `${tag}: a video the visitor paused stays paused`);

        await page.locator('[data-gallery-thumb]').nth(0).click();
        await wait(page, SETTLE);
        ok((await state(page, 1)).paused === true, `${tag}: paused on returning to the image`);

        if (Number(width) === 390) {
            await swipe(ctx, page, true, rtl);
            await playing(page, 1, `${tag} touch swipe -> video`);
            ok(await activeThumb(page) === 1, `${tag}: swipe moves the thumbnail`);
            await swipe(ctx, page, true, rtl);
            await wait(page, SETTLE);
            await stopped(page, 1, `${tag} touch swipe -> left the video`);
            await swipe(ctx, page, false, rtl);
            await playing(page, 1, `${tag} touch swipe back -> video`);
        } else {
            await page.locator('[data-swiper-main]').hover();
            await page.keyboard.press(rtl ? 'ArrowLeft' : 'ArrowRight');
            await playing(page, 1, `${tag} keyboard -> video`);
            await step(page, -1);
            await wait(page, SETTLE);
            await stopped(page, 1, `${tag} prev arrow -> left the video`);
            await step(page, -1);   // wraps to the last slide
            await playing(page, 3, `${tag} prev arrow wraps -> last video`);
        }
        await noErrors(page, `${tag} mixed`);
        await page.close();

        // ---- video as the first slide
        page = await open(ctx, `${site}/${locale}/product/p8-video-first`);
        await playing(page, 0, `${tag} video-first autoplays on load`);
        ok((await state(page, 0)).preload === 'metadata', `${tag}: first video preload=metadata`);
        await page.screenshot({ path: `${OUT}/${tag}-video-first.png`, fullPage: Number(width) === 390 });
        await step(page, 1);
        await wait(page, SETTLE);
        await stopped(page, 0, `${tag} video-first -> left`);
        await page.locator('[data-gallery-thumb]').first().click();
        await playing(page, 0, `${tag} video-first -> returned`);
        await noErrors(page, `${tag} video-first`);
        await page.close();

        // ---- a single video: no arrows or thumbnails, still autoplays
        page = await open(ctx, `${site}/${locale}/product/p8-video-only`);
        await playing(page, 0, `${tag} single video`);
        ok(await page.locator('[data-gallery-next]').count() === 0 && await page.locator('[data-gallery-thumb]').count() === 0, `${tag}: single video has no arrows or thumbnails`);
        await page.close();

        // ---- no video: the same Swiper gallery with images only
        page = await open(ctx, `${site}/${locale}/product/p8-plain`);
        await wait(page, 300);
        ok(await page.locator('[data-swiper="gallery"][data-swiper-ready]').count() === 1 && await page.locator('video').count() === 0, `${tag}: product without video gets the image-only gallery`);
        ok(await page.locator('[data-gallery-slide]').first().isVisible(), `${tag}: first image visible`);
        await page.locator('[data-gallery-thumb]').nth(1).click();
        await wait(page, SETTLE);
        ok(await activeThumb(page) === 1 && await page.evaluate(() => { const s = document.querySelectorAll('[data-gallery-slide]')[1], t = document.querySelector('[data-swiper-main]').getBoundingClientRect(); return Math.abs(s.getBoundingClientRect().left - t.left) < 2; }), `${tag}: thumbnail moves the main image`);
        await page.screenshot({ path: `${OUT}/${tag}-plain.png` });
        await noErrors(page, `${tag} plain`);
        await page.close();

        // ---- variant images on a video-first product
        page = await open(ctx, `${site}/${locale}/product/p8-variable`);
        await playing(page, 0, `${tag} variable product: preselected variant does not take over the video`);
        await page.locator(`[data-option="${FIX.value_own}"]`).click();   // deselects the preselected value
        await page.locator(`[data-option="${FIX.value_none}"]`).click();
        await page.evaluate(() => window.scrollTo(0, 0));   // on phones the options sit below the slider, which pauses it while it is off-screen
        await wait(page, SETTLE);
        ok((await state(page, 0)).paused === false, `${tag}: variant without its own image leaves the video playing`);
        await page.locator(`[data-option="${FIX.value_own}"]`).click();
        await page.locator(`[data-option="${FIX.value_own}"]`).click();
        await wait(page, SETTLE + 300);
        const vs = await page.evaluate(() => { const slides = [...document.querySelectorAll('[data-gallery-slide]')], s = slides[slides.length - 1], t = document.querySelector('[data-swiper-main]').getBoundingClientRect(); return { variant: s.dataset.type === 'variant', src: s.querySelector('img').src, aligned: Math.abs(s.getBoundingClientRect().left - t.left) < 2 }; });
        ok(vs.variant && vs.src === FIX.variant_image && vs.aligned, `${tag}: a variant's own image is shown`);
        ok((await state(page, 0)).paused === true, `${tag}: video paused when the variant image takes over`);
        await page.evaluate(() => window.scrollTo(0, 0));
        await page.locator('[data-gallery-thumb]').first().click();
        await playing(page, 0, `${tag}: thumbnail returns from the variant image`);
        await page.close();
        page = await open(ctx, `${site}/${locale}/product/p8-variable?variant=${FIX.variant_own}`);
        await wait(page, SETTLE);
        ok(await page.evaluate(() => !!document.querySelector('[data-gallery-slide][data-type=variant].swiper-slide-active')), `${tag}: ?variant= opens that variant's image`);
        ok((await state(page, 0)).paused === true, `${tag}: first video idle under ?variant=`);
        await noErrors(page, `${tag} variable`);
        await page.close();
        await ctx.close();

        // ---- prefers-reduced-motion: no autoplay, explicit play control
        const reduced = await browser.newContext({ ...opts, reducedMotion: 'reduce' });
        page = await open(reduced, `${site}/${locale}/product/p8-video-first`);
        await wait(page, 900);
        s = await state(page, 0);
        ok(s.paused === true && s.t === 0, `${tag} reduced motion: does not autoplay`);
        ok(s.play === true && await page.locator('[data-gallery-play]').first().isVisible(), `${tag} reduced motion: play button shown`);
        await page.screenshot({ path: `${OUT}/${tag}-reduced-motion.png` });
        await page.locator('[data-gallery-play]').first().click();
        await page.waitForFunction(() => { const v = document.querySelector('video'); return !v.paused && v.currentTime > 0; }, null, { timeout: 6000 }).catch(() => {});
        s = await state(page, 0);
        ok(s.paused === false && s.play === false, `${tag} reduced motion: play button starts it and disappears`);
        await reduced.close();

        // ---- autoplay refused by the browser: a play button, not a dead slide
        const refused = await browser.newContext(opts);
        await refused.addInitScript(() => {
            const real = HTMLMediaElement.prototype.play;
            let first = true;
            HTMLMediaElement.prototype.play = function () { if (first) { first = false; return Promise.reject(new DOMException('blocked', 'NotAllowedError')); } return real.call(this); };
        });
        page = await open(refused, `${site}/${locale}/product/p8-video-first`);
        await wait(page, 900);
        s = await state(page, 0);
        ok(s.paused === true && s.play === true, `${tag} autoplay refused: play button shown`);
        await page.locator('[data-gallery-play]').first().click();
        await page.waitForFunction(() => { const v = document.querySelector('video'); return !v.paused && v.currentTime > 0; }, null, { timeout: 6000 }).catch(() => {});
        s = await state(page, 0);
        ok(s.paused === false && s.play === false, `${tag} autoplay refused: the button plays it`);
        await refused.close();
    }
}

// ---- admin: upload, validation error, reorder, delete (the real UI)
console.log('STEP admin');
const actx = await browser.newContext({ viewport: { width: 1366, height: 900 } });
const admin = await actx.newPage();
const adminErrs = [];
admin.on('pageerror', (e) => adminErrs.push(e.message));
await admin.goto(`${site}/admin/login`, { waitUntil: 'load' });
await admin.fill('[name=phone]', '01000000001');
await admin.fill('[name=password]', 'password');
await Promise.all([admin.waitForNavigation({ waitUntil: 'load' }).catch(() => {}), admin.click('button[type=submit]')]);
await admin.goto(`${site}/admin/products/${FIX.admin_id}/edit`, { waitUntil: 'load' });
const card = admin.locator('[data-product-media]');
ok(await card.count() === 1, 'admin: media card on the edit page');
await card.locator('[name=video]').setInputFiles({ name: 'notes.mp4', mimeType: 'video/mp4', buffer: Buffer.from('this is not a video') });
await card.locator('[type=submit]').click();
await card.locator('.field-error').waitFor({ timeout: 8000 });
ok(/MP4/.test(await card.locator('.field-error').textContent()), 'admin: invalid file shows an inline error');
await card.locator('[name=video]').setInputFiles({ name: 'huge.mp4', mimeType: 'video/mp4', buffer: Buffer.alloc((await card.getAttribute('data-max-bytes')) * 1 + 1024) });
await card.locator('[type=submit]').click();
await admin.waitForTimeout(300);
ok(/\d+/.test(await card.locator('.field-error').textContent()), 'admin: oversized file refused in the browser with the limit in the message');
await card.locator('[name=video]').setInputFiles(process.env.VIDEO);
await card.locator('[name=poster]').setInputFiles(process.env.POSTER);
await card.locator('[type=submit]').click();
await admin.locator('[data-media-type=video]').waitFor({ timeout: 15000 });
ok(await admin.locator('[data-media-key]').count() === 2, 'admin: uploaded video joins the list');
await admin.locator('[data-product-media]').screenshot({ path: `${OUT}/admin-media-card.png` });
await admin.locator('[data-media-type=video] [data-media-move="-1"]').click();
await admin.waitForFunction(() => document.querySelector('[data-media-key]')?.dataset.mediaType === 'video', null, { timeout: 8000 }).catch(() => {});
ok(await admin.locator('[data-media-key]').first().getAttribute('data-media-type') === 'video', 'admin: video moved first');
const check = await actx.newPage();
await check.goto(`${site}/en/product/p8-admin`, { waitUntil: 'load' });
await check.waitForFunction(() => { const v = document.querySelector('video'); return v && !v.paused && v.currentTime > 0; }, null, { timeout: 8000 }).catch(() => {});
ok(await check.evaluate(() => { const v = document.querySelector('[data-gallery-slide] video'); return !!v && !v.paused && v.muted; }), 'admin -> storefront: the uploaded video is the first slide and autoplays');
await check.close();
admin.on('dialog', (d) => d.accept());
await admin.locator('[data-media-delete]').click();
await admin.waitForFunction(() => !document.querySelector('[data-media-type=video]'), null, { timeout: 8000 }).catch(() => {});
ok(await admin.locator('[data-media-type=video]').count() === 0, 'admin: video deleted');
const after = await actx.newPage();
await after.goto(`${site}/en/product/p8-admin`, { waitUntil: 'load' });
ok(await after.locator('video').count() === 0 && await after.locator('[data-gallery-slide]').count() === 1, 'admin -> storefront: back to the image-only gallery after delete');
ok(adminErrs.length === 0, `admin: no JS errors ${adminErrs.join(' | ')}`);
await actx.close();

await browser.close();
mediaServer.close();
console.log(failed.length ? `FAILED ${failed.length}/${checks}` : `ALL OK ${checks}/${checks}`, '-> screenshots in', OUT);
process.exit(failed.length ? 1 : 0);
