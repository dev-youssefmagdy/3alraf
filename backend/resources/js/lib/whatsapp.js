import $ from 'jquery';
import toastr from 'toastr';

const panel = document.getElementById('wa-panel');
if (panel) {
    const d = panel.dataset;
    const labels = JSON.parse(d.labels);
    const show = (s) => {
        const ok = s.status === 'connected';
        const bad = ['error', 'logged_out'].includes(s.status);
        $('#wa-status-text').text(labels[s.status] || s.status);
        $('#wa-status').removeClass('bg-emerald-50 text-emerald-700 bg-red-50 text-red-700 bg-amber-50 text-amber-700')
            .addClass(ok ? 'bg-emerald-50 text-emerald-700' : bad ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-700');
        $('#wa-phone-big').text(s.phone ? '+' + s.phone : '');
        $('#wa-connected').toggleClass('hidden', !ok);
        $('#wa-scan').toggleClass('hidden', ok);
        $('#wa-connect').toggleClass('hidden', ok);
        $('#wa-phone').text(s.phone ? '+' + s.phone : '—');
        $('#wa-error').text(s.error || '—');
        if (s.last_connected_at) $('#wa-last').text(s.last_connected_at);
        const qr = s.status === 'qr' && s.qr;
        $('#wa-qr').toggleClass('hidden', !qr);
        if (qr) $('#wa-qr').attr('src', s.qr);
        $('#wa-qr-hint').toggleClass('hidden', !!qr);
    };
    const poll = () => $.getJSON(d.statusUrl).done(show);
    let timer = setInterval(poll, 3000); // safety net; relaxed once the socket is up
    $(document).on('click', '[data-wa-action]', function () {
        const $b = $(this);
        if ($b.data('confirm') && !window.confirm($b.data('confirm'))) return;
        $b.prop('disabled', true);
        $.post($b.data('wa-action')).done((r) => { toastr.success(r.message); if (r.data) show(r.data); })
            .fail((x) => toastr.error(x.responseJSON?.message || 'Error')).always(() => $b.prop('disabled', false));
    });
    if (d.wsKey) {
        Promise.all([import('laravel-echo'), import('pusher-js')]).then(([{ default: Echo }, { default: Pusher }]) => {
            window.Pusher = Pusher;
            const echo = new Echo({
                broadcaster: 'reverb', key: d.wsKey, wsHost: d.wsHost, wsPort: d.wsPort, wssPort: d.wsPort, forceTLS: d.wsScheme === 'https',
                enabledTransports: ['ws', 'wss'], authEndpoint: d.authUrl, auth: { headers: { 'X-CSRF-TOKEN': $('meta[name=csrf-token]').attr('content') } },
            });
            echo.private(d.channel).listen('.session.updated', show);
            echo.connector.pusher.connection.bind('connected', () => { clearInterval(timer); timer = setInterval(poll, 30000); });
        }).catch(() => {});
    }
    poll();
}
