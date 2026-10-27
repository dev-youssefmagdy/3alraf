<x-layouts.admin guard="tenant" :title="__('growth_hub.clients.title')">
    <div class="space-y-4">
        <x-card :title="__('growth_hub.clients.title')">
            <p class="mb-3 text-sm text-slate-600">{{ __('growth_hub.clients.hint') }} <code class="rounded bg-slate-100 px-1">{{ $base }}</code></p>
            <div id="secret-box" class="mb-4 hidden rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm">
                <p class="font-semibold text-amber-800">{{ __('growth_hub.clients.show_once') }}</p>
                <p class="mt-2">{{ __('growth_hub.clients.token') }}: <code id="secret-token" class="break-all"></code></p>
                <p class="mt-1">{{ __('growth_hub.clients.hmac') }}: <code id="secret-hmac" class="break-all"></code></p>
            </div>
            @can('growth.manage')
                <form data-ajax-form action="{{ route('tenant.growth.clients.store') }}" method="POST" class="mb-6 grid gap-3 sm:grid-cols-3" novalidate>
                    @csrf
                    <x-input name="name" :label="__('growth_hub.fields.name')" required />
                    <x-input name="webhook_url" type="url" :label="__('growth_hub.fields.webhook_url')" :hint="__('growth_hub.clients.webhook_hint')" />
                    <div data-field>
                        <span class="form-label">{{ __('growth_hub.fields.scopes') }}</span>
                        @foreach ($scopes as $k => $desc)
                            <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="scopes[]" value="{{ $k }}" class="mt-1"> <span><b>{{ $k }}</b> <span class="text-slate-500">{{ $desc }}</span></span></label>
                        @endforeach
                    </div>
                    <div class="sm:col-span-3"><x-button type="submit">{{ __('growth_hub.clients.create') }}</x-button></div>
                </form>
            @endcan
            <x-datatable :table="$table" :url="route('tenant.growth.clients.datatable')" />
        </x-card>
    </div>
    @include('tenant.growth._actions')
    <script>
    const showSecret = (d) => { document.getElementById('secret-token').textContent = d.token; document.getElementById('secret-hmac').textContent = d.secret; document.getElementById('secret-box').classList.remove('hidden'); };
    document.addEventListener('growth:secret', (e) => showSecret(e.detail));
    window.jQuery && window.jQuery(document).on('ajax-form:success', 'form', (e, res) => { if (res.data && res.data.token) { showSecret(res.data); window.jQuery('table[data-datatable]').DataTable().ajax.reload(null, false); } });
    </script>
</x-layouts.admin>
