<x-layouts.admin guard="central" :title="__('ai.menu.providers')">
    <x-card>
        <x-slot:actions>
            @can('ai_providers.create')<x-button :href="route('central.ai.providers.create')">{{ __('admin.add_new') }}</x-button>@endcan
        </x-slot:actions>
        <x-datatable :table="$table" :url="route('central.ai.providers.datatable')" id="ai-providers-table" />
    </x-card>
    <script>
        document.addEventListener('click', function (e) {
            const b = e.target.closest('[data-ai-test]');
            if (!b) return;
            b.disabled = true;
            fetch(b.dataset.aiTest, { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '', 'Accept': 'application/json' } })
                .then(r => r.json().then(j => ({ ok: r.ok, j })))
                .then(({ ok, j }) => { (ok ? toastr.success : toastr.error)(j.message || ''); $('#ai-providers-table').DataTable().ajax.reload(null, false); })
                .catch(() => toastr.error(@json(__('ai.errors.provider'))))
                .finally(() => { b.disabled = false; });
        });
    </script>
</x-layouts.admin>
