<x-layouts.marketer :title="__('marketing.tokens.title')">
    @php $t = fn ($k, $r = []) => __('marketing.tokens.'.$k, $r); @endphp
    <div class="space-y-4">
        <x-card :title="$t('title')">
            <p class="mb-3 text-sm text-slate-600">{{ $t('hint') }} <code class="break-all rounded bg-slate-100 px-1" dir="ltr">{{ $base }}</code></p>
            <div id="token-box" class="mb-4 hidden rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm" role="status">
                <p class="font-semibold text-amber-800">{{ $t('show_once') }}</p>
                <p class="mt-2" dir="ltr"><code id="token-value" class="break-all select-all"></code></p>
                <button type="button" class="btn-sm btn-secondary mt-2" data-copy-token>{{ $t('copy') }}</button>
            </div>
            <form data-ajax-form data-validate-url="{{ route('marketer.tokens.validate') }}" action="{{ route('marketer.tokens.store') }}" method="POST" class="mb-6 grid gap-3 sm:grid-cols-3" novalidate>
                @csrf
                <x-input name="name" :label="$t('name')" required :placeholder="$t('name_placeholder')" />
                <div data-field>
                    <span class="form-label">{{ $t('scopes') }}</span>
                    @foreach ($scopes as $k => $def)
                        <label class="flex items-start gap-2 text-sm {{ ($def['enabled'] ?? false) ? '' : 'opacity-60' }}">
                            <input type="checkbox" name="scopes[]" value="{{ $k }}" class="mt-1" @checked($k === 'marketing:read') @disabled(! ($def['enabled'] ?? false))>
                            <span><b dir="ltr">{{ $k }}</b> <span class="text-slate-500">{{ $t('scope_desc.'.str_replace(':', '_', $k)) }}</span>@unless ($def['enabled'] ?? false) <span class="badge badge-muted">{{ $t('reserved') }}</span>@endunless</span>
                        </label>
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
                <div class="sm:col-span-3"><x-button type="submit">{{ $t('create') }}</x-button></div>
            </form>
            <x-datatable :table="$table" :url="route('marketer.tokens.datatable')" />
        </x-card>
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
