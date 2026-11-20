import $ from 'jquery';

/*
 * Shared UI behaviour for the panels (resources/views/components/ui/*, components/panel/*). Imported by app.js (admin + marketer) and storefront.js (affiliate).
 * Plain DOM + delegated events, no per-page wiring: every behaviour hangs off a data-* attribute emitted by a Blade component.
 */
const doc = document.documentElement;
const isPanel = () => !!doc.dataset.panel;
const t = (k, d = '') => (window.__t && window.__t[k]) || d;
const focusable = 'a[href],button:not([disabled]),input:not([disabled]):not([type=hidden]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])';

// ---- language tabs (x-ui.lang-tabs) ------------------------------------------------------------------------------------------------------------
const TAB_ON = ['bg-panel-surface', 'text-panel-text', 'shadow-panel-xs', 'is-active'];
$(document).on('click', '[data-tab-target]', function () {
    const $w = $(this).closest('[data-tabs]');
    $w.find('[data-tab-target]').each(function () { this.classList.remove(...TAB_ON); this.classList.add('text-panel-text-muted'); this.setAttribute('aria-selected', 'false'); });
    this.classList.add(...TAB_ON); this.classList.remove('text-panel-text-muted'); this.setAttribute('aria-selected', 'true');
    $w.find('[data-tab-pane]').addClass('hidden');
    $w.find('[data-tab-pane="' + $(this).data('tab-target') + '"]').removeClass('hidden');
});

// ---- page tabs (x-ui.tabs panels=true): show one [data-ptab-panel] per tab group, the URL hash #tab-key picks the start tab ---------------------------------
const ON = ['border-panel-accent', 'text-panel-accent-text'], OFF = ['border-transparent', 'text-panel-text-muted'];
export function showPanelTab(nav, key) {
    const id = nav.dataset.ptabs;
    nav.querySelectorAll('[data-ptab]').forEach((b) => {
        const on = b.dataset.ptab === key;
        b.setAttribute('aria-selected', on ? 'true' : 'false');
        b.classList.remove(...(on ? OFF : ON)); b.classList.add(...(on ? ON : OFF));
    });
    document.querySelectorAll('[data-ptab-for="' + id + '"]').forEach((p) => p.classList.toggle('hidden', p.dataset.ptabPanel !== key));
}
$(document).on('click', '[data-ptab]', function () {
    const nav = this.closest('[data-ptabs]');
    showPanelTab(nav, this.dataset.ptab);
    try { history.replaceState(null, '', '#tab-' + this.dataset.ptab); } catch (e) { /* ignore */ }
});
$(() => {
    document.querySelectorAll('[data-ptabs]').forEach((nav) => {
        const keys = [...nav.querySelectorAll('[data-ptab]')].map((b) => b.dataset.ptab);
        // a11y: each tab button controls its panel(s); panels are tabpanels labelled by the tab
        nav.querySelectorAll('[data-ptab]').forEach((b) => {
            const panels = [...document.querySelectorAll('[data-ptab-for="' + nav.dataset.ptabs + '"][data-ptab-panel="' + b.dataset.ptab + '"]')];
            if (!b.id) b.id = 'ptab-' + nav.dataset.ptabs + '-' + b.dataset.ptab;
            panels.forEach((p, i) => { if (!p.id) p.id = 'ptabpanel-' + nav.dataset.ptabs + '-' + b.dataset.ptab + (i ? '-' + i : ''); p.setAttribute('role', 'tabpanel'); p.setAttribute('aria-labelledby', b.id); });
            if (panels.length) b.setAttribute('aria-controls', panels.map((p) => p.id).join(' '));
        });
        const fromHash = location.hash.startsWith('#tab-') ? location.hash.slice(5) : null;
        showPanelTab(nav, keys.includes(fromHash) ? fromHash : keys[0]);
    });
});
// ---- modal / drawer (x-ui.modal, x-ui.drawer): focus trap, Esc, backdrop, focus restored to the trigger --------------------------------------
let lastTrigger = null;
const openModal = (el, trigger) => {
    lastTrigger = trigger || document.activeElement;
    el.classList.remove('hidden'); el.classList.add('flex'); el.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    const dlg = el.querySelector('[role=dialog]');
    setTimeout(() => (dlg.querySelector('[autofocus],input,select,textarea') || dlg).focus(), 30);
};
const closeModal = (el) => {
    el.classList.add('hidden'); el.classList.remove('flex'); el.setAttribute('aria-hidden', 'true');
    if (!document.querySelector('[data-modal].flex')) document.body.style.overflow = '';
    if (lastTrigger && document.contains(lastTrigger)) lastTrigger.focus();
};
$(document).on('click', '[data-modal-open]', function () { const el = document.getElementById($(this).data('modal-open')); if (el) openModal(el, this); });
$(document).on('click', '[data-modal-close], [data-modal-backdrop]', function () { const el = $(this).closest('[data-modal]')[0]; if (el) closeModal(el); });
document.addEventListener('keydown', (e) => {
    const open = [...document.querySelectorAll('[data-modal].flex')].pop();
    if (!open) return;
    if (e.key === 'Escape') { e.stopPropagation(); closeModal(open); return; }
    if (e.key === 'Tab') {
        const f = [...open.querySelectorAll(focusable)].filter((x) => x.offsetParent !== null);
        if (!f.length) return;
        const first = f[0], last = f[f.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); } else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }
});
window.panelModal = { open: (id) => { const el = document.getElementById(id); if (el) openModal(el); }, close: (id) => { const el = document.getElementById(id); if (el) closeModal(el); } };

