import $ from 'jquery';
import toastr from 'toastr';

// discount form: show product / category pickers by scope
function syncScope() {
    const $f = $('[data-discount-form]');
    if (!$f.length) return;
    const scope = $f.find('[name=scope]').val();
    $f.find('[data-scope-pane]').each(function () { $(this).toggle($(this).data('scope-pane') === scope).find('select').prop('disabled', $(this).data('scope-pane') !== scope); });
}
$(document).on('change', '[data-discount-form] [name=scope]', syncScope);
$(syncScope);

// ---- admin notifications bell: poll every 15s, toastr pop for new ones ----
const bell = document.getElementById('notif-menu');
if (bell && bell.dataset.url) {
    const url = bell.dataset.url;
    let lastSeen = null;
    const seen = new Set();
    const render = (res) => {
        const $cnt = $('#notif-count');
        $cnt.toggleClass('hidden', !res.unread).text(res.unread > 9 ? '9+' : res.unread || '');
        const $list = $('#notif-list').empty();
        if (!res.items.length) $list.append($('<p class="p-3 text-sm text-slate-500">').text(bell.dataset.empty));
        res.items.forEach((n) => {
            const $a = $('<a class="block border-b border-slate-100 px-3 py-2 text-sm hover:bg-slate-50">').attr('href', n.url || '#').attr('data-notif-id', n.id)
                .toggleClass('bg-brand-50', !n.read).append($('<div class="font-medium text-slate-800">').text(n.title), $('<div class="text-xs text-slate-500">').text(n.time));
            $list.append($a);
        });
    };
    const poll = () => $.getJSON(url, lastSeen ? { after: lastSeen } : {}).done((res) => {
        render(res);
        res.fresh.filter((n) => !seen.has(n.id)).forEach((n) => { seen.add(n.id); if (lastSeen !== null) toastr.info(n.title, null, { onclick: () => { location.href = n.url; } }); });
        lastSeen = res.latest || lastSeen || '0';
    });
    poll();
    setInterval(poll, 15000);
    $(document).on('click', '[data-notif-id]', function () { $.post(bell.dataset.readUrl.replace('__ID__', $(this).data('notif-id'))); });
    $(document).on('click', '#notif-read-all', function (e) { e.preventDefault(); $.post(bell.dataset.readAllUrl).done(poll); });
}
