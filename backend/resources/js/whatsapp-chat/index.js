import { root, $, h, t, initial, listTime, dayOf, dayLabel, fmtDur } from './util.js';
import { api, errMsg } from './api.js';
import { renderMessage, QUICK_REACTIONS } from './render.js';

const D = root.dataset;
const can = { send: D.canSend === '1', manage: D.canManage === '1', media: D.canMedia === '1' };
const admins = JSON.parse(D.admins || '[]');
const RANK = { pending: 0, sent: 1, delivered: 2, read: 3, failed: 0 };
const toast = (type, msg) => (window.toastr ? window.toastr[type](msg) : console.warn(msg));

const S = {
    convs: new Map(), active: null, msgs: [], hasBefore: false, filter: 'all', q: '', connected: D.connected === '1',
    limits: null, files: [], replyTo: null, tmpSeq: 0, loadingOlder: false, typingTimer: null, wsDown: false,
};

const el = {
    list: $('wa-convs'), empty: $('wa-convs-empty'), msgs: $('wa-msgs'), active: $('wa-active'), placeholder: $('wa-placeholder'), thread: $('wa-thread'), listPane: $('wa-list'),
    input: $('wa-input'), typing: $('wa-typing'), disc: $('wa-disc'),
};

/* ---------------- conversation list ---------------- */
const title = (c) => c.name || (c.phone ? '+' + c.phone : c.jid.split('@')[0]);
const sortedConvs = () => [...S.convs.values()].sort((a, b) => (b.is_pinned - a.is_pinned) || String(b.last_message_at || '').localeCompare(String(a.last_message_at || '')) || b.id - a.id);

function visible(c) {
    if (S.filter === 'archived') return c.is_archived;
    if (c.is_archived) return false;
    if (S.filter === 'unread') return c.unread_count > 0;
    if (S.filter === 'mine') return c.assigned_admin_id == D.me && c.assigned_guard === D.guard;
    return true;
}

function renderList() {
    el.list.replaceChildren();
    let n = 0;
    for (const c of sortedConvs()) {
        if (!visible(c)) continue;
        n++;
        el.list.append(h('li', { dataset: { cid: c.id }, class: 'flex cursor-pointer items-center gap-3 px-3 py-2.5 hover:bg-slate-50 ' + (S.active === c.id ? 'bg-emerald-50' : '') },
            h('div', { class: 'grid size-11 shrink-0 place-items-center rounded-full bg-emerald-100 font-bold text-emerald-700' }, initial(title(c))),
            h('div', { class: 'min-w-0 flex-1' },
                h('div', { class: 'flex items-baseline gap-2' }, h('span', { class: 'flex-1 truncate text-sm font-semibold text-slate-800', dir: 'auto' }, title(c)), h('span', { class: 'shrink-0 text-[11px] ' + (c.unread_count ? 'font-semibold text-emerald-600' : 'text-slate-400'), dir: 'ltr' }, listTime(c.last_message_at))),
                h('div', { class: 'flex items-center gap-2' }, h('span', { class: 'flex-1 truncate text-xs text-slate-500', dir: 'auto' }, c.last_message_preview || ''),
                    c.is_pinned ? h('span', { class: 'text-xs' }, '📌') : null,
                    c.unread_count ? h('span', { class: 'min-w-5 rounded-full bg-emerald-600 px-1.5 text-center text-[11px] font-bold leading-5 text-white' }, c.unread_count > 99 ? '99+' : c.unread_count) : null))));
    }
    el.empty.classList.toggle('hidden', n > 0);
    el.empty.textContent = S.q || S.filter !== 'all' ? t('no_results') : t('no_chats');
    const total = [...S.convs.values()].reduce((s, c) => s + (c.is_archived ? 0 : c.unread_count || 0), 0);
    const b = $('wa-unread-total');
    b.textContent = total;
    b.classList.toggle('hidden', !total);
}

function upsertConv(c) {
    const old = S.convs.get(c.id) || {};
    const merged = { ...old, ...c }; // broadcasts omit assigned_*/customer_id: keep what we know
    S.convs.set(c.id, merged);
    if (S.active === c.id) renderHeader();
    return merged;
}

async function loadConvs() {
    const p = new URLSearchParams({ limit: '100' });
    if (S.q) p.set('q', S.q);
    if (S.filter === 'archived') p.set('archived', '1');
    if (S.filter === 'unread') p.set('unread', '1');
    if (S.filter === 'mine') p.set('mine', '1');
    const r = await api('GET', '/conversations?' + p).catch(() => null);
    if (!r?.ok) return;
    S.convs.clear();
    r.data.data.forEach((c) => S.convs.set(c.id, c));
    renderList();
}