// ---- dropdown / popover ------------------------------------------------------------------------------------------------------------------------
const closeDropdowns = (except) => $('[data-dropdown]').not(except || []).addClass('hidden').each(function () { $('[data-dropdown-toggle="' + this.id + '"]').attr('aria-expanded', 'false'); });
$(document).on('click', '[data-dropdown-toggle]', function (e) {
    e.stopPropagation();
    const $m = $('#' + $(this).data('dropdown-toggle'));
    closeDropdowns($m);
    $m.toggleClass('hidden');
    $(this).attr('aria-expanded', String(!$m.hasClass('hidden')));
    if (!$m.hasClass('hidden')) $m.find('[role=menuitem],a,button').filter(':visible').first().trigger('focus');
});
$(document).on('click', (e) => { if (!$(e.target).closest('[data-dropdown]').length) closeDropdowns(); });
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') { const o = document.querySelector('[data-dropdown]:not(.hidden)'); if (o) { closeDropdowns(); const tg = document.querySelector('[data-dropdown-toggle="' + o.id + '"]'); tg && tg.focus(); } }
    if ((e.key === 'ArrowDown' || e.key === 'ArrowUp') && document.activeElement && document.activeElement.closest('[role=menu]')) {
        const items = [...document.activeElement.closest('[role=menu]').querySelectorAll('[role=menuitem],a,button')].filter((x) => x.offsetParent !== null);
        const i = items.indexOf(document.activeElement);
        if (i > -1) { e.preventDefault(); items[(i + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length].focus(); }
    }
});
$(document).on('click', '[data-alert-close]', function () { $(this).closest('[data-alert]').remove(); });

// ---- sidebar: drawer below 768, rail flyouts at tablet, one nav group open at a time -----------------------------------------------------
const sidebar = document.getElementById('sidebar');
const backdrop = document.querySelector('[data-sidebar-backdrop]');
const setDrawer = (open) => {
    if (!sidebar) return;
    sidebar.toggleAttribute('data-open', open);
    if (backdrop) backdrop.classList.toggle('hidden', !open);
    document.body.style.overflow = open ? 'hidden' : '';
    if (open) setTimeout(() => (sidebar.querySelector('a[aria-current],a') || sidebar).focus(), 50);
};
$(document).on('click', '[data-sidebar-toggle]', () => setDrawer(!(sidebar && sidebar.hasAttribute('data-open'))));
$(document).on('click', '[data-sidebar-backdrop]', () => setDrawer(false));
$(document).on('click', '#sidebar a[data-nav-link]', () => setDrawer(false));
document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && sidebar && sidebar.hasAttribute('data-open')) setDrawer(false); });
const rail = matchMedia('(min-width: 768px) and (max-width: 1279.98px)');
const syncRail = () => {
    document.querySelectorAll('[data-nav-group]').forEach((d) => {
        if (rail.matches) d.removeAttribute('open');
        else if (d.querySelector('[aria-current]')) d.setAttribute('open', '');
    });
};
rail.addEventListener('change', syncRail);
syncRail();
$(document).on('toggle', '[data-nav-group]', function () {
    if (!this.open) return;
    document.querySelectorAll('[data-nav-group][open]').forEach((o) => { if (o !== this) o.removeAttribute('open'); });
    if (rail.matches) { const r = this.querySelector('summary').getBoundingClientRect(); const f = this.querySelector('[data-nav-flyout]'); f.style.top = Math.max(8, Math.min(r.top, innerHeight - f.offsetHeight - 8)) + 'px'; f.style[document.dir === 'rtl' ? 'right' : 'left'] = '76px'; }
});
document.addEventListener('toggle', (e) => { if (e.target.matches && e.target.matches('[data-nav-group]')) $(e.target).trigger('toggle'); }, true);
document.addEventListener('click', (e) => { if (rail.matches && !e.target.closest('[data-nav-group]')) document.querySelectorAll('[data-nav-group][open]').forEach((o) => o.removeAttribute('open')); });

