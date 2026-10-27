<x-layouts.admin guard="tenant" :title="__('growth_hub.missed.title')">
    @php $m = fn ($k) => __('growth_hub.missed.'.$k); @endphp
    <div class="space-y-4">
        <p class="text-sm text-slate-600">{{ $m('hint') }}</p>
        @can('growth.manage')<form data-ajax-form action="{{ route('tenant.growth.missed.scan') }}" method="POST" novalidate>@csrf<x-button type="submit">{{ $m('scan') }}</x-button></form>@endcan
        <x-card :title="$m('title')"><x-datatable :table="$table" :url="route('tenant.growth.missed.datatable')" /></x-card>
    </div>
    @can('growth.manage')
    <dialog id="missed-edit" class="m-auto rounded-lg p-0 w-full max-w-md backdrop:bg-black/40">
        <form id="missed-edit-form" class="p-4 space-y-3" novalidate>
            <h3 class="font-semibold">{{ __('admin.edit') }}</h3>
            <label class="block text-sm">{{ $m('owner') }}
                <select name="owner" class="w-full border rounded p-2"><option value="">-</option>@foreach ($owners as $o)<option value="{{ $o }}">{{ $o }}</option>@endforeach</select></label>
            <label class="block text-sm">{{ $m('due') }}<input type="date" name="due_on" class="w-full border rounded p-2"></label>
            <label class="block text-sm">{{ __('admin.status') }}
                <select name="status" class="w-full border rounded p-2">@foreach ($m('statuses') as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
            <label class="block text-sm">{{ $m('result') }}<textarea name="result_note" maxlength="500" rows="3" class="w-full border rounded p-2"></textarea></label>
            <div class="flex gap-2 justify-end"><button type="button" class="btn-secondary" data-missed-cancel>{{ __('admin.cancel') }}</button><x-button type="submit">{{ __('admin.save') }}</x-button></div>
        </form>
    </dialog>
    <script>
    (() => {
        const dlg = document.getElementById('missed-edit'), f = document.getElementById('missed-edit-form'); let url = '';
        document.addEventListener('click', (ev) => {
            const b = ev.target.closest('[data-missed-edit]');
            if (b) { url = b.dataset.url; f.owner.value = b.dataset.owner; f.due_on.value = b.dataset.due; f.status.value = b.dataset.status; f.result_note.value = b.dataset.note; dlg.showModal(); }
            if (ev.target.closest('[data-missed-cancel]')) dlg.close();
        });
        f.addEventListener('submit', async (ev) => {
            ev.preventDefault();
            const body = new FormData(f); body.append('_method', 'PUT');
            const res = await fetch(url, {method: 'POST', body, headers: {'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json'}});
            const j = await res.json().catch(() => ({}));
            if (!res.ok) { toastr.error(j.message || (j.errors && Object.values(j.errors)[0][0]) || 'Error'); return; }
            toastr.success(j.message); dlg.close();
            window.jQuery(document.querySelector('table[data-datatable]')).DataTable().ajax.reload(null, false);
        });
    })();
    </script>
    @endcan
    @include('tenant.growth._actions')
</x-layouts.admin>