/* ---------------- header ---------------- */
function renderHeader() {
    const c = S.convs.get(S.active);
    if (!c) return;
    $('wa-h-avatar').textContent = initial(title(c));
    $('wa-h-name').textContent = title(c);
    $('wa-h-sub').textContent = c.phone ? '+' + c.phone : '';
    const cu = $('wa-h-customer');
    if (c.customer_id && D.customerUrl) { cu.href = D.customerUrl.replace('__ID__', c.customer_id); cu.textContent = t('customer') + ' #' + c.customer_id; cu.title = t('view_customer'); cu.classList.remove('hidden'); } else cu.classList.add('hidden');
    const as = $('wa-h-assigned');
    const a = c.assigned_admin_id && c.assigned_guard === D.guard ? admins.find((x) => x.id == c.assigned_admin_id) : null;
    as.textContent = a ? t('assigned_to', { name: a.name }) : '';
    as.classList.toggle('hidden', !a);
    if (can.manage) {
        $('wa-pin').textContent = c.is_pinned ? t('unpin') : t('pin');
        $('wa-archive').textContent = c.is_archived ? t('unarchive') : t('archive');
        const sel = $('wa-assign');
        if (!sel.options.length) {
            sel.append(h('option', { value: '' }, t('unassigned')), ...admins.map((x) => h('option', { value: x.id }, x.name)));
        }
        sel.value = a ? String(a.id) : '';
    }
}

/* ---------------- thread ---------------- */
const lastRealId = () => S.msgs.reduce((m, x) => (typeof x.id === 'number' && x.id > m ? x.id : m), 0);

function renderThread(keepBottom = true) {
    const box = el.msgs;
    const nearBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 120;
    box.replaceChildren();
    if (S.hasBefore) box.append(h('button', { type: 'button', class: 'mx-auto my-1 rounded-full bg-white/80 px-3 py-1 text-xs text-slate-600', dataset: { act: 'older' } }, t('load_older')));
    let day = 0;
    const ctx = { canSend: can.send };
    for (const m of S.msgs) {
        const d = dayOf(m.wa_timestamp || m._createdAt);
        if (d !== day) { day = d; box.append(h('div', { class: 'mx-auto my-2 rounded-full bg-white/80 px-3 py-0.5 text-xs text-slate-500' }, dayLabel(m.wa_timestamp || m._createdAt))); }
        box.append(renderMessage(m, ctx));
    }
    if (keepBottom && nearBottom) box.scrollTop = box.scrollHeight;
}

function upsertMsg(m) {
    if (m.conversation_id !== S.active) return false;
    const i = S.msgs.findIndex((x) => x.id === m.id);
    if (i >= 0) {
        const o = S.msgs[i];
        if (o.status && m.status && RANK[m.status] < RANK[o.status] && m.status !== 'failed') m = { ...m, status: o.status };
        S.msgs[i] = { ...o, ...m, _localUrl: o._localUrl && !m.media ? o._localUrl : undefined };
    } else {
        S.msgs.push(m);
        S.msgs.sort((a, b) => (typeof a.id === 'number' && typeof b.id === 'number' ? a.id - b.id : 0));
    }
    return true;
}

async function openConv(id, focus = true) {
    S.active = id;
    S.msgs = [];
    S.replyTo = null;
    clearReply();
    clearTray();
    el.placeholder.classList.add('hidden');
    el.active.classList.remove('hidden'); el.active.classList.add('flex');
    el.thread.classList.remove('hidden'); el.thread.classList.add('flex');
    el.listPane.classList.add('hidden', 'md:flex');
    el.typing.classList.add('hidden');
    renderList(); renderHeader();
    el.msgs.replaceChildren(h('div', { class: 'm-auto text-sm text-slate-500' }, t('loading')));
    const r = await api('GET', `/conversations/${id}/messages?limit=40`).catch(() => null);
    if (S.active !== id) return;
    if (!r?.ok) { el.msgs.replaceChildren(h('div', { class: 'm-auto text-sm text-red-600' }, errMsg(r, t('action_error')))); return; }
    S.msgs = r.data.data; S.hasBefore = r.data.has_more_before;
    renderThread(false);
    el.msgs.scrollTop = el.msgs.scrollHeight;
    markRead();
    if (focus && el.input) el.input.focus();
}

function closeConv() {
    S.active = null;
    el.active.classList.add('hidden'); el.active.classList.remove('flex');
    el.placeholder.classList.remove('hidden');
    el.thread.classList.add('hidden'); el.thread.classList.remove('flex');
    el.listPane.classList.remove('hidden', 'md:flex'); el.listPane.classList.add('flex');
    renderList();
}

async function loadOlder() {
    if (S.loadingOlder || !S.hasBefore || !S.msgs.length) return;
    S.loadingOlder = true;
    const id = S.active, first = S.msgs.find((m) => typeof m.id === 'number');
    const r = await api('GET', `/conversations/${id}/messages?limit=40&before=${first.id}`).catch(() => null);
    S.loadingOlder = false;
    if (!r?.ok || S.active !== id) return;
    const prevH = el.msgs.scrollHeight;
    S.msgs = [...r.data.data, ...S.msgs]; S.hasBefore = r.data.has_more_before;
    renderThread(false);
    el.msgs.scrollTop = el.msgs.scrollHeight - prevH;
}

