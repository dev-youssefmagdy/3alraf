import { h, t, linkify, fmtTime, fmtSize, fmtDur, safeHttp, root } from './util.js';
import { mediaUrl } from './api.js';

const canMedia = root.dataset.canMedia === '1';
const ME = 'me';

export const QUICK_REACTIONS = ['👍', '❤️', '😂', '😮', '😢', '🙏'];

const TICK = {
    pending: ['🕓', 'text-slate-400', 'status_pending'],
    sent: ['✓', 'text-slate-400', 'status_sent'],
    delivered: ['✓✓', 'text-slate-400', 'status_delivered'],
    read: ['✓✓', 'text-sky-500', 'status_read'],
    failed: ['!', 'text-red-600', 'failed'],
};

/** Local object URL of an optimistic upload, or the authorized media URL once the server has it. */
function srcOf(m) {
    if (m._localUrl) return m._localUrl;
    if (m.media && m.media.status === 'ready' && canMedia) return mediaUrl(m.media.id);
    return null;
}
const thumbOf = (m) => (m.media && canMedia ? mediaUrl(m.media.id, '?thumb=1') : null);

function note(text, cls = '') {
    return h('div', { class: 'rounded-lg bg-black/5 px-3 py-2 text-xs text-slate-500 ' + cls }, text);
}

function mediaPlaceholder(m) {
    if (!canMedia) return note(t('media_locked'));
    const st = m.media?.status;
    if (st === 'failed' || st === 'expired') return note(t('media_unavailable'));
    return note(t('media_pending'));
}

function sizeStyle(m) {
    const w = m.media?.width, hh = m.media?.height;
    return w && hh ? `aspect-ratio:${w}/${hh}` : '';
}

function renderImage(m, sticker) {
    const src = srcOf(m);
    const thumb = thumbOf(m);
    if (!src && !thumb) return mediaPlaceholder(m);
    const img = h('img', {
        src: src || thumb, alt: '', loading: 'lazy', decoding: 'async', style: sticker ? '' : sizeStyle(m),
        class: sticker ? 'size-32 object-contain' : 'max-h-80 w-full max-w-xs cursor-zoom-in rounded-lg object-cover' + (src ? '' : ' blur-[1px]'),
        dataset: src && !sticker ? { lightbox: 'image', src } : {},
    });
    img.addEventListener('error', () => { if (thumb && img.src !== location.origin + thumb && img.dataset.fallback !== '1') { img.dataset.fallback = '1'; img.src = thumb; } });
    return img;
}

function renderVideo(m) {
    const src = srcOf(m);
    if (!src) {
        const thumb = thumbOf(m);
        return thumb ? h('div', { class: 'relative' }, h('img', { src: thumb, alt: '', class: 'max-h-80 w-full max-w-xs rounded-lg object-cover opacity-80' }), h('div', { class: 'absolute inset-0 grid place-items-center text-3xl text-white drop-shadow' }, '▶')) : mediaPlaceholder(m);
    }
    const poster = thumbOf(m);
    return h('video', { src, controls: true, preload: 'metadata', playsinline: true, poster: poster || null, class: 'max-h-80 w-full max-w-xs rounded-lg bg-black', style: sizeStyle(m) });
}

function bars(seed, n = 32) {
    let s = (Number(seed) || 7) * 9301 + 49297;
    return Array.from({ length: n }, () => { s = (s * 9301 + 49297) % 233280; return 18 + Math.round((s / 233280) * 82); });
}

function renderAudio(m, voice) {
    const src = srcOf(m);
    if (!src) return mediaPlaceholder(m);
    if (!voice) return h('audio', { src, controls: true, preload: 'none', class: 'w-64 max-w-full' });
    const audio = h('audio', { src, preload: 'none' });
    const heights = bars(m.media?.id || m.id);
    const barEls = heights.map((p) => h('span', { class: 'w-0.5 rounded bg-slate-300', style: `height:${p}%` }));
    const play = h('button', { type: 'button', class: 'grid size-9 shrink-0 place-items-center rounded-full bg-emerald-600 text-white', 'aria-label': t('record') }, '▶');
    const time = h('span', { class: 'w-10 shrink-0 text-[11px] text-slate-500', dir: 'ltr' }, m.media?.duration ? fmtDur(m.media.duration) : '');
    const wave = h('div', { class: 'flex h-8 flex-1 cursor-pointer items-center gap-px', dir: 'ltr' }, barEls);
    play.addEventListener('click', () => (audio.paused ? audio.play() : audio.pause()));
    audio.addEventListener('play', () => { play.textContent = '❚❚'; });
    audio.addEventListener('pause', () => { play.textContent = '▶'; });
    audio.addEventListener('ended', () => { play.textContent = '▶'; barEls.forEach((b) => b.classList.replace('bg-emerald-500', 'bg-slate-300')); });
    audio.addEventListener('timeupdate', () => {
        const p = audio.duration ? audio.currentTime / audio.duration : 0;
        barEls.forEach((b, i) => { const on = i / barEls.length <= p; b.classList.toggle('bg-emerald-500', on); b.classList.toggle('bg-slate-300', !on); });
        time.textContent = fmtDur(audio.currentTime);
    });
    audio.addEventListener('loadedmetadata', () => { if (Number.isFinite(audio.duration) && !m.media?.duration) time.textContent = fmtDur(audio.duration); });
    wave.addEventListener('click', (e) => {
        const r = wave.getBoundingClientRect();
        if (audio.duration) audio.currentTime = audio.duration * Math.min(1, Math.max(0, (e.clientX - r.left) / r.width));
    });
    return h('div', { class: 'flex w-64 max-w-full items-center gap-2' }, play, wave, time, audio);
}

