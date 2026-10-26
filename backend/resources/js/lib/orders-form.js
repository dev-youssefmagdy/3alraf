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

// ---- admin notifications bell: poll (15s; 60s when the Reverb socket is up), toastr pop for new ones; Echo push refreshes instantly ----
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
    let timer = setInterval(poll, 15000);
    if (bell.dataset.channel && bell.dataset.wsKey) {
        Promise.all([import('laravel-echo'), import('pusher-js')]).then(([{ default: Echo }, { default: Pusher }]) => {
            window.Pusher = Pusher;
            const tls = bell.dataset.wsScheme === 'https';
            const echo = new Echo({
                broadcaster: 'reverb', key: bell.dataset.wsKey, wsHost: bell.dataset.wsHost, wsPort: bell.dataset.wsPort, wssPort: bell.dataset.wsPort,
                forceTLS: tls, enabledTransports: ['ws', 'wss'], authEndpoint: bell.dataset.authUrl,
                auth: { headers: { 'X-CSRF-TOKEN': $('meta[name=csrf-token]').attr('content') } },
            });
            echo.private(bell.dataset.channel).notification(() => poll());
            // socket healthy -> relax polling (kept as a safety net); down -> back to 15s
            const conn = echo.connector.pusher.connection;
            conn.bind('connected', () => { clearInterval(timer); timer = setInterval(poll, 60000); });
            conn.bind('unavailable', () => { clearInterval(timer); timer = setInterval(poll, 15000); });
            conn.bind('disconnected', () => { clearInterval(timer); timer = setInterval(poll, 15000); });
        }).catch(() => {});
    }
    $(document).on('click', '[data-notif-id]', function () { $.post(bell.dataset.readUrl.replace('__ID__', $(this).data('notif-id'))); });
    $(document).on('click', '#notif-read-all', function (e) { e.preventDefault(); $.post(bell.dataset.readAllUrl).done(poll); });
}
