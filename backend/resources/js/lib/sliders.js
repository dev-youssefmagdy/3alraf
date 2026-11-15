/**
 * Swiper.js for every horizontal scroller on the storefront (STOREFRONT_REDESIGN_PLAN.md, docs/design/STOREFRONT_DESIGN.md §3).
 *
 * Markup contract (any element with [data-swiper="<preset>"] is initialised on load and after `sliders:refresh`):
 *
 *   <section data-swiper="rail" [data-swiper-bleed] [data-swiper-options='{"loop":true}']>
 *     <button type="button" data-swiper-prev class="icon-btn icon-btn-outline" aria-label="…">…chevron…</button>   (optional, anywhere inside the root)
 *     <button type="button" data-swiper-next class="icon-btn icon-btn-outline" aria-label="…">…</button>             (optional)
 *     <div class="swiper"><div class="swiper-wrapper"><div class="swiper-slide">…</div>…</div></div>
 *     <div data-swiper-pagination></div>                                                                          (optional)
 *     <button type="button" data-swiper-toggle data-label-pause="…" data-label-play="…"></button>                  (optional, autoplay presets)
 *   </section>
 *
 * Presets: hero, categories, promo, rail, flash, brands, gallery, packaging, reviews, posts, chips. Slide widths per breakpoint are set in
 * storefront.css with the same numbers, so nothing moves when Swiper takes over. `data-swiper-bleed` makes a rail run to the screen
 * edge below 1024px (put `-mx-4 lg:mx-0` on the root) with 16px offsets. The instance is stored on `root.swiper` and announced with a
 * `swiper:ready` event on the root.
 *
 * Gallery preset (product page, storefront/_product-gallery.blade.php):
 *   <div data-swiper="gallery">
 *     <div class="swiper" data-swiper-main>…slides [data-gallery-slide data-type data-url]; a video slide holds <video muted playsinline> and an optional [data-gallery-play] button…</div>
 *     <div class="swiper" data-swiper-thumbs>…one thumb slide per main slide, each with a [data-gallery-thumb="<index>"] button…</div>
 *     <span data-swiper-pagination></span>  (fraction counter "1 / 5")
 *   </div>
 *   The active slide's video plays muted inline; leaving a slide pauses and rewinds its video; reduced motion / a refused autoplay shows
 *   [data-gallery-play]; the video pauses while the gallery is off-screen or the tab is hidden. `root.gallery.showVariantImage(url, {initial, explicit})`
 *   moves to a variant's image (appending a "variant" slide when the image is not one of the slides), as the retired product-gallery.js did.
 */
import Swiper from 'swiper';
import { A11y, Autoplay, FreeMode, Keyboard, Manipulation, Navigation, Pagination, Thumbs } from 'swiper/modules';
// Swiper's core CSS is imported by storefront.css inside a cascade layer, so Tailwind utilities can override it

const reducedMotion = () => matchMedia('(prefers-reduced-motion: reduce)').matches;
const label = (key, fallback) => window.__sf?.i?.[key] || fallback;

