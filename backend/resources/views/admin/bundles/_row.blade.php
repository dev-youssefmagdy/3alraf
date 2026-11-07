<div data-repeater-item class="grid items-end gap-2 rounded-lg border border-slate-200 p-3 sm:grid-cols-12">
    <x-select search class="sm:col-span-8" name="items[{{ $i }}][item]" :label="__('ops.fields.item')" :options="$opts" :value="$r['item'] ?? null" :placeholder="__('admin.select')" />
    <x-input class="sm:col-span-3" name="items[{{ $i }}][quantity]" type="number" min="1" :label="__('orders.fields.qty')" :value="$r['quantity'] ?? 1" />
    <div class="sm:col-span-1"><button type="button" data-repeater-remove class="btn-sm btn-danger">&times;</button></div>
</div>