let readTimer = null;
function markRead() {
    clearTimeout(readTimer);
    readTimer = setTimeout(async () => {
        const c = S.convs.get(S.active);
        if (!c || !c.unread_count || document.hidden) return;
        const r = await api('POST', `/conversations/${c.id}/read`).catch(() => null);
        if (r?.ok) { upsertConv(r.data.data); renderList(); }
    }, 300);
}
document.addEventListener('visibilitychange', () => { if (!document.hidden) markRead(); });

/* gap-fill after a reconnect (or on the polling fallback): everything newer than the last real id */
async function gapFill() {
    await loadConvs();
    if (!S.active) return;
    const id = S.active;
    for (let guard = 0; guard < 10; guard++) {
        const r = await api('GET', `/conversations/${id}/messages?limit=100&after=${lastRealId()}`).catch(() => null);
        if (!r?.ok || S.active !== id) return;
        r.data.data.forEach(upsertMsg);
        if (!r.data.has_more_after || !r.data.data.length) break;
    }
    renderThread(); markRead();
}

/* ---------------- sending ---------------- */
function setConnected(v) {
    S.connected = v;
    el.disc.classList.toggle('hidden', v);
    $('wa-composer')?.classList.toggle('opacity-60', !v);
}

function addTemp(partial, send) {
    const key = 'tmp' + ++S.tmpSeq;
    const m = { id: key, _tmp: key, conversation_id: S.active, direction: 'out', status: 'pending', reactions: [], payload: null, body: null, caption: null, media: null, reply_to_id: S.replyTo?.id || null, reply_to: S.replyTo ? { id: S.replyTo.id, direction: S.replyTo.direction, type: S.replyTo.type, text: S.replyTo.body || S.replyTo.caption || '' } : null, _createdAt: new Date().toISOString(), ...partial };
    m._send = send;
    S.msgs.push(m);
    renderThread(false); el.msgs.scrollTop = el.msgs.scrollHeight;
    runTemp(m);
}

async function runTemp(m) {
    m.status = 'pending'; m.error = null;
    const r = await m._send().catch(() => null);
    const i = S.msgs.findIndex((x) => x._tmp === m._tmp);
    if (i < 0) return;
    if (r?.ok) {
        const real = r.data.data;
        const dup = S.msgs.findIndex((x) => x.id === real.id);
        if (dup >= 0) S.msgs.splice(i, 1); else S.msgs[i] = real;
        S.msgs.sort((a, b) => (typeof a.id === 'number' && typeof b.id === 'number' ? a.id - b.id : 0));
        if (m._localUrl) setTimeout(() => URL.revokeObjectURL(m._localUrl), 60000);
        const c = S.convs.get(real.conversation_id); if (c) { upsertConv({ ...c, last_message_id: real.id }); }
    } else {
        m.status = 'failed';
        m.error = r ? errMsg(r, t('sent_error')) : t('sent_error');
        if (r?.data?.code === 'not_connected') setConnected(false);
    }
    renderThread();
}

function sendText() {
    const text = el.input.value.trim();
    if (!can.send || !S.active) return;
    if (S.files.length) return sendFiles(text);
    if (!text) return;
    const cid = S.active, reply = S.replyTo?.id;
    el.input.value = ''; autoGrow(); presence('paused');
    addTemp({ type: 'text', body: text }, () => api('POST', `/conversations/${cid}/messages`, { type: 'text', text, reply_to_id: reply || undefined }));
    clearReply();
}

function sendFiles(caption) {
    const cid = S.active, reply = S.replyTo?.id;
    const files = S.files.slice();
    clearTray(); clearReply(); el.input.value = ''; autoGrow();
    files.forEach((f, idx) => {
        const type = f._type;
        const cap = idx === 0 && caption && ['image', 'video', 'document'].includes(type) ? caption : '';
        const url = ['image', 'video', 'audio', 'ptt'].includes(type) ? URL.createObjectURL(f) : null;
        addTemp({ type, caption: cap || null, _localUrl: url, _localName: f.name, media: { id: 0, mime: f.type, size: f.size, name: f.name, status: 'ready' }, },
            () => { const fd = new FormData(); fd.append('type', type); fd.append('file', f, f.name); if (cap) fd.append('caption', cap); if (reply) fd.append('reply_to_id', reply); return api('POST', `/conversations/${cid}/messages`, fd); });
    });
}

