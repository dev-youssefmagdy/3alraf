<div data-repeater-item class="grid items-end gap-2 rounded-lg border border-slate-200 p-3 sm:grid-cols-12">
    <x-select search class="sm:col-span-5" name="items[{{ $i }}][item]" :label="__('ops.fields.item')" :options="$opts" :value="$r['item'] ?? null" :placeholder="__('admin.select')" />
    <x-select class="sm:col-span-2" name="items[{{ $i }}][discount_type]" :label="__('ops.fields.type')" :options="\App\Enums\FlashDiscountType::options()" :value="$r['discount_type'] ?? 'price'" />
    <x-input class="sm:col-span-2" name="items[{{ $i }}][value]" type="number" step="0.01" min="0" :label="__('orders.fields.value')" :value="$r['value'] ?? null" />
    <x-input class="sm:col-span-2" name="items[{{ $i }}][qty_limit]" type="number" min="1" :label="__('orders.fields.qty_limit')" :value="$r['qty_limit'] ?? null" :hint="isset($r['sold']) ? __('orders.fields.sold').': '.$r['sold'] : null" />
    <div class="sm:col-span-1"><button type="button" data-repeater-remove class="btn-sm btn-danger">&times;</button></div>
</div>
