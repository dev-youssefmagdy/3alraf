<x-layouts.marketer :title="__('admin.integration.title')">
    <div class="space-y-4">
        <x-card :title="__('admin.integration.title')">
            <p class="mb-3 text-sm text-slate-600">{{ __('admin.integration.hint') }} <code class="break-all rounded bg-slate-100 px-1" dir="ltr">{{ $base }}</code></p>
            <div id="secret-box" class="mb-4 hidden rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm">
                <p class="font-semibold text-amber-800">{{ __('admin.integration.show_once') }}</p>
                <p class="mt-2">{{ __('admin.integration.token') }}: <code id="secret-token" class="break-all"></code></p>
                <p class="mt-1">{{ __('admin.integration.hmac') }}: <code id="secret-hmac" class="break-all"></code></p>
            </div>
            @can('integration_clients.manage')
                <form data-ajax-form action="{{ route('marketer.integration-clients.store') }}" method="POST" class="mb-6 grid gap-3 sm:grid-cols-3" novalidate>
                    @csrf
                    <x-input name="name" :label="__('admin.integration.name')" required />
                    <div data-field>
                        <span class="form-label">{{ __('admin.integration.scopes') }}</span>
                        @foreach ($scopes as $k => $desc)
                            <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="scopes[]" value="{{ $k }}" class="mt-1"> <span><b>{{ $k }}</b> <span class="text-slate-500">{{ $desc }}</span></span></label>
                        @endforeach
                    </div>
                    <div class="sm:col-span-3"><x-button type="submit">{{ __('admin.integration.create') }}</x-button></div>
                </form>
            @endcan
            @can('integration_clients.manage')
                <form id="edit-client" data-ajax-form data-method="PUT" action="#" method="POST" class="mb-6 hidden grid gap-3 rounded-lg border border-slate-200 p-3 sm:grid-cols-3" novalidate>
                    @csrf
                    <x-input name="name" :label="__('admin.integration.name')" required />
                    <div data-field>
                        <span class="form-label">{{ __('admin.integration.scopes') }}</span>
                        @foreach ($scopes as $k => $desc)
                            <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="scopes[]" value="{{ $k }}" class="mt-1"> <span><b>{{ $k }}</b></span></label>
                        @endforeach
                    </div>
                    <div class="sm:col-span-3"><x-button type="submit">{{ __('admin.save') }}</x-button></div>
                </form>
            @endcan
            <x-datatable :table="$table" :url="route('marketer.integration-clients.datatable')" />
        </x-card>
    </div>
{{-- POST action buttons: <button data-gpost="url" [data-confirm="text"]> (rotate / revoke); reloads the DataTable on success. --}}
<script>
document.addEventListener('click', async (ev) => {
    const b = ev.target.closest('[data-gpost]');
    if (!b) return;
    if (b.dataset.confirm && !confirm(b.dataset.confirm)) return;
    const body = new FormData();
    b.disabled = true;
    try {
        const res = await fetch(b.dataset.gpost, {method: 'POST', body, headers: {'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json'}});
        const j = await res.json().catch(() => ({}));
        if (!res.ok) { toastr.error(j.message || (j.errors && Object.values(j.errors)[0][0]) || 'Error'); return; }
        j.message && toastr.success(j.message);
        if (j.data && j.data.token) { document.dispatchEvent(new CustomEvent('integration:secret', {detail: j.data})); }
        const t = document.querySelector('table[data-datatable]');
        if (t && window.jQuery) window.jQuery(t).DataTable().ajax.reload(null, false);
    } finally { b.disabled = false; }
});
</script>
    <script>
    document.addEventListener('click', (e) => {
        const b = e.target.closest('[data-edit-client]'); if (!b) return;
        const d = JSON.parse(b.dataset.editClient), f = document.getElementById('edit-client'); if (!f) return;
        f.action = d.url; f.classList.remove('hidden');
        f.querySelector('[name=name]').value = d.name;
        f.querySelectorAll('[name="scopes[]"]').forEach((c) => { c.checked = d.scopes.includes(c.value); });
        f.scrollIntoView({ behavior: 'smooth' });
    });
    const showSecret = (d) => { document.getElementById('secret-token').textContent = d.token; document.getElementById('secret-hmac').textContent = d.secret; document.getElementById('secret-box').classList.remove('hidden'); };
    document.addEventListener('integration:secret', (e) => showSecret(e.detail));
    document.addEventListener('DOMContentLoaded', () => window.jQuery(document).on('ajax-form:success', 'form', (e, res) => { if (res.data && res.data.token) { showSecret(res.data); window.jQuery('table[data-datatable]').DataTable().ajax.reload(null, false); } }));
    </script>
</x-layouts.marketer>
