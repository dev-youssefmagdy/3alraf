/**
 * Drag-to-scroll for horizontal rows that must not become a Swiper (e.g. the desktop category bar, whose mega-menu panels are
 * positioned outside the row and would be clipped by a transformed slider). Usage: <ul data-drag-scroll class="overflow-x-auto …">.
 *   - mouse / pen: press and drag sideways to scroll; a drag never fires the link click underneath (touch keeps native scrolling)
 *   - mouse wheel: vertical wheel scrolls the row sideways while it can still move, then lets the page scroll
 *   - sets data-scroll-start / data-scroll-end while content is hidden on that side (hooks for styling)
 * Works in RTL (scrollLeft runs 0 → negative there; the same delta maths applies).
 */
const THRESHOLD = 6; // px of movement before a press becomes a drag

function edges(el) {
    const max = el.scrollWidth - el.clientWidth;
    const pos = Math.abs(el.scrollLeft); // RTL scrollLeft is ≤ 0
    el.toggleAttribute('data-scroll-start', pos > 1);
    el.toggleAttribute('data-scroll-end', max - pos > 1);
}

function bind(el) {
    if (el.dataset.dragScrollReady) return;
    el.dataset.dragScrollReady = '1';
    let startX = 0, startLeft = 0, pressed = false, dragged = false, pointerId = null;

    el.addEventListener('pointerdown', (e) => {
        if (e.pointerType === 'touch' || e.button !== 0 || el.scrollWidth <= el.clientWidth) return;
        pressed = true; dragged = false; pointerId = e.pointerId;
        startX = e.clientX; startLeft = el.scrollLeft;
    });
    el.addEventListener('pointermove', (e) => {
        if (!pressed || e.pointerId !== pointerId) return;
        const dx = e.clientX - startX;
        if (!dragged && Math.abs(dx) < THRESHOLD) return;
        if (!dragged) { dragged = true; el.classList.add('is-dragging'); el.setPointerCapture(pointerId); }
        el.scrollLeft = startLeft - dx;
        e.preventDefault();
    });
    const end = () => {
        if (!pressed) return;
        pressed = false;
        if (pointerId !== null && el.hasPointerCapture?.(pointerId)) el.releasePointerCapture(pointerId);
        pointerId = null;
        // keep .is-dragging until the click that follows this release has been swallowed
        setTimeout(() => { el.classList.remove('is-dragging'); dragged = false; }, 0);
    };
    el.addEventListener('pointerup', end);
    el.addEventListener('pointercancel', end);
    el.addEventListener('click', (e) => { if (dragged) { e.preventDefault(); e.stopPropagation(); } }, true);
    el.addEventListener('dragstart', (e) => e.preventDefault()); // no native link/image ghost drag

    el.addEventListener('wheel', (e) => {
        if (Math.abs(e.deltaY) <= Math.abs(e.deltaX) || el.scrollWidth <= el.clientWidth) return;
        const before = el.scrollLeft;
        el.scrollLeft += (document.documentElement.dir === 'rtl' ? -1 : 1) * e.deltaY;
        if (el.scrollLeft !== before) e.preventDefault(); // at either end the page scrolls as usual
    }, { passive: false });

    el.addEventListener('scroll', () => edges(el), { passive: true });
    new ResizeObserver(() => edges(el)).observe(el);
    edges(el);
}

export function initDragScroll(root = document) {
    root.querySelectorAll('[data-drag-scroll]').forEach(bind);
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => initDragScroll());
else initDragScroll();
