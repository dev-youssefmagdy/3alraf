@php
    use App\Enums\FlashDiscountType;
    $fmt = fn ($d) => $d?->format('Y-m-d\TH:i');
    $rows = old('items', $model->items->map(fn ($i) => ['item' => $i->product_id.'-'.$i->product_variant_id, 'discount_type' => $i->discount_type->value, 'value' => $i->value, 'qty_limit' => $i->qty_limit, 'sold' => $i->sold])->all());
    $opts = collect($catalogItems)->mapWithKeys(fn ($o) => [$o['key'] => $o['label']])->all();
@endphp
<x-resource-form :route="$route" :editing="$editing" :model="$model" width="max-w-5xl">
    <x-translatable-tabs name="name" :label="__('admin.fields.name')" :languages="$languages" :values="$model->getTranslations('name')" />
    <div class="grid gap-4 sm:grid-cols-3">
        <x-input name="starts_at" type="datetime-local" :label="__('orders.fields.starts_at')" :value="$fmt($model->starts_at)" required />
        <x-input name="ends_at" type="datetime-local" :label="__('orders.fields.ends_at')" :value="$fmt($model->ends_at)" required />
        <div class="flex items-end"><x-checkbox name="is_active" :label="__('admin.fields.is_active')" :checked="$model->is_active" /></div>
    </div>
    <div data-repeater class="space-y-2 border-t border-slate-100 pt-4">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-slate-700">{{ __('orders.fields.products') }}</h3>
            <button type="button" data-repeater-add class="btn-sm btn-secondary">+ {{ __('ops.add_item') }}</button>
        </div>
        <div data-repeater-list class="space-y-2">
            @foreach ($rows as $k => $r)
                @include('tenant.flash-sales._row', ['i' => $k, 'r' => $r, 'opts' => $opts])
            @endforeach
        </div>
        <template data-repeater-template>@include('tenant.flash-sales._row', ['i' => '__INDEX__', 'r' => null, 'opts' => $opts])</template>
    </div>
</x-resource-form>