// ---- theme (light default, dark from the toggle; remembered per browser) -------------------------------------------------------------------
$(document).on('click', '[data-theme-toggle]', () => {
    const dark = doc.getAttribute('data-theme') !== 'dark';
    dark ? doc.setAttribute('data-theme', 'dark') : doc.removeAttribute('data-theme');
    try { localStorage.setItem('panel_theme', dark ? 'dark' : 'light'); } catch (e) { /* private mode */ }
    window.dispatchEvent(new CustomEvent('panel:theme', { detail: { dark } }));
});

// ---- command palette (x-ui.command-palette): Ctrl/Cmd+K, filters the nav pages + quick actions ------------------------------------------
(function () {
    const box = document.querySelector('[data-cmdk]');
    if (!box) return;
    const input = box.querySelector('[data-cmdk-input]'), list = box.querySelector('[data-cmdk-results]');
    let data = { groups: [], empty: '' };
    try { data = JSON.parse(box.querySelector('[data-cmdk-data]').textContent); } catch (e) { /* none */ }
    const norm = (s) => String(s || '').toLowerCase().normalize('NFKD').replace(/[ً-ْـ]/g, '').replace(/[أإآ]/g, 'ا').replace(/ة/g, 'ه').replace(/ى/g, 'ي');
    const esc = (s) => String(s || '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const iconSvg = (m) => '<svg class="size-5 shrink-0 text-panel-text-subtle" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + (m || '') + '</svg>';
    let hits = [], sel = 0, last = null;
    const render = () => {
        const q = norm(input.value.trim());
        hits = [];
        let html = '';
        data.groups.forEach((g) => {
            let items = g.items.filter((i) => !q || norm(i.label + ' ' + (i.hint || '')).includes(q));
            if (!q && g.items.length > 8) items = items.slice(0, 6);
            if (!items.length) return;
            html += '<p class="px-3 pb-1 pt-2 text-xs font-bold text-panel-text-subtle">' + esc(g.label) + '</p>';
            items.slice(0, q ? 12 : 8).forEach((i) => {
                const idx = hits.push(i) - 1;
                html += '<a href="' + esc(i.href) + '" role="option" data-cmdk-item data-i="' + idx + '" aria-selected="' + (idx === 0) + '" class="flex min-h-11 items-center gap-3 rounded-xl px-3 py-2 text-sm text-panel-text">' + iconSvg(i.svg) + '<span class="min-w-0 flex-1 truncate">' + esc(i.label) + '</span>' + (i.hint ? '<span class="truncate text-xs text-panel-text-subtle">' + esc(i.hint) + '</span>' : '') + '</a>';
            });
        });
        sel = 0;
        list.innerHTML = html || '<p class="px-3 py-6 text-center text-sm text-panel-text-subtle">' + esc(data.empty || t('cmdk_empty', '-')) + '</p>';
    };
    const mark = () => list.querySelectorAll('[data-cmdk-item]').forEach((c, i) => { c.setAttribute('aria-selected', String(i === sel)); if (i === sel) c.scrollIntoView({ block: 'nearest' }); });
    const open = () => { last = document.activeElement; box.classList.remove('hidden'); box.classList.add('flex'); box.setAttribute('aria-hidden', 'false'); document.body.style.overflow = 'hidden'; input.value = ''; render(); input.focus(); };
    const close = () => { box.classList.add('hidden'); box.classList.remove('flex'); box.setAttribute('aria-hidden', 'true'); document.body.style.overflow = ''; if (last && document.contains(last)) last.focus(); };
    input.addEventListener('input', render);
    input.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowDown' && hits.length) { e.preventDefault(); sel = (sel + 1) % hits.length; mark(); }
        else if (e.key === 'ArrowUp' && hits.length) { e.preventDefault(); sel = (sel - 1 + hits.length) % hits.length; mark(); }
        else if (e.key === 'Enter' && hits[sel]) { e.preventDefault(); window.location.href = hits[sel].href; }
    });
    list.addEventListener('mousemove', (e) => { const a = e.target.closest('[data-cmdk-item]'); if (a) { sel = +a.dataset.i; mark(); } });
    document.addEventListener('click', (e) => { if (e.target.closest('[data-cmdk-open]')) open(); else if (e.target.closest('[data-cmdk-close]')) close(); });
    document.addEventListener('keydown', (e) => {
        if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); box.classList.contains('hidden') ? open() : close(); }
        else if (e.key === 'Escape' && !box.classList.contains('hidden')) close();
    });
})();