function pickType(f) {
    const mimes = S.limits?.mimes || {};
    const mime = (f.type || '').split(';')[0];
    if (mime.startsWith('image/') && (mimes.image || []).includes(mime)) return 'image';
    if (mime.startsWith('video/') && (mimes.video || []).includes(mime) && !mime.includes('webm')) return 'video';
    if (mime.startsWith('audio/') && (mimes.audio || []).includes(mime)) return 'audio';
    return 'document';
}

function addFiles(list) {
    if (!can.send || !S.active) return;
    for (const f of list) {
        f._type = f._type || pickType(f);
        const cap = S.limits?.limits?.[f._type];
        if (cap && f.size > cap) { toast('error', t('file_too_large', { name: f.name, mb: Math.round(cap / 1048576) })); continue; }
        S.files.push(f);
    }
    renderTray();
}
function renderTray() {
    const tray = $('wa-tray');
    tray.replaceChildren(...S.files.map((f, i) => h('div', { class: 'flex items-center gap-2 rounded-lg border border-slate-200 bg-white p-1.5 text-xs' },
        f._type === 'image' ? h('img', { src: URL.createObjectURL(f), alt: '', class: 'size-10 rounded object-cover' }) : h('span', { class: 'text-lg' }, f._type === 'document' ? '📄' : '🎞'),
        h('span', { class: 'max-w-32 truncate', dir: 'auto' }, f.name), h('button', { type: 'button', class: 'text-red-600', dataset: { rmfile: i }, 'aria-label': t('cancel') }, '×'))));
    tray.classList.toggle('hidden', !S.files.length); tray.classList.toggle('flex', !!S.files.length);
    el.input.placeholder = S.files.length ? t('caption') : t('type_message');
}
function clearTray() { S.files = []; if (can.send) renderTray(); }

function clearReply() { S.replyTo = null; const r = $('wa-reply'); if (r) { r.classList.add('hidden'); r.classList.remove('flex'); r.replaceChildren(); } }
function setReply(m) {
    S.replyTo = m;
    const r = $('wa-reply');
    r.replaceChildren(h('div', { class: 'min-w-0 flex-1' }, h('div', { class: 'font-semibold text-emerald-700' }, t('replying_to'), ' ', m.direction === 'out' ? t('you') : title(S.convs.get(S.active))), h('div', { class: 'truncate text-slate-600', dir: 'auto' }, m.body || m.caption || t('unsupported'))),
        h('button', { type: 'button', class: 'text-slate-500', onclick: clearReply, 'aria-label': t('cancel') }, '×'));
    r.classList.remove('hidden'); r.classList.add('flex'); el.input.focus();
}

/* ---------------- typing presence (outgoing) ---------------- */
let lastComposing = 0, idleTimer = null;
function presence(state) { if (S.active && S.connected) api('POST', `/conversations/${S.active}/presence`, { state }).catch(() => {}); }
function onType() {
    autoGrow();
    const now = Date.now();
    if (now - lastComposing > 5000) { lastComposing = now; presence('composing'); }
    clearTimeout(idleTimer); idleTimer = setTimeout(() => { lastComposing = 0; presence('paused'); }, 3000);
}
function autoGrow() { el.input.style.height = 'auto'; el.input.style.height = Math.min(el.input.scrollHeight, 128) + 'px'; }

/* ---------------- message actions ---------------- */
let pop = null;
function closePop() { pop?.remove(); pop = null; }
document.addEventListener('click', (e) => { if (pop && !pop.contains(e.target)) closePop(); if (!e.target.closest('#wa-emoji-pop,#wa-emoji-btn')) $('wa-emoji-pop')?.classList.add('hidden'); if (!e.target.closest('#wa-attach-pop,#wa-attach-btn')) $('wa-attach-pop')?.classList.add('hidden'); }, true);

function openMenu(btn, m) {
    closePop();
    const own = m.direction === 'out';
    const items = [];
    if (m.status === 'failed' && !m._tmp) items.push([t('resend'), () => resend(m)]);
    if (!m._tmp && m.status !== 'failed') {
        items.push([t('reply'), () => setReply(m)]);
        if (own && m.type === 'text' && m.wa_timestamp && Date.now() - new Date(m.wa_timestamp) < 15 * 60e3) items.push([t('edit'), () => editMsg(m)]);
        if (m.body) items.push([t('copy'), () => navigator.clipboard?.writeText(m.body)]);
        if (own) items.push([t('delete_everyone'), () => delMsg(m)]);
    }
    const quick = !m._tmp && m.status !== 'failed' ? h('div', { class: 'flex gap-1 border-b border-slate-100 px-2 pb-1.5' }, QUICK_REACTIONS.map((e) => h('button', { type: 'button', class: 'rounded px-1 text-lg hover:bg-slate-100', onclick: () => { closePop(); react(m, e); } }, e))) : null;
    pop = h('div', { class: 'fixed z-50 min-w-44 rounded-lg border border-slate-200 bg-white py-1.5 text-sm shadow-lg' }, quick, items.map(([label, fn]) => h('button', { type: 'button', class: 'block w-full px-3 py-1.5 text-start hover:bg-slate-50', onclick: () => { closePop(); fn(); } }, label)));
    document.body.append(pop);
    const r = btn.getBoundingClientRect();
    pop.style.top = Math.min(r.bottom + 4, window.innerHeight - pop.offsetHeight - 8) + 'px';
    pop.style.left = Math.max(8, Math.min(r.left, window.innerWidth - pop.offsetWidth - 8)) + 'px';
}

