{{-- POST action buttons: <button data-gpost="url" [data-confirm="text"] [data-reason] [data-secret]> ; reloads the DataTable on success. --}}
<script>
document.addEventListener('click', async (ev) => {
    const b = ev.target.closest('[data-gpost]');
    if (!b) return;
    if (b.dataset.confirm && !confirm(b.dataset.confirm)) return;
    const body = new FormData();
    if (b.dataset.reason !== undefined) {
        const r = prompt(@json(__('growth_hub.approvals.reason_prompt')));
        if (!r) return;
        body.append('reason', r);
    }
    b.disabled = true;
    try {
        const res = await fetch(b.dataset.gpost, {method: 'POST', body, headers: {'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json'}});
        const j = await res.json().catch(() => ({}));
        if (!res.ok) { toastr.error(j.message || (j.errors && Object.values(j.errors)[0][0]) || 'Error'); return; }
        j.message && toastr.success(j.message);
        if (j.data && j.data.token) { document.dispatchEvent(new CustomEvent('growth:secret', {detail: j.data})); }
        const t = document.querySelector('table[data-datatable]');
        if (t && window.jQuery) window.jQuery(t).DataTable().ajax.reload(null, false);
    } finally { b.disabled = false; }
});
</script>