/** Preset options. `ctx` = { root, slides } so a preset can depend on the content (e.g. promo tiles). */
export const presets = {
    hero: () => ({
        slidesPerView: 1, spaceBetween: 12, loop: true, speed: 400,
        autoplay: { delay: 5000, pauseOnMouseEnter: true, disableOnInteraction: false },
        breakpoints: { 1280: { spaceBetween: 20 } },
        pagination: { clickable: true },
    }),
    // fixed-width circles packed with small gaps (widths in storefront.css), not stretched across the row
    categories: () => ({
        slidesPerView: 'auto', spaceBetween: 10, freeMode: true, bleed: true, watchOverflow: true,
        breakpoints: { 1024: { spaceBetween: 16 } },
    }),
    promo: ({ slides }) => ({
        slidesPerView: 1.15, spaceBetween: 12,
        breakpoints: { 768: { slidesPerView: 2.1 }, 1024: { slidesPerView: 3, spaceBetween: 20, ...(slides <= 3 ? { enabled: false } : {}) } },
    }),
    rail: () => ({
        slidesPerView: 2.3, spaceBetween: 12, freeMode: true, watchOverflow: true, bleed: true,
        breakpoints: { 768: { slidesPerView: 3.3 }, 1024: { slidesPerView: 4, spaceBetween: 20 }, 1280: { slidesPerView: 5, spaceBetween: 20 } },
        pagination: { type: 'progressbar' },
    }),
    brands: () => ({
        slidesPerView: 2.4, spaceBetween: 12, freeMode: true, watchOverflow: true,
        breakpoints: { 768: { slidesPerView: 4.4 }, 1024: { slidesPerView: 6, spaceBetween: 16 }, 1280: { slidesPerView: 7, spaceBetween: 16 } },
    }),
    packaging: () => ({ slidesPerView: 2.2, spaceBetween: 10, watchOverflow: true, breakpoints: { 768: { slidesPerView: 3 } } }),
    reviews: () => ({ slidesPerView: 1.1, spaceBetween: 12, watchOverflow: true, breakpoints: { 768: { slidesPerView: 2 }, 1024: { slidesPerView: 3, spaceBetween: 20 } } }),
    // blog cards ("more from the blog" under an article): 1.15 / 2.2 / 3 per view, bleeding to the screen edge on phones
    posts: () => ({
        slidesPerView: 1.15, spaceBetween: 12, watchOverflow: true, bleed: true,
        breakpoints: { 768: { slidesPerView: 2.2 }, 1024: { slidesPerView: 3, spaceBetween: 20 } },
        pagination: { type: 'progressbar' },
    }),
    chips: () => ({ slidesPerView: 'auto', spaceBetween: 8, freeMode: true, watchOverflow: true }),
    gallery: () => ({ slidesPerView: 1, spaceBetween: 0, rewind: true, pagination: { type: 'fraction' } }), // rewind: the arrows wrap around, as the old gallery did
    galleryThumbs: () => ({
        slidesPerView: 5.2, spaceBetween: 8, watchSlidesProgress: true, freeMode: true, watchOverflow: true,
        breakpoints: { 1024: { direction: 'vertical', slidesPerView: 5, spaceBetween: 12 } },
    }),
};
presets.flash = presets.rail;

const own = (root, sel) => [...root.querySelectorAll(sel)].find((el) => el.closest('[data-swiper]') === root) || null;

function build(root, name, container, extra = {}, controls = true) {
    const slides = container.querySelectorAll(':scope > .swiper-wrapper > .swiper-slide').length;
    const preset = (presets[name] || presets.rail)({ root, slides });
    let custom = {};
    try { custom = root.dataset.swiperOptions ? JSON.parse(root.dataset.swiperOptions) : {}; } catch (e) { /* ignore bad JSON */ }
    const { bleed, ...opts } = { ...preset, ...custom, ...extra };
    const prevEl = controls ? own(root, '[data-swiper-prev]') : null, nextEl = controls ? own(root, '[data-swiper-next]') : null, pagEl = controls ? own(root, '[data-swiper-pagination]') : null;
    const reduce = reducedMotion();

    const o = {
        modules: [A11y, Keyboard, Navigation, Pagination, FreeMode, Autoplay, Thumbs, Manipulation], // Manipulation: the gallery appends a variant slide
        ...opts,
        // RTL: Swiper reads the computed `direction` of the container, which follows <html dir> (or a closer dir attribute)
        speed: reduce ? 0 : (opts.speed ?? 400),
        watchOverflow: opts.watchOverflow ?? true,
        // off until the pointer or focus is inside this slider (scopeKeyboard), so the arrow keys never move every slider on screen
        keyboard: { enabled: false, onlyInViewport: true },
        a11y: { prevSlideMessage: prevEl?.getAttribute('aria-label') || label('prev', 'Previous'), nextSlideMessage: nextEl?.getAttribute('aria-label') || label('next', 'Next') },
        navigation: prevEl || nextEl ? { prevEl, nextEl, lockClass: 'swiper-button-lock', disabledClass: 'swiper-button-disabled' } : false,
        pagination: pagEl && opts.pagination ? { el: pagEl, ...opts.pagination } : false,
        autoplay: opts.autoplay && !reduce && slides > 1 ? opts.autoplay : false,
    };
    if (opts.loop && slides < 2) o.loop = false;
    if (bleed) {
        // mobile/tablet: run to the screen edge with 16px offsets; desktop: inside the content width
        o.slidesOffsetBefore = 16; o.slidesOffsetAfter = 16;
        o.breakpoints = Object.fromEntries(Object.entries(o.breakpoints || {}).map(([bp, v]) => [bp, Number(bp) >= 1024 ? { slidesOffsetBefore: 0, slidesOffsetAfter: 0, ...v } : v]));
        o.breakpoints[1024] ??= { slidesOffsetBefore: 0, slidesOffsetAfter: 0 };
    }
    return o;
}

