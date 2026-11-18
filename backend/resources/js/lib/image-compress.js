/**
 * Shrinks picked images in the browser before upload, so a few 15 MB phone photos don't make a huge request.
 * The server re-encodes everything to small AVIF (max ~1600px) anyway, so 2400px WebP at q0.9 loses nothing.
 * Runs on `change` of image file inputs inside ajax forms and swaps the input's files in place;
 * ajax-form awaits `pendingCompression()` before building its FormData.
 * Leaves the original whenever compression fails, isn't supported, or doesn't make it smaller.
 */
import $ from 'jquery';

const MAX_SIDE = 2400;
const QUALITY = 0.9;
const SKIP_BELOW = 1024 * 1024; // already-small files with small dimensions are left alone
const pending = new Set();

const toBlob = (canvas, type, q) => new Promise((res) => canvas.toBlob(res, type, q));

async function shrink(file) {
    if (!/^image\/(jpeg|png|webp|bmp)$/.test(file.type) || typeof createImageBitmap !== 'function') return file;
    let bmp;
    try {
        bmp = await createImageBitmap(file, { imageOrientation: 'from-image' });
    } catch {
        return file;
    }
    const scale = Math.min(1, MAX_SIDE / Math.max(bmp.width, bmp.height));
    if (scale === 1 && file.size < SKIP_BELOW) { bmp.close?.(); return file; }

    const canvas = document.createElement('canvas');
    canvas.width = Math.round(bmp.width * scale);
    canvas.height = Math.round(bmp.height * scale);
    const ctx = canvas.getContext('2d');
    ctx.imageSmoothingQuality = 'high';
    ctx.drawImage(bmp, 0, 0, canvas.width, canvas.height);
    bmp.close?.();

    let blob = await toBlob(canvas, 'image/webp', QUALITY);
    // Browsers without WebP encoding hand back PNG; JPEG is fine only when the source had no transparency.
    if (!blob || blob.type !== 'image/webp') blob = file.type === 'image/jpeg' ? await toBlob(canvas, 'image/jpeg', QUALITY) : null;
    if (!blob || blob.size >= file.size) return file;

    const ext = blob.type === 'image/webp' ? 'webp' : 'jpg';
    return new File([blob], file.name.replace(/\.[^.]+$/, '') + '.' + ext, { type: blob.type, lastModified: file.lastModified });
}

async function compressInput(input) {
    const files = Array.from(input.files || []);
    if (!files.length || typeof DataTransfer !== 'function') return;
    const out = await Promise.all(files.map((f) => shrink(f).catch(() => f)));
    if (out.every((f, i) => f === files[i])) return;
    const dt = new DataTransfer();
    out.forEach((f) => dt.items.add(f));
    input.files = dt.files;
}

/** Resolves once every in-flight compression has finished. */
export const pendingCompression = () => Promise.all(Array.from(pending));

/** The smaller version of one image, or the original when it can't be improved. */
export const shrinkImage = (file) => shrink(file).catch(() => file);

/** Makes ajax-form wait for this work before it builds its FormData. */
export function trackCompression(work) {
    const p = work.catch(() => {}).finally(() => pending.delete(p));
    pending.add(p);
    return p;
}

$(document).on('change', 'form[data-ajax-form] input[type=file]', function () {
    if (this.dataset.noCompress !== undefined || !(this.accept || '').includes('image')) return;
    trackCompression(compressInput(this));
});
