import $ from 'jquery';

// translatable tabs
$(document).on('click', '[data-tab-target]', function () {
    const $w = $(this).closest('[data-tabs]');
    $w.find('[data-tab-target]').removeClass('border-brand-600 text-brand-600').addClass('border-transparent text-slate-500');
    $(this).addClass('border-brand-600 text-brand-600').removeClass('border-transparent text-slate-500');
    $w.find('[data-tab-pane]').addClass('hidden');
    $w.find('[data-tab-pane="' + $(this).data('tab-target') + '"]').removeClass('hidden');
});

// modals
$(document).on('click', '[data-modal-open]', function () { $('#' + $(this).data('modal-open')).removeClass('hidden').addClass('flex'); });
$(document).on('click', '[data-modal-close]', function () { $(this).closest('[data-modal]').addClass('hidden').removeClass('flex'); });

// dropdowns
$(document).on('click', '[data-dropdown-toggle]', function (e) {
    e.stopPropagation();
    const $m = $('#' + $(this).data('dropdown-toggle'));
    $('[data-dropdown]').not($m).addClass('hidden');
    $m.toggleClass('hidden');
});
$(document).on('click', function () { $('[data-dropdown]').addClass('hidden'); });
$(document).on('click', '[data-sidebar-toggle]', function () { $('#sidebar').toggleClass('hidden'); });

// Panel shell (resources/views/components/panel/shell.blade.php): mobile sidebar backdrop + ⌘K page jump over the sidebar links
$(document).on('click', '[data-sidebar-toggle]', function () { $('[data-sidebar-backdrop]').toggleClass('hidden', $('#sidebar').hasClass('hidden')); });
$(document).on('click', '[data-sidebar-backdrop]', function () { $('#sidebar').addClass('hidden'); $(this).addClass('hidden'); });
(function () {
    const box = document.querySelector('[data-cmdk]');
    if (!box) return;
    const input = box.querySelector('[data-cmdk-input]');
    const list = box.querySelector('[data-cmdk-results]');
    const links = [...document.querySelectorAll('[data-nav-link]')].map((a) => ({ href: a.href, text: a.textContent.replace(/\s+/g, ' ').replace(/\d+\+?$/, '').trim() }));
    const norm = (s) => s.toLowerCase().normalize('NFKD').replace(/[ً-ْـ]/g, '').replace(/[أإآ]/g, 'ا').replace(/ة/g, 'ه').replace(/ى/g, 'ي');
    let sel = 0, hits = [];
    const render = () => {
        const q = norm(input.value.trim());
        hits = q ? links.filter((l) => norm(l.text).includes(q)).slice(0, 8) : [];
        sel = 0;
        list.innerHTML = hits.length ? hits.map((h, i) => `<a href="${h.href}" role="option" aria-selected="${i === 0}" class="block rounded-xl px-3 py-2 text-sm text-slate-800 hover:bg-slate-100">${h.text.replace(/[<>&]/g, '')}</a>`).join('')
            : (q ? `<p class="px-3 py-2 text-sm text-slate-500">${(window.__t && window.__t.cmdk_empty) || '—'}</p>` : '');
        list.classList.toggle('hidden', !q);
    };
    const move = (d) => { if (!hits.length) return; sel = (sel + d + hits.length) % hits.length; [...list.children].forEach((c, i) => c.setAttribute('aria-selected', String(i === sel))); };
    input.addEventListener('input', render);
    input.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowDown') { e.preventDefault(); move(1); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); move(-1); }
        else if (e.key === 'Enter' && hits[sel]) { e.preventDefault(); window.location.href = hits[sel].href; }
        else if (e.key === 'Escape') { input.value = ''; render(); input.blur(); }
    });
    document.addEventListener('keydown', (e) => { if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); input.focus(); } });
    document.addEventListener('click', (e) => { if (!box.contains(e.target)) list.classList.add('hidden'); });
})();
