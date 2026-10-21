@php
    use App\Enums\{DiscountScope, DiscountType};
    $fmt = fn ($d) => $d?->format('Y-m-d\TH:i');
@endphp
<x-resource-form :route="$route" :editing="$editing" :model="$model" width="max-w-3xl" data-discount-form>
    <div class="grid gap-4 sm:grid-cols-3">
        <x-input name="code" class="uppercase" :label="__('orders.fields.code')" :value="$model->code" required />
        <x-select name="type" :label="__('ops.fields.type')" :options="DiscountType::options()" :value="$model->type?->value ?? $model->type" />
        <x-input name="value" type="number" step="0.01" min="0" :label="__('orders.fields.value')" :value="$model->value" />
        <x-input name="min_order" type="number" step="0.01" min="0" :label="__('orders.fields.min_order')" :value="$model->min_order ?? 0" />
        <x-input name="max_discount" type="number" step="0.01" min="0" :label="__('orders.fields.max_discount')" :value="$model->max_discount" />
        <div class="flex items-end"><x-checkbox name="free_shipping" :label="__('orders.fields.free_shipping')" :checked="$model->free_shipping" /></div>
        <x-input name="usage_limit" type="number" min="1" :label="__('orders.fields.usage_limit')" :value="$model->usage_limit" />
        <x-input name="per_customer_limit" type="number" min="1" :label="__('orders.fields.per_customer_limit')" :value="$model->per_customer_limit" />
        <div class="flex items-end"><x-checkbox name="is_active" :label="__('admin.fields.is_active')" :checked="$model->is_active" /></div>
        <x-input name="starts_at" type="datetime-local" :label="__('orders.fields.starts_at')" :value="$fmt($model->starts_at)" />
        <x-input name="ends_at" type="datetime-local" :label="__('orders.fields.ends_at')" :value="$fmt($model->ends_at)" />
    </div>
    <div class="space-y-3 border-t border-slate-100 pt-4">
        <x-select name="scope" :label="__('orders.fields.scope')" :options="DiscountScope::options()" :value="$model->scope?->value ?? $model->scope" data-scope-select />
        <div data-scope-pane="products" data-field>
            <label class="form-label" for="f-product_ids">{{ __('orders.fields.products') }}</label>
            <select id="f-product_ids" name="product_ids[]" multiple size="8" class="form-control">
                @foreach ($products as $id => $name)<option value="{{ $id }}" @selected(in_array($id, $model->product_ids ?? []))>{{ $name }}</option>@endforeach
            </select>
        </div>
        <div data-scope-pane="categories" data-field>
            <label class="form-label" for="f-category_ids">{{ __('orders.fields.categories') }}</label>
            <select id="f-category_ids" name="category_ids[]" multiple size="8" class="form-control">
                @foreach ($categories as $id => $name)<option value="{{ $id }}" @selected(in_array($id, $model->category_ids ?? []))>{{ $name }}</option>@endforeach
            </select>
        </div>
    </div>
</x-resource-form>