// ---- toasts: window.toast({tone, message, action, href, onAction}); in a panel toastr.success/error/info/warning use the same markup ----------
export function toast({ tone = 'info', message = '', action = null, href = null, onAction = null, timeout = tone === 'danger' ? 0 : 4000 } = {}) {
    const region = document.querySelector('[data-toast-region]'), tpl = document.querySelector('[data-toast-template="' + tone + '"]');
    if (!region || !tpl) return null;
    const el = tpl.content.firstElementChild.cloneNode(true);
    el.querySelector('[data-toast-message]').textContent = message;
    const a = el.querySelector('[data-toast-action]');
    if (a) { if (action) { a.textContent = action; if (href) a.href = href; a.addEventListener('click', (e) => { if (onAction) { e.preventDefault(); onAction(); } el.remove(); }); } else a.remove(); }
    el.querySelector('[data-toast-close]').addEventListener('click', () => el.remove());
    region.appendChild(el);
    if (timeout) setTimeout(() => el.remove(), timeout);
    return el;
}
window.toast = toast;
document.addEventListener('DOMContentLoaded', () => {
    if (!isPanel() || !window.toastr || !document.querySelector('[data-toast-region]')) return;
    const map = { success: 'success', error: 'danger', info: 'info', warning: 'warning' };
    Object.entries(map).forEach(([fn, tone]) => { window.toastr[fn] = (message, title) => { toast({ tone, message: [title, message].filter(Boolean).reverse().join(': ') }); }; });
    window.toastr.clear = () => {};
});

// ---- small field behaviours ----------------------------------------------------------------------------------------------------------------
$(document).on('click', '[data-toggle-password]', function () {
    const i = document.getElementById(this.dataset.togglePassword); if (!i) return;
    const show = i.type === 'password'; i.type = show ? 'text' : 'password';
    this.querySelector('[data-eye-on]').classList.toggle('hidden', show); this.querySelector('[data-eye-off]').classList.toggle('hidden', !show);
    this.setAttribute('aria-label', show ? this.dataset.labelHide : this.dataset.labelShow);
});
$(document).on('click', '[data-sheet-toggle]', function () {
    const id = this.dataset.sheetToggle, sh = document.getElementById(id), bd = document.querySelector('[data-sheet-backdrop="' + id + '"]');
    if (!sh) return;
    const open = !sh.hasAttribute('data-open'); sh.toggleAttribute('data-open', open); if (bd) bd.classList.toggle('hidden', !open);
});
$(document).on('click', '[data-sheet-backdrop]', function () { const sh = document.getElementById(this.dataset.sheetBackdrop); sh && sh.removeAttribute('data-open'); this.classList.add('hidden'); });
$(document).on('input change', 'form[data-ajax-form] input:not([data-otp-box]), form[data-ajax-form] select, form[data-ajax-form] textarea', function () { $(this).closest('form').find('[data-unsaved]').removeAttr('hidden'); });
document.querySelectorAll('input[data-indeterminate]').forEach((c) => { c.indeterminate = true; });

// OTP boxes (x-ui.otp-input): auto-advance, backspace, arrow keys, paste of the whole code; joined digits go in the hidden input
$(document).on('input', '[data-otp-box]', function () {
    const wrap = this.closest('[data-otp]'), boxes = [...wrap.querySelectorAll('[data-otp-box]')];
    let v = this.value.replace(/\D/g, '');
    if (v.length > 1) { v.slice(0, boxes.length).split('').forEach((d, i) => { boxes[i].value = d; }); boxes[Math.min(v.length, boxes.length) - 1].focus(); }
    else { this.value = v; if (v) { const n = boxes[boxes.indexOf(this) + 1]; n && n.focus(); } }
    wrap.querySelector('[data-otp-value]').value = boxes.map((b) => b.value).join('');
    boxes.forEach((b) => b.classList.remove('is-invalid', '!border-panel-danger-solid'));
});
$(document).on('keydown', '[data-otp-box]', function (e) {
    const boxes = [...this.closest('[data-otp]').querySelectorAll('[data-otp-box]')], i = boxes.indexOf(this);
    if (e.key === 'Backspace' && !this.value && i > 0) { boxes[i - 1].focus(); boxes[i - 1].value = ''; boxes[i - 1].dispatchEvent(new Event('input', { bubbles: true })); }
    else if (e.key === 'ArrowLeft' && boxes[i - 1]) boxes[i - 1].focus();
    else if (e.key === 'ArrowRight' && boxes[i + 1]) boxes[i + 1].focus();
});
$(document).on('focus', '[data-otp-box]', function () { this.select(); });

// ---- copy buttons in the affiliate panel: storefront.js only wires [data-copy] on storefront pages (window.__sf), growth-form.js only in the admin bundle ----
if (doc.dataset.panel === 'affiliate' && !window.__sf) {
    $(document).on('click', '[data-copy]', async function () {
        const text = String(this.dataset.copy);
        try { await navigator.clipboard.writeText(text); toast({ tone: 'success', message: this.dataset.copied || t('copied', 'Copied') }); } catch (e) { window.prompt('', text); }
    });
}
