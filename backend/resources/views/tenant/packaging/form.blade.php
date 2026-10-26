<x-resource-form :route="$route" :editing="$editing" :model="$model" width="max-w-3xl">
    <x-translatable-tabs name="name" :label="__('admin.fields.name')" :languages="$languages" :values="$model->getTranslations('name')" />
    <x-translatable-tabs name="description" :label="__('admin.fields.description')" :languages="$languages" :values="$model->getTranslations('description')" type="textarea" />
    <div class="grid gap-4 sm:grid-cols-3">
        <x-input name="price" type="number" step="0.01" min="0" :label="__('orders.packaging.price')" :value="$model->price" :hint="__('orders.packaging.price_hint')" required />
        <x-input name="cost" type="number" step="0.01" min="0" :label="__('orders.packaging.cost')" :value="$model->cost" :hint="__('orders.packaging.cost_hint')" />
        <x-input name="sort_order" type="number" min="0" :label="__('admin.fields.sort_order')" :value="$model->sort_order ?? 0" />
    </div>
    <x-checkbox name="is_active" :label="__('admin.fields.is_active')" :checked="$model->is_active" />
    <div data-field>
        <label class="form-label" for="f-image">{{ __('orders.packaging.image') }} <span class="text-red-600">*</span></label>
        @if ($model->image)
            <img src="{{ media_url($model->image) }}" class="mb-2 h-20 rounded border object-contain" alt="">
        @endif
        <input id="f-image" type="file" name="image" accept="image/jpeg,image/png,image/webp" class="form-control" @required(! $model->image)>
        <p class="mt-1 text-xs text-slate-500">{{ __('orders.packaging.image_hint') }}</p>
    </div>
</x-resource-form>
