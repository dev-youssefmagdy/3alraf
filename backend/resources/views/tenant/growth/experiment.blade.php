<x-layouts.admin guard="tenant" :title="$exp->name">
    <div class="space-y-4">
        <x-card :title="$exp->name">
            @can('growth.manage')
                @include('tenant.growth._experiment-form', ['action' => route('tenant.growth.experiments.update', $exp->id), 'method' => 'PUT'])
                <button type="button" data-delete="{{ route('tenant.growth.experiments.destroy', $exp->id) }}" class="btn-sm btn-danger mt-4">{{ __('admin.delete') }}</button>
            @else
                <p class="text-sm text-slate-700">{{ $exp->hypothesis }}</p>
            @endcan
        </x-card>
        <x-card :title="__('growth_hub.experiments.results')">
            @can('growth.manage')
                <form data-ajax-form action="{{ route('tenant.growth.experiments.results', $exp->id) }}" method="POST" class="mb-4 grid gap-3 sm:grid-cols-5" novalidate>
                    @csrf
                    <x-input name="metric" :label="__('growth_hub.fields.metric')" :value="$exp->metric" required />
                    <x-input name="value" type="number" step="0.0001" :label="__('growth_hub.fields.value')" />
                    <x-input name="spend" type="number" step="0.01" min="0" :label="__('growth_hub.fields.spend')" />
                    <x-input name="recorded_on" type="date" :label="__('growth_hub.fields.recorded_on')" :value="now()->toDateString()" />
                    <x-input name="note" :label="__('growth_hub.fields.note')" />
                    <div class="sm:col-span-5"><x-button type="submit">{{ __('growth_hub.experiments.add_result') }}</x-button></div>
                </form>
            @endcan
            <div class="overflow-x-auto"><table class="w-full text-sm">
                <thead><tr class="text-start text-slate-500"><th class="p-2 text-start">{{ __('growth_hub.fields.recorded_on') }}</th><th class="p-2 text-start">{{ __('growth_hub.fields.metric') }}</th><th class="p-2 text-start">{{ __('growth_hub.fields.value') }}</th><th class="p-2 text-start">{{ __('growth_hub.fields.spend') }}</th><th class="p-2 text-start">{{ __('growth_hub.fields.note') }}</th><th class="p-2 text-start">{{ __('growth_hub.experiments.source') }}</th></tr></thead>
                <tbody>@forelse ($exp->results as $r)
                    <tr class="border-t border-slate-100"><td class="p-2">{{ $r->recorded_on->format('Y-m-d') }}</td><td class="p-2">{{ $r->metric }}</td><td class="p-2">{{ $r->value }}</td><td class="p-2">{{ $r->spend ?? '—' }}</td><td class="p-2">{{ $r->note }}</td><td class="p-2">{{ $r->source }}</td></tr>
                @empty <tr><td colspan="6" class="p-3 text-slate-500">{{ __('growth_hub.experiments.no_results') }}</td></tr> @endforelse</tbody>
            </table></div>
            <p class="mt-3 text-xs text-slate-500">{{ __('growth_hub.experiments.roas_note') }}</p>
        </x-card>
    </div>
</x-layouts.admin>
