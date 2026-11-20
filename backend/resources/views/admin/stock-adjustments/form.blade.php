@php
    use App\Enums\{AdjustmentReason, AdjustmentType};
    $rows = old('items', []);
@endphp
<x-resource-form route="admin.stock-adjustments" :editing="false" :model="null" width="max-w-4xl" :title="__('ops.new_adjustment')">
    <div class="grid gap-4 sm:grid-cols-3">
        <x-ui.select name="branch_id" :label="__('ops.fields.branch')" :options="$branches" :placeholder="__('admin.select')" required />
        <x-ui.select name="type" :label="__('ops.fields.type')" :options="AdjustmentType::options()" />
        <x-ui.select name="reason" :label="__('ops.fields.reason')" :options="AdjustmentReason::options()" />
    </div>
    <x-ui.textarea name="note" :label="__('ops.fields.note')" />
    @include('admin.stock._items', ['rows' => $rows, 'items' => $items, 'cost' => true])
</x-resource-form>