async function react(m, emoji) {
    const mine = (m.reactions || []).find((r) => r.reactor_jid === 'me');
    const r = await api('POST', `/messages/${m.id}/react`, { emoji: mine && mine.emoji === emoji ? '' : emoji }).catch(() => null);
    if (r?.ok) { upsertMsg(r.data.data); renderThread(); } else toast('error', errMsg(r, t('action_error')));
}
async function delMsg(m) {
    if (!window.confirm(t('delete_confirm'))) return;
    const r = await api('DELETE', `/messages/${m.id}`).catch(() => null);
    if (r?.ok) { upsertMsg(r.data.data); renderThread(); } else toast('error', errMsg(r, t('action_error')));
}
async function resend(m) {
    const r = await api('POST', `/messages/${m.id}/resend`).catch(() => null);
    if (r?.ok) { upsertMsg(r.data.data); renderThread(); } else toast('error', errMsg(r, t('action_error')));
}
function editMsg(m) {
    dialog(t('edit'), t('save'), [h('textarea', { name: 'text', rows: 4, class: 'form-control', dir: 'auto', maxlength: 4096 }, m.body || '')], async (f, err) => {
        const text = f.text.value.trim();
        if (!text) return err(t('type_message'));
        const r = await api('PUT', `/messages/${m.id}`, { text }).catch(() => null);
        if (!r?.ok) return err(errMsg(r, t('action_error')));
        upsertMsg(r.data.data); renderThread(); return true;
    });
}

/* ---------------- dialogs ---------------- */
function dialog(titleText, okText, fields, onSubmit) {
    $('wa-dialog-title').textContent = titleText;
    $('wa-dialog-ok').textContent = okText;
    $('wa-dialog-body').replaceChildren(...fields);
    $('wa-dialog-err').classList.add('hidden');
    const dlg = $('wa-dialog');
    dlg.classList.remove('hidden'); dlg.classList.add('flex');
    const form = $('wa-dialog-form');
    const close = () => { dlg.classList.add('hidden'); dlg.classList.remove('flex'); form.onsubmit = null; };
    $('wa-dialog-cancel').onclick = close;
    const err = (msg) => { const e = $('wa-dialog-err'); e.textContent = msg; e.classList.remove('hidden'); };
    form.onsubmit = async (ev) => {
        ev.preventDefault();
        $('wa-dialog-ok').disabled = true;
        const done = await onSubmit(form, err);
        $('wa-dialog-ok').disabled = false;
        if (done) close();
    };
    fields[0]?.focus?.();
}
const field = (label, input) => h('label', { class: 'block text-xs font-medium text-slate-600' }, label, h('div', { class: 'mt-1' }, input));
const COUNTRIES = [['EG', '+20'], ['SA', '+966'], ['AE', '+971'], ['KW', '+965'], ['QA', '+974'], ['BH', '+973'], ['OM', '+968'], ['JO', '+962'], ['LB', '+961'], ['IQ', '+964'], ['LY', '+218'], ['MA', '+212'], ['DZ', '+213'], ['TN', '+216'], ['SD', '+249'], ['PS', '+970'], ['TR', '+90'], ['GB', '+44'], ['US', '+1']];

function newChat() {
    dialog(t('new_chat_title'), t('start'), [
        field(t('phone'), h('div', { class: 'flex gap-2', dir: 'ltr' }, h('select', { name: 'country', class: 'form-control !w-28' }, COUNTRIES.map(([c, d]) => h('option', { value: c }, `${c} ${d}`))), h('input', { name: 'phone', type: 'tel', class: 'form-control', inputmode: 'tel', autocomplete: 'off' }))),
    ], async (f, err) => {
        const phone = f.phone.value.trim();
        if (!phone) return err(t('invalid_phone'));
        const r = await api('POST', '/conversations', { country: f.country.value, phone }).catch(() => null);
        if (!r?.ok) return err(errMsg(r, t('invalid_phone')));
        upsertConv(r.data.data); renderList(); openConv(r.data.data.id); return true;
    });
}