function renderDocument(m) {
    const name = m.media?.name || m._localName || t('document');
    const href = m.media && m.media.status === 'ready' && canMedia ? mediaUrl(m.media.id, '?download=1') : null;
    return h('div', { class: 'flex w-64 max-w-full items-center gap-3 rounded-lg bg-black/5 p-2.5' },
        h('div', { class: 'grid size-10 shrink-0 place-items-center rounded bg-white text-lg' }, '📄'),
        h('div', { class: 'min-w-0 flex-1' }, h('div', { class: 'truncate text-sm font-medium', dir: 'auto', title: name }, name), h('div', { class: 'text-xs text-slate-500' }, m.media?.size ? fmtSize(m.media.size) : (m.media?.mime || ''))),
        href ? h('a', { href, class: 'rounded-full p-2 hover:bg-black/10', title: t('download'), 'aria-label': t('download'), rel: 'noopener' }, '⬇') : null);
}

function mapLink(lat, lng) {
    const a = Number(lat), b = Number(lng);
    if (!Number.isFinite(a) || !Number.isFinite(b) || Math.abs(a) > 90 || Math.abs(b) > 180) return null;
    return `https://www.openstreetmap.org/?mlat=${a}&mlon=${b}#map=16/${a}/${b}`;
}

function renderLocation(m) {
    const p = m.payload || {};
    const href = mapLink(p.lat, p.lng);
    return h('div', { class: 'w-60 max-w-full space-y-1 rounded-lg bg-black/5 p-3' },
        h('div', { class: 'text-sm font-semibold' }, '📍 ', m.type === 'live_location' ? t('live_location') : (p.name || t('location'))),
        p.address ? h('div', { class: 'text-xs text-slate-600', dir: 'auto' }, p.address) : null,
        h('div', { class: 'text-xs text-slate-500', dir: 'ltr' }, `${Number(p.lat).toFixed(5)}, ${Number(p.lng).toFixed(5)}`),
        href ? h('a', { href, target: '_blank', rel: 'noopener noreferrer', class: 'text-xs text-sky-700 underline' }, t('open_map')) : null);
}

/** Pull a display name + phone out of a vCard string (text only). */
export function parseVcard(v) {
    const out = { name: null, phones: [] };
    if (typeof v !== 'string') return out;
    for (const line of v.split(/\r?\n/)) {
        if (/^FN[:;]/i.test(line)) out.name = line.slice(line.indexOf(':') + 1).trim();
        else if (/^(item\d+\.)?TEL/i.test(line)) { const n = line.slice(line.lastIndexOf(':') + 1).trim(); if (n) out.phones.push(n); }
    }
    return out;
}

function renderContacts(m) {
    const list = (m.payload?.contacts || []).slice(0, 10);
    return h('div', { class: 'w-60 max-w-full space-y-1' }, list.map((c) => {
        const v = parseVcard(c.vcard);
        const name = c.name || v.name || t('contact');
        const phones = c.phone ? [c.phone] : v.phones;
        return h('div', { class: 'flex items-center gap-3 rounded-lg bg-black/5 p-2.5' }, h('div', { class: 'grid size-10 shrink-0 place-items-center rounded-full bg-white' }, '👤'),
            h('div', { class: 'min-w-0' }, h('div', { class: 'truncate text-sm font-medium', dir: 'auto' }, name), phones.map((p) => h('div', { class: 'text-xs text-slate-500', dir: 'ltr' }, p))));
    }));
}

function renderPoll(m) {
    const opts = m.payload?.options || [];
    return h('div', { class: 'w-64 max-w-full space-y-1.5' }, h('div', { class: 'text-sm font-semibold', dir: 'auto' }, '📊 ', m.body || t('poll')),
        h('div', { class: 'text-xs text-slate-500' }, t('votes_hint', { n: m.payload?.selectable_count || 1 })),
        opts.map((o) => h('div', { class: 'rounded-md border border-slate-300/70 bg-white/60 px-2.5 py-1.5 text-sm', dir: 'auto' }, '○ ', o)));
}

function renderLinkPreview(link) {
    const href = safeHttp(link?.url);
    if (!href) return null;
    const thumb = typeof link.thumbnail_b64 === 'string' && /^[A-Za-z0-9+/=]+$/.test(link.thumbnail_b64) && link.thumbnail_b64.length < 200000 ? 'data:image/jpeg;base64,' + link.thumbnail_b64 : null;
    return h('a', { href, target: '_blank', rel: 'noopener noreferrer nofollow', class: 'mb-1 block overflow-hidden rounded-lg bg-black/5 no-underline' },
        thumb ? h('img', { src: thumb, alt: '', class: 'max-h-40 w-full object-cover' }) : null,
        h('div', { class: 'p-2' }, link.title ? h('div', { class: 'line-clamp-2 text-sm font-semibold text-slate-800', dir: 'auto' }, link.title) : null,
            link.description ? h('div', { class: 'line-clamp-2 text-xs text-slate-600', dir: 'auto' }, link.description) : null,
            h('div', { class: 'truncate text-[11px] text-slate-500', dir: 'ltr' }, new URL(href).host)));
}

