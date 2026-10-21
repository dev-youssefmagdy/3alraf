@php
    $rows = old('items', $t->items->map(fn ($i) => ['item' => $i->product_id.'-'.$i->product_variant_id, 'quantity' => $i->quantity])->all());
@endphp
<x-resource-form route="tenant.stock-transfers" :editing="$editing" :model="$t" width="max-w-4xl" :title="$editing ? __('admin.edit').': '.$t->number : __('ops.new_transfer')">
    <div class="grid gap-4 sm:grid-cols-2">
        <x-select name="from_branch_id" :label="__('ops.fields.from_branch')" :options="$branches" :value="$t->from_branch_id" :placeholder="__('admin.select')" required />
        <x-select name="to_branch_id" :label="__('ops.fields.to_branch')" :options="$branches" :value="$t->to_branch_id" :placeholder="__('admin.select')" required />
    </div>
    <x-textarea name="note" :label="__('ops.fields.note')" :value="$t->note" />
    @include('tenant.stock._items', ['rows' => $rows, 'items' => $items, 'cost' => false])
</x-resource-form>