function shareLocation() {
    const lat = h('input', { name: 'lat', class: 'form-control', inputmode: 'decimal', dir: 'ltr' }), lng = h('input', { name: 'lng', class: 'form-control', inputmode: 'decimal', dir: 'ltr' });
    dialog(t('location_title'), t('send'), [
        field(t('latitude'), lat), field(t('longitude'), lng), field(t('place_name'), h('input', { name: 'name', class: 'form-control', dir: 'auto', maxlength: 100 })),
        h('button', { type: 'button', class: 'text-xs text-sky-700 underline', onclick: () => navigator.geolocation?.getCurrentPosition((p) => { lat.value = p.coords.latitude.toFixed(6); lng.value = p.coords.longitude.toFixed(6); }, () => toast('error', t('action_error'))) }, t('use_my_location')),
    ], async (f, err) => {
        const a = Number(f.lat.value), b = Number(f.lng.value);
        if (!f.lat.value || !f.lng.value || !Number.isFinite(a) || !Number.isFinite(b) || Math.abs(a) > 90 || Math.abs(b) > 180) return err(t('latitude') + ' / ' + t('longitude'));
        const cid = S.active, name = f.name.value.trim();
        addTemp({ type: 'location', payload: { lat: a, lng: b, name: name || null } }, () => api('POST', `/conversations/${cid}/messages`, { type: 'location', location: { lat: a, lng: b, name: name || undefined } }));
        return true;
    });
}

function shareContact() {
    dialog(t('contact_title'), t('send'), [
        field(t('contact_name'), h('input', { name: 'cname', class: 'form-control', dir: 'auto', maxlength: 100 })),
        field(t('contact_phone'), h('input', { name: 'cphone', class: 'form-control', dir: 'ltr', inputmode: 'tel' })),
    ], async (f, err) => {
        const name = f.cname.value.trim(), phone = f.cphone.value.replace(/[^\d]/g, '');
        if (!name || phone.length < 6) return err(t('contact_phone'));
        const cid = S.active;
        addTemp({ type: 'contact', payload: { contacts: [{ name, phone }] } }, () => api('POST', `/conversations/${cid}/messages`, { type: 'contact', contacts: [{ name, phone }] }));
        return true;
    });
}

/* ---------------- voice note recorder ---------------- */
const rec = { mr: null, chunks: [], stream: null, started: 0, tick: null, cancelled: false };
async function startRec() {
    if (!navigator.mediaDevices?.getUserMedia || !window.MediaRecorder) return toast('error', t('recording_unsupported'));
    try { rec.stream = await navigator.mediaDevices.getUserMedia({ audio: true }); } catch { return toast('error', t('mic_denied')); }
    const mime = ['audio/ogg;codecs=opus', 'audio/webm;codecs=opus', 'audio/webm', 'audio/mp4'].find((m) => MediaRecorder.isTypeSupported(m));
    rec.mr = new MediaRecorder(rec.stream, mime ? { mimeType: mime } : undefined);
    rec.chunks = []; rec.cancelled = false;
    rec.mr.ondataavailable = (e) => e.data.size && rec.chunks.push(e.data);
    rec.mr.onstop = onRecStop;
    rec.mr.start();
    rec.started = Date.now();
    $('wa-compose-row').classList.add('hidden'); $('wa-recbar').classList.remove('hidden'); $('wa-recbar').classList.add('flex');
    rec.tick = setInterval(() => { $('wa-rec-time').textContent = fmtDur((Date.now() - rec.started) / 1000); }, 250);
    presence('recording');
}
function endRecUi() {
    clearInterval(rec.tick); rec.stream?.getTracks().forEach((x) => x.stop());
    $('wa-compose-row').classList.remove('hidden'); $('wa-recbar').classList.add('hidden'); $('wa-recbar').classList.remove('flex');
    presence('paused');
}
function onRecStop() {
    endRecUi();
    if (rec.cancelled || !rec.chunks.length) return;
    const type = (rec.mr.mimeType || 'audio/webm').split(';')[0];
    const ext = type.includes('ogg') ? 'ogg' : type.includes('mp4') ? 'm4a' : 'webm';
    const f = new File(rec.chunks, `voice-${Date.now()}.${ext}`, { type });
    f._type = 'ptt';
    S.files = [f];
    sendFiles('');
}

/* ---------------- lightbox ---------------- */
function lightbox(src) {
    const box = $('wa-lightbox');
    const close = () => { box.classList.add('hidden'); box.classList.remove('flex'); box.replaceChildren(); document.removeEventListener('keydown', esc); };
    const esc = (e) => e.key === 'Escape' && close();
    box.replaceChildren(h('button', { type: 'button', class: 'absolute top-3 end-4 text-3xl text-white', onclick: close, 'aria-label': t('close') }, '×'), h('img', { src, alt: '', class: 'max-h-full max-w-full object-contain' }));
    box.onclick = (e) => e.target === box && close();
    box.classList.remove('hidden'); box.classList.add('flex'); document.addEventListener('keydown', esc);
}

