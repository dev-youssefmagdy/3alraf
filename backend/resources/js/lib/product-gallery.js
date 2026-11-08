/**
 * Product page slider mixing images and videos (P8). Native CSS scroll-snap does the swiping (touch, RTL, native video controls need no special handling).
 * The active video plays muted + inline; everything else is paused. When autoplay is refused, or the visitor prefers reduced motion, a play button replaces it.
 * Off-screen / hidden-tab pauses keep the position and resume; leaving the slide rewinds it. A video the visitor paused stays paused until the slide is left.
 */
export function initProductGallery(root) {
    const track = root.querySelector('[data-gallery-track]');
    const slides = [...root.querySelectorAll('[data-gallery-slide]')];
    const thumbs = [...root.querySelectorAll('[data-gallery-thumb]')];
    const thumbRow = root.querySelector('[data-scroll-row]');
    const variantSlide = slides.find((s) => s.dataset.type === 'variant') || null;
    const rtl = () => getComputedStyle(track).direction === 'rtl';
    const reducedMotion = () => matchMedia('(prefers-reduced-motion: reduce)').matches;
    const videoAt = (i) => slides[i]?.querySelector('video') || null;
    const count = () => slides.filter((s) => !s.hidden).length;

    let active = -1;
    let inView = !('IntersectionObserver' in window);
    let target = null, targetUntil = 0, settleTimer; // destination of a programmatic scroll, so its intermediate positions are ignored
    const ownPause = new WeakSet();
    const userPaused = new WeakSet();

    const showPlay = (v, on) => v.closest('[data-gallery-slide]').querySelector('[data-gallery-play]')?.classList.toggle('hidden', !on);

    function stop(v, rewind) {
        if (!v.paused) { ownPause.add(v); v.pause(); }
        if (rewind) { try { v.currentTime = 0; } catch (e) { /* metadata not loaded yet */ } }
    }

    function playActive() {
        const v = videoAt(active);
        if (!v || !inView || document.hidden || userPaused.has(v)) return;
        if (reducedMotion()) { if (v.paused) showPlay(v, true); return; }
        const p = v.play();
        if (p) p.then(() => showPlay(v, false)).catch((e) => { if (e.name !== 'AbortError') showPlay(v, true); });
    }

    function markThumb(i) {
        thumbs.forEach((t, k) => {
            const on = k === i;
            t.classList.toggle('border-brand-600', on);
            t.classList.toggle('border-transparent', !on);
            if (on) t.setAttribute('aria-current', 'true'); else t.removeAttribute('aria-current');
        });
        const t = thumbs[i];
        if (t && thumbRow && thumbRow.scrollWidth > thumbRow.clientWidth) {
            const r = thumbRow.getBoundingClientRect(), b = t.getBoundingClientRect();
            if (b.left < r.left) thumbRow.scrollBy({ left: b.left - r.left, behavior: 'auto' });
            else if (b.right > r.right) thumbRow.scrollBy({ left: b.right - r.right, behavior: 'auto' });
        }
    }

    function setActive(i) {
        if (i === active) return;
        const prev = videoAt(active);
        if (prev) { stop(prev, true); userPaused.delete(prev); showPlay(prev, false); }
        active = i;
        markThumb(i);
        const v = videoAt(i);
        if (v) {
            v.preload = 'metadata';
            v.muted = true; // autoplay is only allowed silently; the controls can unmute
            playActive();
        }
    }

    function go(k, smooth = true) {
        const n = count();
        const i = ((k % n) + n) % n;
        target = i;
        targetUntil = Date.now() + 1200;
        track.scrollTo({ left: (rtl() ? -1 : 1) * i * track.clientWidth, behavior: smooth && !reducedMotion() ? 'smooth' : 'auto' });
        setActive(i);
    }

    function settle() {
        const w = track.clientWidth || 1;
        const left = Math.abs(track.scrollLeft);
        if (target !== null) {
            if (Math.abs(left - target * w) > 2 && Date.now() < targetUntil) { settleTimer = setTimeout(settle, 120); return; }
            target = null;
        }
        setActive(Math.min(Math.round(left / w), count() - 1));
    }

    track.addEventListener('scroll', () => { clearTimeout(settleTimer); settleTimer = setTimeout(settle, 90); }, { passive: true });
    track.addEventListener('pointerdown', () => { target = null; }); // a swipe overrides a running programmatic scroll
    window.addEventListener('resize', () => track.scrollTo({ left: (rtl() ? -1 : 1) * Math.max(active, 0) * track.clientWidth, behavior: 'auto' }));

    root.querySelector('[data-gallery-next]')?.addEventListener('click', () => go(active + 1));
    root.querySelector('[data-gallery-prev]')?.addEventListener('click', () => go(active - 1));
    thumbs.forEach((t) => t.addEventListener('click', () => go(Number(t.dataset.galleryThumb))));
    track.addEventListener('keydown', (e) => {
        if (e.target.tagName === 'VIDEO' || (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft')) return;
        e.preventDefault();
        go(active + ((e.key === 'ArrowRight') === rtl() ? -1 : 1));
    });

    slides.forEach((s) => {
        const v = s.querySelector('video');
        if (!v) return;
        v.addEventListener('pause', () => { if (ownPause.has(v)) ownPause.delete(v); else userPaused.add(v); });
        v.addEventListener('playing', () => { userPaused.delete(v); showPlay(v, false); });
        s.querySelector('[data-gallery-play]')?.addEventListener('click', () => {
            userPaused.delete(v);
            showPlay(v, false);
            const p = v.play();
            if (p) p.catch(() => showPlay(v, true));
        });
    });

    // pause while the slider is off-screen or the tab is hidden; carry on when it is back
    const sync = () => {
        const v = videoAt(active);
        if (!v) return;
        if (inView && !document.hidden) playActive(); else stop(v, false);
    };
    document.addEventListener('visibilitychange', sync);
    if ('IntersectionObserver' in window) new IntersectionObserver(([e]) => { inView = e.isIntersecting; sync(); }, { threshold: 0.5 }).observe(track);

    setActive(0);

    return {
        /** Variant picked: `initial` = during page load, `explicit` = asked for by the URL (?variant=). A variant without its own image leaves the slider where it is. */
        showVariantImage(url, { initial = false, explicit = false } = {}) {
            if (!url || url === slides.find((s) => s.dataset.type === 'image')?.dataset.url) return;
            if (initial && !explicit && slides[0].dataset.type === 'video') return; // an automatic preselection must not take over a video-first gallery
            const i = slides.findIndex((s) => s.dataset.type === 'image' && s.dataset.url === url);
            if (i >= 0) {
                if (variantSlide) variantSlide.hidden = true;
                go(i, !initial);
            } else if (variantSlide) {
                variantSlide.querySelector('img').src = url;
                variantSlide.hidden = false;
                go(slides.indexOf(variantSlide), !initial);
            }
        },
    };
}