/**
 * Keyboard arrows drive only the slider the visitor is in: on while the pointer is over the root or focus is inside it, off otherwise
 * and only one slider at a time (Swiper's keyboard module listens on the whole document, so with it always on one key press moved every visible slider).
 */
let keyOwner = null; // the one slider that currently takes the arrow keys (the last one entered by pointer or focus)
function scopeKeyboard(root, swiper) {
    if (!swiper.keyboard) return;
    const has = () => root.contains(document.activeElement) && document.activeElement !== document.body;
    const on = () => {
        if (swiper.destroyed) return;
        if (keyOwner && keyOwner !== swiper && !keyOwner.destroyed) keyOwner.keyboard.disable();
        keyOwner = swiper;
        swiper.keyboard.enable();
    };
    const off = () => {
        if (swiper.destroyed || root.matches(':hover') || has()) return;
        swiper.keyboard.disable();
        if (keyOwner === swiper) keyOwner = null;
    };
    root.addEventListener('pointerenter', on);
    root.addEventListener('pointerleave', () => setTimeout(off));
    root.addEventListener('focusin', on);
    root.addEventListener('focusout', (e) => { if (!root.contains(e.relatedTarget)) setTimeout(off); });
    if (root.matches(':hover') || has()) on();
}

function autoplayToggle(root, swiper) {
    const btn = own(root, '[data-swiper-toggle]');
    if (!btn) return;
    if (!swiper.autoplay?.running) { btn.hidden = true; return; }
    const sync = () => {
        const on = swiper.autoplay.running && !swiper.autoplay.paused;
        btn.setAttribute('aria-label', on ? btn.dataset.labelPause || label('pause_slides', 'Pause') : btn.dataset.labelPlay || label('play_slides', 'Play'));
        btn.setAttribute('aria-pressed', on ? 'false' : 'true');
        btn.dataset.state = on ? 'playing' : 'paused';
    };
    btn.addEventListener('click', () => { if (swiper.autoplay.running) swiper.autoplay.stop(); else swiper.autoplay.start(); sync(); });
    swiper.on('autoplayStart autoplayStop autoplayPause autoplayResume', sync);
    sync();
}