/* ---------------- emoji ---------------- */
const EMOJI = '😀 😁 😂 🤣 😊 😍 😘 😎 🤔 😅 😉 🙂 😢 😭 😡 😮 😴 🤗 🙏 👍 👎 👏 🙌 💪 🤝 👌 ✌️ 👋 ❤️ 💔 🔥 ✨ 🎉 🎁 ✅ ❌ ⭐ 💯 📦 🚚 🛒 💳 💰 📞 📍 ⏰ 🌹 ☕ 😇 🥳 😬 🤷 🤦'.split(' ');
function buildEmoji() {
    const p = $('wa-emoji-pop');
    if (!p) return;
    p.append(h('div', { class: 'grid grid-cols-8 gap-1' }, EMOJI.map((e) => h('button', { type: 'button', class: 'rounded p-1 text-xl hover:bg-slate-100', onclick: () => { const i = el.input; const s = i.selectionStart ?? i.value.length; i.setRangeText(e, s, i.selectionEnd ?? s, 'end'); i.focus(); onType(); } }, e))));
}

/* ---------------- realtime ---------------- */
function showTyping(state) {
    clearTimeout(S.typingTimer);
    if (state === 'composing' || state === 'recording') {
        el.typing.textContent = state === 'recording' ? t('recording_now') : t('typing');
        el.typing.classList.remove('hidden');
        S.typingTimer = setTimeout(() => el.typing.classList.add('hidden'), 7000);
    } else el.typing.classList.add('hidden');
}

function onReceived({ message, conversation }) {
    const c = upsertConv(conversation);
    const active = S.active === message.conversation_id;
    if (active) {
        // an optimistic row of ours may still be waiting for its POST response: the response handler dedupes by real id
        const added = upsertMsg(message);
        if (added) renderThread();
        if (message.direction === 'in') markRead();
    }
    if (!active && message.direction === 'in' && c.is_archived && S.filter !== 'archived') c.is_archived = false;
    renderList();
}
function onUpdated({ message }) { if (upsertMsg(message)) renderThread(false); }
function onConvUpdated({ conversation }) { upsertConv(conversation); renderList(); }

