<x-layouts.marketer :title="__('marketing.tokens.title')">
    @php $t = fn ($k, $r = []) => __('marketing.tokens.'.$k, $r); @endphp
    <x-slot:header><x-ui.page-header :title="$t('title')" :subtitle="$t('hint')" /></x-slot:header>
    <div class="space-y-4">
        <x-ui.alert tone="info"><code class="break-all" dir="ltr">{{ $base }}</code></x-ui.alert>
        <div id="token-box" class="hidden" role="status">
            <x-ui.alert tone="warning">
                <p class="font-semibold">{{ $t('show_once') }}</p>
                <p class="mt-2" dir="ltr"><code id="token-value" class="break-all select-all"></code></p>
                <button type="button" class="btn btn-secondary btn-sm mt-2" data-copy-token>{{ $t('copy') }}</button>
            </x-ui.alert>
        </div>
        <form data-ajax-form data-validate-url="{{ route('marketer.tokens.validate') }}" action="{{ route('marketer.tokens.store') }}" method="POST" novalidate>
            @csrf
            <x-ui.form-section :title="$t('create')" :cols="1">
                <x-ui.input name="name" :label="$t('name')" required :placeholder="$t('name_placeholder')" />
                <div data-field>
                    <span class="form-label">{{ $t('scopes') }}</span>
                    @foreach ($scopes as $k => $def)
                        <x-ui.checkbox name="scopes[]" :value="$k" :bool="false" :checked="$k === 'marketing:read'" :disabled="! ($def['enabled'] ?? false)" :label="$k" :help="$t('scope_desc.'.str_replace(':', '_', $k)).(($def['enabled'] ?? false) ? '' : ' · '.$t('reserved'))" class="!w-full" />
                    @endforeach
                </div>
                <div data-field>
                    <label class="form-label" for="expires_in_days">{{ $t('expires') }}</label>
                    <select id="expires_in_days" name="expires_in_days" class="form-control">
                        @foreach ($expiry as $d)
                            <option value="{{ $d ?? 'never' }}" @selected($d === $defaultExpiry)>{{ $d === null ? $t('never') : $t('days', ['n' => $d]) }}</option>
                        @endforeach
                    </select>
                </div>
                <div><x-ui.button type="submit">{{ $t('create') }}</x-ui.button></div>
            </x-ui.form-section>
        </form>
        <x-ui.data-table :table="$table" :url="route('marketer.tokens.datatable')" />
    </div>
    <script>
    document.addEventListener('click', async (ev) => {
        const c = ev.target.closest('[data-copy-token]');
        if (c) { navigator.clipboard?.writeText(document.getElementById('token-value').textContent); return; }
        const b = ev.target.closest('[data-gpost]');
        if (!b) return;
        if (b.dataset.confirm && !confirm(b.dataset.confirm)) return;
        b.disabled = true;
        try {
            const res = await fetch(b.dataset.gpost, {method: 'POST', body: new FormData(), headers: {'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json'}});
            const j = await res.json().catch(() => ({}));
            if (!res.ok) { toastr.error(j.message || 'Error'); return; }
            j.message && toastr.success(j.message);
            window.jQuery && window.jQuery('table[data-datatable]').DataTable().ajax.reload(null, false);
        } finally { b.disabled = false; }
    });
    // app.js is a deferred module: bind once jQuery exists
    document.addEventListener('DOMContentLoaded', () => window.jQuery(document).on('ajax-form:success', 'form', (e, res) => {
        if (!res.data || !res.data.token) return;
        document.getElementById('token-value').textContent = res.data.token;
        document.getElementById('token-box').classList.remove('hidden');
        e.target.reset && e.target.reset();
        window.jQuery('table[data-datatable]').DataTable().ajax.reload(null, false);
    }));
    </script>
</x-layouts.marketer>
