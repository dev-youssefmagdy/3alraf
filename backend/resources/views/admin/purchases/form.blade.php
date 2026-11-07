@php
    $rows = old('items', $po->items->map(fn ($i) => ['item' => $i->product_id.'-'.$i->product_variant_id, 'quantity' => $i->quantity, 'unit_cost' => $i->unit_cost])->all());
@endphp
<x-resource-form route="tenant.purchases" :editing="$editing" :model="$po" width="max-w-5xl" :title="$editing ? __('admin.edit').': '.$po->number : __('ops.new_purchase')">
    <div class="grid gap-4 sm:grid-cols-3">
        <x-select name="supplier_id" :label="__('ops.fields.supplier')" :options="$suppliers" :value="$po->supplier_id" :placeholder="__('admin.select')" required />
        <x-select name="branch_id" :label="__('ops.fields.branch')" :options="$branches" :value="$po->branch_id" :placeholder="__('admin.select')" required />
        <x-input name="ordered_at" type="date" :label="__('ops.fields.ordered_at')" :value="$po->ordered_at?->toDateString()" required />
        <x-input name="shipping_cost" type="number" step="0.01" min="0" :label="__('ops.fields.shipping_cost')" :value="$po->shipping_cost ?? 0" />
        <x-input name="other_cost" type="number" step="0.01" min="0" :label="__('ops.fields.other_cost')" :value="$po->other_cost ?? 0" />
    </div>
    <x-textarea name="note" :label="__('ops.fields.note')" :value="$po->note" />
    @include('tenant.stock._items', ['rows' => $rows, 'items' => $items, 'cost' => true])
    @can('purchases.receive')<x-checkbox name="receive" :label="__('ops.save_and_receive')" :checked="false" />@endcan
</x-resource-form>
