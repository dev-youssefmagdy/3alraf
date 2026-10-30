import { root } from './util.js';

const base = root.dataset.base;
const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';

export const mediaUrl = (id, extra = '') => `${base}/media/${id}${extra}`;

/** Resolves {ok,status,data}; never throws on HTTP errors (only network failures reject). */
export async function api(method, path, body) {
    const headers = { Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' };
    let payload;
    if (body instanceof FormData) payload = body;
    else if (body !== undefined) { headers['Content-Type'] = 'application/json'; payload = JSON.stringify(body); }
    const res = await fetch(base + path, { method, headers, body: payload, credentials: 'same-origin' });
    let data = null;
    try { data = await res.json(); } catch { /* empty body */ }
    return { ok: res.ok, status: res.status, data };
}

export const errMsg = (r, fallback) => r?.data?.message || (r?.data?.errors && Object.values(r.data.errors)[0]?.[0]) || fallback;
