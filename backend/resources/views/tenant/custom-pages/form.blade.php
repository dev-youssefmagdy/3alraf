@php
    $f = old('filters', $model->filters ?? []);
    $sel = fn ($key) => array_map('strval', (array) ($f[$key] ?? []));
    $pinnedIds = old('product_ids', $pinned);
@endphp
<x-resource-form :route="$route" :editing="$editing" :model="$model" width="max-w-5xl">
    <x-input name="slug" :label="__('admin.fields.slug_short')" :value="$model->slug" :hint="__('growth.custom_slug_hint', ['url' => tenant_url(current_tenant(), '/…')])" required />
    <x-translatable-tabs name="title" :label="__('growth.fields.title')" :languages="$languages" :values="$model->getTranslations('title')" />
    <x-checkbox name="is_active" :label="__('admin.fields.is_active')" :checked="$model->is_active" />

    <div class="space-y-4 border-t border-slate-100 pt-4">
        <h3 class="text-sm font-semibold text-slate-700">{{ __('growth.filters') }}</h3>
        <div class="grid gap-4 md:grid-cols-3">
            <div data-field><label class="form-label">{{ __('growth.fields.categories') }}</label>
                <select name="filters[categories][]" multiple size="6" class="form-control">@foreach ($categories as $id => $label)<option value="{{ $id }}" @selected(in_array((string) $id, $sel('categories')))>{{ $label }}</option>@endforeach</select></div>
            <div data-field><label class="form-label">{{ __('growth.fields.brands') }}</label>
                <select name="filters[brands][]" multiple size="6" class="form-control">@foreach ($brands as $id => $label)<option value="{{ $id }}" @selected(in_array((string) $id, $sel('brands')))>{{ $label }}</option>@endforeach</select></div>
            <div data-field><label class="form-label">{{ __('growth.fields.attributes') }}</label>
                <select name="filters[attribute_values][]" multiple size="6" class="form-control">@foreach ($attributeValues as $id => $label)<option value="{{ $id }}" @selected(in_array((string) $id, $sel('attribute_values')))>{{ $label }}</option>@endforeach</select></div>
        </div>
        <div class="grid gap-4 sm:grid-cols-4">
            <x-input name="filters[price_min]" type="number" step="0.01" min="0" :label="__('growth.fields.price_min')" :value="$f['price_min'] ?? null" />
            <x-input name="filters[price_max]" type="number" step="0.01" min="0" :label="__('growth.fields.price_max')" :value="$f['price_max'] ?? null" />
            <x-select name="filters[sort]" :label="__('growth.fields.sort')" :options="['newest' => __('growth.sort.newest'), 'price_asc' => __('growth.sort.price_asc'), 'price_desc' => __('growth.sort.price_desc'), 'best_selling' => __('growth.sort.best_selling')]" :value="$f['sort'] ?? 'newest'" />
            <div class="flex items-end"><x-checkbox name="filters[flash_sale]" :label="__('growth.fields.flash_sale')" :checked="! empty($f['flash_sale'])" /></div>
        </div>
    </div>

    <div class="space-y-2 border-t border-slate-100 pt-4" data-pin-list data-options='@json($products)'>
        <h3 class="text-sm font-semibold text-slate-700">{{ __('growth.pinned_products') }}</h3>
        <p class="text-xs text-slate-500">{{ __('growth.pinned_hint') }}</p>
        <div class="flex gap-2">
            <select data-pin-select class="form-control"><option value="">{{ __('admin.select') }}</option>@foreach ($products as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach</select>
            <button type="button" data-pin-add class="btn btn-secondary">{{ __('growth.pin') }}</button>
        </div>
        <ul data-pin-items class="space-y-1">
            @foreach ($pinnedIds as $pid)
                <li data-pin-item class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-1.5 text-sm">
                    <input type="hidden" name="product_ids[]" value="{{ $pid }}"><span class="flex-1">{{ $products[$pid] ?? $pid }}</span>
                    <button type="button" data-pin-up class="btn-sm btn-secondary">↑</button><button type="button" data-pin-down class="btn-sm btn-secondary">↓</button><button type="button" data-pin-remove class="btn-sm btn-danger">&times;</button>
                </li>
            @endforeach
        </ul>
    </div>
</x-resource-form>
