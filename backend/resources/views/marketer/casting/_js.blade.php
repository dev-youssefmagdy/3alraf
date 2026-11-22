{{-- Tiny fetch helper shared by the casting pages: window.casting.post(url, data|FormData, method) → JSON, errors surfaced as alerts. --}}
<script>
window.casting = {
    csrf: document.querySelector('meta[name=csrf-token]')?.content,
    async req(url, data, method = 'POST') {
        const isForm = data instanceof FormData;
        const res = await fetch(url, {method, credentials: 'same-origin', headers: {'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json', ...(isForm || !data ? {} : {'Content-Type': 'application/json'})}, body: data ? (isForm ? data : JSON.stringify(data)) : undefined});
        const j = await res.json().catch(() => ({}));
        if (!res.ok) { throw new Error(j.message || Object.values(j.errors || {}).flat()[0] || ('HTTP ' + res.status)); }
        return j;
    },
    async get(url) { const r = await fetch(url, {credentials: 'same-origin', headers: {'Accept': 'application/json'}}); if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); },
};
</script>
