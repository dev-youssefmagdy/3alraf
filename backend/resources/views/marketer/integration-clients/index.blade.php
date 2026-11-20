<x-layouts.marketer :title="__('admin.integration.title')">
    <x-slot:header><x-ui.page-header :title="__('admin.integration.title')" :subtitle="__('admin.integration.hint')" /></x-slot:header>
    <div class="space-y-4">
        <x-ui.alert tone="info"><code class="break-all" dir="ltr">{{ $base }}</code></x-ui.alert>
        <div id="secret-box" class="hidden">
            <x-ui.alert tone="warning">
                <p class="font-semibold">{{ __('admin.integration.show_once') }}</p>
                <p class="mt-2">{{ __('admin.integration.token') }}: <code id="secret-token" class="break-all"></code></p>
                <p class="mt-1">{{ __('admin.integration.hmac') }}: <code id="secret-hmac" class="break-all"></code></p>
            </x-ui.alert>
        </div>
        @can('integration_clients.manage')
            <form data-ajax-form action="{{ route('marketer.integration-clients.store') }}" method="POST" novalidate>
                @csrf
                <x-ui.form-section :title="__('admin.integration.create')" :cols="1">
                    <x-ui.input name="name" :label="__('admin.integration.name')" required />
                    <div data-field>
                        <span class="form-label">{{ __('admin.integration.scopes') }}</span>
                        @foreach ($scopes as $k => $desc)
                            <x-ui.checkbox name="scopes[]" :value="$k" :bool="false" :label="$k" :help="$desc" class="!w-full" />
                        @endforeach
                    </div>
                    <div><x-ui.button type="submit">{{ __('admin.integration.create') }}</x-ui.button></div>
                </x-ui.form-section>
            </form>
            <form id="edit-client" data-ajax-form data-method="PUT" action="#" method="POST" class="hidden" novalidate>
                @csrf
                <x-ui.form-section :title="__('admin.edit')" :cols="1">
                    <x-ui.input name="name" :label="__('admin.integration.name')" required />
                    <div data-field>
                        <span class="form-label">{{ __('admin.integration.scopes') }}</span>
                        @foreach ($scopes as $k => $desc)
                            <x-ui.checkbox name="scopes[]" :value="$k" :bool="false" :label="$k" class="!w-full" />
                        @endforeach
                    </div>
                    <div><x-ui.button type="submit">{{ __('admin.save') }}</x-ui.button></div>
                </x-ui.form-section>
            </form>
        @endcan
        <x-ui.data-table :table="$table" :url="route('marketer.integration-clients.datatable')" />
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
