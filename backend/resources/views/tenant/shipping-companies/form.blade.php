@php
    $rows = old('rates', $model->rates->map(fn ($r) => $r->only(['city_id', 'price', 'cost', 'free_over', 'eta_min_days', 'eta_max_days', 'supports_cod']))->all());
@endphp
<x-resource-form :route="$route" :editing="$editing" :model="$model" width="max-w-5xl">
    <x-translatable-tabs name="name" :label="__('admin.fields.name')" :languages="$languages" :values="$model->getTranslations('name')" />
    <div class="grid gap-4 sm:grid-cols-2">
        <x-input name="sort_order" type="number" min="0" :label="__('admin.fields.sort_order')" :value="$model->sort_order ?? 0" />
        <div class="flex items-end"><x-checkbox name="is_active" :label="__('admin.fields.is_active')" :checked="$model->is_active" /></div>
    </div>
    <div data-repeater class="space-y-2 border-t border-slate-100 pt-4">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-slate-700">{{ __('ops.rates') }}</h3>
            <button type="button" data-repeater-add class="btn-sm btn-secondary">+ {{ __('ops.add_rate') }}</button>
        </div>
        <div data-repeater-list class="space-y-2">
            @foreach ($rows as $k => $r)
                @include('tenant.shipping-companies._rate-row', ['i' => $k, 'r' => $r, 'cities' => $cities])
            @endforeach
        </div>
        <template data-repeater-template>@include('tenant.shipping-companies._rate-row', ['i' => '__INDEX__', 'r' => ['supports_cod' => true], 'cities' => $cities])</template>
    </div>
</x-resource-form>