function startRealtime() {
    let pollTimer = null;
    const startPoll = (ms) => { clearInterval(pollTimer); pollTimer = setInterval(gapFill, ms); };
    if (!D.wsKey) { startPoll(15000); return; }
    Promise.all([import('laravel-echo'), import('pusher-js')]).then(([{ default: Echo }, { default: Pusher }]) => {
        window.Pusher = Pusher;
        const echo = new Echo({
            broadcaster: 'reverb', key: D.wsKey, wsHost: D.wsHost, wsPort: D.wsPort, wssPort: D.wsPort, forceTLS: D.wsScheme === 'https',
            enabledTransports: ['ws', 'wss'], authEndpoint: D.authUrl, auth: { headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' } },
        });
        echo.private(D.channel)
            .listen('.chat.message.received', onReceived).listen('.chat.message.updated', onUpdated)
            .listen('.chat.conversation.updated', onConvUpdated)
            .listen('.chat.presence', (p) => { const c = S.convs.get(S.active); if (c && p.jid === c.jid) showTyping(p.state); });
        const conn = echo.connector.pusher.connection;
        let was = false;
        conn.bind('connected', () => { $('wa-offline').classList.add('hidden'); clearInterval(pollTimer); if (was && S.wsDown) gapFill(); S.wsDown = false; was = true; });
        const down = () => { S.wsDown = true; $('wa-offline').classList.remove('hidden'); startPoll(20000); };
        conn.bind('disconnected', down); conn.bind('unavailable', down); conn.bind('failed', down);
    }).catch(() => startPoll(15000));
}

/* ---------------- wiring ---------------- */
el.list.addEventListener('click', (e) => { const li = e.target.closest('li[data-cid]'); if (li) openConv(Number(li.dataset.cid)); });
$('wa-back').addEventListener('click', closeConv);
let searchTimer;
$('wa-search').addEventListener('input', (e) => { clearTimeout(searchTimer); searchTimer = setTimeout(() => { S.q = e.target.value.trim(); loadConvs(); }, 300); });
document.querySelectorAll('#wa-filters [data-filter]').forEach((b) => b.addEventListener('click', () => { S.filter = b.dataset.filter; syncFilters(); loadConvs(); }));
function syncFilters() { document.querySelectorAll('#wa-filters [data-filter]').forEach((b) => b.classList.toggle('on', b.dataset.filter === S.filter)); }

el.msgs.addEventListener('scroll', () => { if (el.msgs.scrollTop < 60) loadOlder(); });
el.msgs.addEventListener('click', (e) => {
    const lb = e.target.closest('[data-lightbox]'); if (lb) return lightbox(lb.dataset.src);
    const row = e.target.closest('.wa-msg');
    const jump = e.target.closest('[data-jump]');
    if (jump) { const tgt = el.msgs.querySelector(`[data-mid="${CSS.escape(jump.dataset.jump)}"]`); tgt?.scrollIntoView({ block: 'center', behavior: 'smooth' }); tgt?.classList.add('ring-2', 'ring-emerald-400'); setTimeout(() => tgt?.classList.remove('ring-2', 'ring-emerald-400'), 1500); return; }
    const act = e.target.closest('[data-act]')?.dataset.act;
    if (act === 'older') return loadOlder();
    if (!row) return;
    const m = S.msgs.find((x) => String(x.id) === row.dataset.mid);
    if (!m) return;
    if (act === 'menu') openMenu(e.target.closest('[data-act]'), m);
    if (act === 'resend') (m._tmp ? runTemp(m) : resend(m));
});

if (can.manage) {
    const patch = async (body) => { const r = await api('PATCH', `/conversations/${S.active}`, body).catch(() => null); if (r?.ok) { upsertConv(r.data.data); renderList(); if (body.is_archived) closeConv(); } else toast('error', errMsg(r, t('action_error'))); };
    $('wa-pin').addEventListener('click', () => patch({ is_pinned: !S.convs.get(S.active).is_pinned }));
    $('wa-archive').addEventListener('click', () => patch({ is_archived: !S.convs.get(S.active).is_archived }));
    $('wa-assign').addEventListener('change', async (e) => { const r = await api('PUT', `/conversations/${S.active}/assign`, { admin_id: e.target.value ? Number(e.target.value) : null }).catch(() => null); if (r?.ok) { upsertConv(r.data.data); renderList(); } else { toast('error', errMsg(r, t('action_error'))); renderHeader(); } });
    $('wa-delchat').addEventListener('click', async () => { if (!window.confirm(t('delete_chat_confirm'))) return; const id = S.active; const r = await api('DELETE', `/conversations/${id}`).catch(() => null); if (r?.ok) { S.convs.delete(id); closeConv(); } else toast('error', errMsg(r, t('action_error'))); });
}

if (can.send) {
    $('wa-new').addEventListener('click', newChat);
    $('wa-send').addEventListener('click', sendText);
    el.input.addEventListener('input', onType);
    el.input.addEventListener('keydown', (e) => { if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); sendText(); } });
    el.input.addEventListener('paste', (e) => { const fs = [...(e.clipboardData?.files || [])]; if (fs.length) { e.preventDefault(); addFiles(fs); } });
    $('wa-emoji-btn').addEventListener('click', () => $('wa-emoji-pop').classList.toggle('hidden'));
    $('wa-attach-btn').addEventListener('click', () => $('wa-attach-pop').classList.toggle('hidden'));
    $('wa-attach-pop').addEventListener('click', (e) => {
        const a = e.target.closest('[data-attach]')?.dataset.attach; if (!a) return;
        $('wa-attach-pop').classList.add('hidden');
        if (a === 'media') $('wa-file-media').click(); else if (a === 'document') $('wa-file-doc').click(); else if (a === 'location') shareLocation(); else if (a === 'contact') shareContact();
    });
    ['wa-file-media', 'wa-file-doc'].forEach((id) => $(id).addEventListener('change', (e) => { const doc = id === 'wa-file-doc'; addFiles([...e.target.files].map((f) => { if (doc) f._type = 'document'; return f; })); e.target.value = ''; }));
    $('wa-tray').addEventListener('click', (e) => { const i = e.target.closest('[data-rmfile]')?.dataset.rmfile; if (i !== undefined) { S.files.splice(Number(i), 1); renderTray(); } });
    $('wa-mic').addEventListener('click', startRec);
    $('wa-rec-cancel').addEventListener('click', () => { rec.cancelled = true; rec.mr?.stop(); });
    $('wa-rec-send').addEventListener('click', () => rec.mr?.stop());
    let drag = 0;
    const dz = $('wa-dropzone');
    el.thread.addEventListener('dragenter', (e) => { if (e.dataTransfer?.types?.includes('Files')) { drag++; dz.classList.remove('hidden'); dz.classList.add('grid'); } });
    el.thread.addEventListener('dragleave', () => { if (--drag <= 0) { drag = 0; dz.classList.add('hidden'); dz.classList.remove('grid'); } });
    el.thread.addEventListener('dragover', (e) => e.preventDefault());
    el.thread.addEventListener('drop', (e) => { e.preventDefault(); drag = 0; dz.classList.add('hidden'); dz.classList.remove('grid'); if (e.dataTransfer?.files?.length) addFiles([...e.dataTransfer.files]); });
    buildEmoji();
}

async function init() {
    syncFilters();
    api('GET', '/limits').then((r) => { if (r.ok) { S.limits = r.data.data; setConnected(r.data.data.connected); } }).catch(() => {});
    setInterval(() => api('GET', '/limits').then((r) => { if (r.ok) setConnected(r.data.data.connected); }).catch(() => {}), 60000);
    await loadConvs();
    startRealtime();
}
init();
