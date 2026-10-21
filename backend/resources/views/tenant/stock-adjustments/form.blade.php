@php
    use App\Enums\{AdjustmentReason, AdjustmentType};
    $rows = old('items', []);
@endphp
<x-resource-form route="tenant.stock-adjustments" :editing="false" :model="null" width="max-w-4xl" :title="__('ops.new_adjustment')">
    <div class="grid gap-4 sm:grid-cols-3">
        <x-select name="branch_id" :label="__('ops.fields.branch')" :options="$branches" :placeholder="__('admin.select')" required />
        <x-select name="type" :label="__('ops.fields.type')" :options="AdjustmentType::options()" />
        <x-select name="reason" :label="__('ops.fields.reason')" :options="AdjustmentReason::options()" />
    </div>
    <x-textarea name="note" :label="__('ops.fields.note')" />
    @include('tenant.stock._items', ['rows' => $rows, 'items' => $items, 'cost' => true])
</x-resource-form>