/** Gallery: main + thumbs, the active slide's video plays muted inline (same behaviour as the retired product-gallery.js). */
function initGallery(root) {
    const mainEl = root.querySelector('[data-swiper-main]');
    const thumbsEl = root.querySelector('[data-swiper-thumbs]');
    if (!mainEl) return null;
    const thumbs = thumbsEl ? new Swiper(thumbsEl, build(root, 'galleryThumbs', thumbsEl, {}, false)) : null;
    const swiper = new Swiper(mainEl, build(root, 'gallery', mainEl, thumbs ? { thumbs: { swiper: thumbs } } : {}));
    scopeKeyboard(root, swiper);

    const videoOf = (i) => swiper.slides[i]?.querySelector('video') || null;
    const showPlay = (v, on) => v.closest('.swiper-slide')?.querySelector('[data-gallery-play]')?.classList.toggle('hidden', !on);
    const ownPause = new WeakSet(), userPaused = new WeakSet();
    let inView = !('IntersectionObserver' in window);
    const stop = (v, rewind) => {
        if (!v.paused) { ownPause.add(v); v.pause(); }
        if (rewind) { try { v.currentTime = 0; } catch (e) { /* metadata not loaded yet */ } }
    };
    const playActive = () => {
        const v = videoOf(swiper.activeIndex);
        if (!v || !inView || document.hidden || userPaused.has(v)) return;
        if (reducedMotion()) { if (v.paused) showPlay(v, true); return; }
        v.muted = true; v.playsInline = true; v.preload = 'metadata';
        const p = v.play();
        if (p) p.then(() => showPlay(v, false)).catch((e) => { if (e.name !== 'AbortError') showPlay(v, true); });
    };
    swiper.slides.forEach((s) => {
        const v = s.querySelector('video');
        if (!v) return;
        v.addEventListener('pause', () => { if (ownPause.has(v)) ownPause.delete(v); else userPaused.add(v); });
        v.addEventListener('playing', () => { userPaused.delete(v); showPlay(v, false); });
        s.querySelector('[data-gallery-play]')?.addEventListener('click', () => { userPaused.delete(v); showPlay(v, false); v.play()?.catch(() => showPlay(v, true)); });
    });
    swiper.on('slideChange', () => {
        const prev = videoOf(swiper.previousIndex);
        if (prev) { stop(prev, true); userPaused.delete(prev); showPlay(prev, false); }
        playActive();
    });
    const sync = () => { const v = videoOf(swiper.activeIndex); if (!v) return; if (inView && !document.hidden) playActive(); else stop(v, false); };
    document.addEventListener('visibilitychange', sync);
    if ('IntersectionObserver' in window) new IntersectionObserver(([e]) => { inView = e.isIntersecting; sync(); }, { threshold: 0.5 }).observe(mainEl);
    // thumbs are real buttons (keyboard + screen readers); Swiper's own thumb tap handles pointer clicks
    root.querySelectorAll('[data-gallery-thumb]').forEach((b) => b.addEventListener('click', () => swiper.slideTo(Number(b.dataset.galleryThumb))));
    const markThumb = () => root.querySelectorAll('[data-gallery-thumb]').forEach((b) => { if (Number(b.dataset.galleryThumb) === swiper.activeIndex) b.setAttribute('aria-current', 'true'); else b.removeAttribute('aria-current'); });
    swiper.on('slideChange', markThumb);
    markThumb();
    playActive();

    const slideList = () => [...swiper.slides];
    root.gallery = {
        swiper,
        /** Variant picked: `initial` = during page load, `explicit` = asked for by the URL (?variant=). */
        showVariantImage(url, { initial = false, explicit = false } = {}) {
            const slides = slideList();
            if (!url || url === slides.find((s) => s.dataset.type === 'image')?.dataset.url) return;
            if (initial && !explicit && slides[0]?.dataset.type === 'video') return; // an automatic preselection must not take over a video-first gallery
            const speed = initial ? 0 : undefined;
            let variant = slides.findIndex((s) => s.dataset.type === 'variant');
            const i = slides.findIndex((s) => s.dataset.type === 'image' && s.dataset.url === url);
            if (i >= 0) {
                if (variant >= 0) swiper.removeSlide(variant);
                swiper.slideTo(i, speed);
                return;
            }
            if (variant < 0) {
                const alt = mainEl.querySelector('img')?.alt || '';
                const el = document.createElement('div');
                el.className = 'swiper-slide';
                el.dataset.gallerySlide = ''; el.dataset.type = 'variant';
                el.innerHTML = '<img alt="" class="aspect-square size-full object-cover" decoding="async">';
                el.querySelector('img').alt = alt;
                swiper.appendSlide(el);
                variant = swiper.slides.length - 1;
            }
            const slide = swiper.slides[variant];
            slide.dataset.url = url;
            slide.querySelector('img').src = url;
            swiper.slideTo(variant, speed);
        },
    };
    return swiper;
}

export function initSliders(scope = document) {
    scope.querySelectorAll('[data-swiper]:not([data-swiper-ready])').forEach((root) => {
        const name = root.dataset.swiper || 'rail';
        let swiper;
        if (name === 'gallery') {
            swiper = initGallery(root);
        } else {
            const container = root.classList.contains('swiper') ? root : own(root, '.swiper') || root.querySelector('.swiper');
            if (!container) return;
            swiper = new Swiper(container, build(root, name, container));
            scopeKeyboard(root, swiper);
            autoplayToggle(root, swiper);
        }
        if (!swiper) return;
        root.dataset.swiperReady = '1';
        root.swiper = swiper;
        root.dispatchEvent(new CustomEvent('swiper:ready', { detail: { swiper }, bubbles: true }));
    });
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => initSliders());
else initSliders();
document.addEventListener('sliders:refresh', (e) => initSliders(e.target instanceof Element ? e.target : document));
