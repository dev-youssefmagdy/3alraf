{{-- Suggestion action buttons: <button data-sugg-post="url" [data-confirm] [data-note] [data-form="#id"]> → POST (CSRF, JSON), toastr, reload. --}}
<script>
document.addEventListener('click', async (ev) => {
    const b = ev.target.closest('[data-sugg-post]');
    if (!b) return;
    ev.preventDefault();
    if (b.dataset.confirm && !confirm(b.dataset.confirm)) return;
    const fd = b.dataset.form ? new FormData(document.querySelector(b.dataset.form)) : new FormData();
    if (b.dataset.note !== undefined) {
        const note = prompt(b.dataset.note || '');
        if (note === null) return;
        fd.set('note', note);
    }
    b.disabled = true;
    try {
        const res = await fetch(b.dataset.suggPost, {method: 'POST', body: fd, headers: {'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json'}});
        const j = await res.json().catch(() => ({}));
        if (!res.ok) {
            const first = j.errors ? Object.values(j.errors)[0] : null;
            window.toastr ? toastr.error((Array.isArray(first) ? first[0] : first) || j.message || 'Error') : alert(j.message || 'Error');
            if (res.status === 422 && j.data && j.data.execution_id) setTimeout(() => location.reload(), 1500);
            return;
        }
        j.message && window.toastr && toastr.success(j.message);
        setTimeout(() => (j.redirect ? location.href = j.redirect : location.reload()), 600);
    } finally { b.disabled = false; }
});
</script>
