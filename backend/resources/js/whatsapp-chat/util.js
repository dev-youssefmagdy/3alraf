// Small DOM + formatting helpers. Remote content is only ever inserted via textContent / createTextNode.
export const root = document.getElementById('wa-chat');
const i18n = JSON.parse(root.dataset.i18n || '{}');
export const locale = root.dataset.locale || 'en';

export const t = (key, vars = {}) => {
    let s = i18n[key] ?? key;
    for (const [k, v] of Object.entries(vars)) s = s.replaceAll(':' + k, String(v));
    return s;
};

/** h('div', {class:'x', onclick:fn, dataset:{a:1}}, 'text', node) - strings become text nodes (never HTML). */
export function h(tag, attrs = {}, ...kids) {
    const el = document.createElement(tag);
    for (const [k, v] of Object.entries(attrs || {})) {
        if (v === null || v === undefined || v === false) continue;
        if (k === 'class') el.className = v;
        else if (k === 'dataset') Object.assign(el.dataset, v);
        else if (k.startsWith('on') && typeof v === 'function') el.addEventListener(k.slice(2), v);
        else el.setAttribute(k, v === true ? '' : v);
    }
    for (const kid of kids.flat()) {
        if (kid === null || kid === undefined || kid === false) continue;
        el.append(kid instanceof Node ? kid : document.createTextNode(String(kid)));
    }
    return el;
}

export const $ = (id) => document.getElementById(id);

const timeFmt = new Intl.DateTimeFormat(locale, { hour: '2-digit', minute: '2-digit' });
const dateFmt = new Intl.DateTimeFormat(locale, { year: 'numeric', month: 'short', day: 'numeric' });
const shortFmt = new Intl.DateTimeFormat(locale, { month: 'short', day: 'numeric' });
export const fmtTime = (iso) => (iso ? timeFmt.format(new Date(iso)) : '');
const dayKey = (d) => d.getFullYear() * 400 + d.getMonth() * 32 + d.getDate();
export const dayOf = (iso) => (iso ? dayKey(new Date(iso)) : 0);
export function dayLabel(iso) {
    const d = new Date(iso), now = new Date(), y = new Date(Date.now() - 864e5);
    if (dayKey(d) === dayKey(now)) return t('today');
    if (dayKey(d) === dayKey(y)) return t('yesterday');
    return dateFmt.format(d);
}
export function listTime(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    return dayKey(d) === dayKey(new Date()) ? timeFmt.format(d) : shortFmt.format(d);
}
export const fmtSize = (n) => (n >= 1048576 ? (n / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(n / 1024)) + ' KB');
export const fmtDur = (s) => `${Math.floor(s / 60)}:${String(Math.floor(s % 60)).padStart(2, '0')}`;

const URL_RE = /\bhttps?:\/\/[^\s<>"'`]+/gi;
/** Safe linkify: only http(s) URLs parsed by URL(); everything else stays a text node. */
export function linkify(text) {
    const out = [];
    let last = 0;
    for (const m of String(text).matchAll(URL_RE)) {
        let url = m[0];
        const trail = url.match(/[.,;:!?)\]}]+$/);
        if (trail) url = url.slice(0, -trail[0].length);
        let safe = null;
        try { const u = new URL(url); if (u.protocol === 'http:' || u.protocol === 'https:') safe = u.href; } catch { /* not a URL */ }
        if (!safe) continue;
        if (m.index > last) out.push(document.createTextNode(text.slice(last, m.index)));
        out.push(h('a', { href: safe, target: '_blank', rel: 'noopener noreferrer nofollow', class: 'text-sky-700 underline break-all' }, url));
        last = m.index + url.length;
    }
    if (last < text.length) out.push(document.createTextNode(text.slice(last)));
    return out;
}

export const safeHttp = (u) => { try { const x = new URL(u); return x.protocol === 'http:' || x.protocol === 'https:' ? x.href : null; } catch { return null; } };
export const initial = (s) => (Array.from(String(s || '?').replace(/^\+/, ''))[0] || '?').toUpperCase();