function renderReply(m) {
    const r = m.reply_to;
    if (!m.reply_to_id) return null;
    const who = r ? (r.direction === 'out' ? t('you') : null) : null;
    const text = r ? (r.text || '…') : '…';
    return h('div', { class: 'mb-1 cursor-pointer rounded border-s-4 border-emerald-500 bg-black/5 px-2 py-1 text-xs', dataset: { jump: m.reply_to_id } },
        who ? h('div', { class: 'font-semibold text-emerald-700' }, who) : null, h('div', { class: 'line-clamp-2 text-slate-600', dir: 'auto' }, text));
}

function body(m) {
    if (m.deleted_at) return h('div', { class: 'italic text-slate-500' }, '🚫 ' + t('deleted'));
    const caption = (txt) => (txt ? h('div', { class: 'mt-1 whitespace-pre-wrap break-words', dir: 'auto' }, linkify(txt)) : null);
    switch (m.type) {
        case 'text': return [m.payload?.link ? renderLinkPreview(m.payload.link) : null, h('div', { class: 'whitespace-pre-wrap break-words', dir: 'auto' }, linkify(m.body || ''))];
        case 'image': return [renderImage(m, false), caption(m.caption)];
        case 'sticker': return renderImage(m, true);
        case 'video': return [renderVideo(m), caption(m.caption)];
        case 'audio': return renderAudio(m, false);
        case 'ptt': return renderAudio(m, true);
        case 'document': return [renderDocument(m), caption(m.caption)];
        case 'location': case 'live_location': return [renderLocation(m), caption(m.caption)];
        case 'contact': return renderContacts(m);
        case 'poll': return renderPoll(m);
        default: return h('div', { class: 'italic text-slate-500' }, t('unsupported'));
    }
}

function reactionsRow(m) {
    if (!m.reactions?.length || m.deleted_at) return null;
    const g = new Map();
    for (const r of m.reactions) { const e = g.get(r.emoji) || { n: 0, mine: false }; e.n++; e.mine ||= r.reactor_jid === ME; g.set(r.emoji, e); }
    return h('div', { class: 'absolute -bottom-3 end-2 flex gap-1' }, [...g].map(([emo, v]) => h('span', { class: 'rounded-full border bg-white px-1.5 text-xs shadow-sm ' + (v.mine ? 'border-emerald-400' : 'border-slate-200') }, emo, v.n > 1 ? ' ' + v.n : '')));
}

/** One message row. `m` is the Phase 3 message JSON (plus optional client-only _tmp/_localUrl/_localName). */
export function renderMessage(m, { canSend, canEdit }) {
    const out = m.direction === 'out';
    const sticker = m.type === 'sticker' && !m.deleted_at;
    const tick = TICK[m.status] || TICK.sent;
    const failed = m.status === 'failed';
    const hasReactions = !!(m.reactions?.length && !m.deleted_at);
    const menu = (canSend && !m.deleted_at && m.status !== 'pending' && !m._tmp) || failed || (m._tmp && failed)
        ? h('button', { type: 'button', class: 'wa-menu-btn absolute top-0.5 end-1 hidden rounded-full bg-white/80 px-1.5 text-slate-500 group-hover:block', dataset: { act: 'menu' }, 'aria-label': t('more') }, '⌄') : null;

    const bubble = h('div', {
        class: 'group relative max-w-[85%] rounded-lg px-2.5 py-1.5 text-sm shadow-sm md:max-w-[70%] ' + (sticker ? 'bg-transparent shadow-none ' : out ? 'bg-emerald-100 ' : 'bg-white ') + (hasReactions ? 'mb-3' : ''),
    },
    menu, renderReply(m), body(m),
    h('div', { class: 'mt-0.5 flex items-center justify-end gap-1 text-[10px] text-slate-500' },
        m.edited_at && !m.deleted_at ? h('span', {}, t('edited')) : null,
        h('span', { dir: 'ltr' }, fmtTime(m.wa_timestamp || m._createdAt)),
        out ? h('span', { class: tick[1] + ' font-bold', title: t(tick[2]) }, tick[0]) : null),
    failed ? h('div', { class: 'mt-1 flex items-center gap-2 text-xs text-red-600' }, h('span', {}, m.error ? m.error : t('failed')), canSend ? h('button', { type: 'button', class: 'font-semibold underline', dataset: { act: 'resend' } }, t('retry')) : null) : null,
    reactionsRow(m));

    return h('div', { class: 'wa-msg flex ' + (out ? 'justify-end' : 'justify-start'), dataset: { mid: m.id, key: m._tmp || '' } }, bubble);
}
