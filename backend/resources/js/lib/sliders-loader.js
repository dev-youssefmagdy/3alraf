/**
 * Loads Swiper (./sliders) without competing with first paint. storefront.css already lays every slider out before Swiper starts,
 * so rows look right without it. A product gallery is the exception: the product page code uses its API at once, so it loads first.
 * Elsewhere it loads once the page has loaded, on the first interaction if that comes sooner, or when a row is added (sliders:refresh).
 */
let started = false;
const load = () => {
    if (started) return null;
    started = true;
    return import('./sliders');
};

if (document.querySelector('[data-swiper="gallery"]')) {
    await load();
} else {
    document.addEventListener('sliders:refresh', load, { once: true });
    if (document.querySelector('[data-swiper]')) {
        const idle = () => setTimeout(() => (window.requestIdleCallback ? requestIdleCallback(load, { timeout: 1000 }) : load()), 300);
        ['pointerdown', 'keydown', 'wheel'].forEach((type) => addEventListener(type, load, { once: true, passive: true }));
        if (document.readyState === 'complete') idle();
        else addEventListener('load', idle, { once: true });
        setTimeout(load, 5000); // a stalled image must not leave the sliders inactive
    }
}
