@php
    $rows = old('items', $model->items->map(fn ($i) => ['item' => $i->product_id.'-'.$i->product_variant_id, 'quantity' => $i->quantity])->all());
    $opts = collect($catalogItems)->mapWithKeys(fn ($o) => [$o['key'] => $o['label']])->all();
@endphp
<x-resource-form :route="$route" :editing="$editing" :model="$model" width="max-w-5xl">
    <x-translatable-tabs name="name" :label="__('admin.fields.name')" :languages="$languages" :values="$model->getTranslations('name')" />
    <x-translatable-tabs name="description" :label="__('admin.fields.description')" :languages="$languages" :values="$model->getTranslations('description')" type="textarea" />
    <div class="grid gap-4 sm:grid-cols-3">
        <x-input name="price" type="number" step="0.01" min="0.01" :label="__('orders.bundle.price')" :value="$model->price" :hint="__('orders.bundle.price_hint')" required />
        <x-input name="starts_at" type="datetime-local" :label="__('orders.fields.starts_at')" :value="$model->starts_at?->format('Y-m-d\TH:i')" />
        <x-input name="ends_at" type="datetime-local" :label="__('orders.fields.ends_at')" :value="$model->ends_at?->format('Y-m-d\TH:i')" />
    </div>
    <div class="grid gap-4 sm:grid-cols-3">
        <x-input name="sort_order" type="number" min="0" :label="__('admin.fields.sort_order')" :value="$model->sort_order ?? 0" />
        <div class="flex items-end"><x-checkbox name="is_active" :label="__('admin.fields.is_active')" :checked="$model->is_active" /></div>
    </div>
    <div data-field>
        <label class="form-label" for="f-image">{{ __('orders.bundle.image') }}</label>
        @if ($model->image)
            <img src="{{ media_url($model->image) }}" class="mb-2 h-20 rounded border object-contain" alt="">
            <x-checkbox name="remove_image" :label="__('ops.remove_image')" :bool="false" />
        @endif
        <input id="f-image" type="file" name="image" accept="image/*" class="form-control">
    </div>
    <div data-repeater class="space-y-2 border-t border-slate-100 pt-4">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-slate-700">{{ __('orders.bundle.components') }}</h3>
            <button type="button" data-repeater-add class="btn-sm btn-secondary">+ {{ __('ops.add_item') }}</button>
        </div>
        <div data-repeater-list class="space-y-2">
            @foreach ($rows as $k => $r)
                @include('tenant.bundles._row', ['i' => $k, 'r' => $r, 'opts' => $opts])
            @endforeach
        </div>
        <template data-repeater-template>@include('tenant.bundles._row', ['i' => '__INDEX__', 'r' => null, 'opts' => $opts])</template>
    </div>
</x-resource-form>
