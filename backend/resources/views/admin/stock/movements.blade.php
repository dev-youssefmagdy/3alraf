<x-layouts.admin :title="__('ops.stock_movements')">
    <x-slot:header><x-ui.page-header :title="__('ops.stock_movements')"><select name="branch_id" data-dt-filter="#movements-table" class="form-control !w-auto !py-1.5">
                <option value="">{{ __('ops.all_branches') }}</option>
                @foreach ($branches as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
            </select>
            <select name="type" data-dt-filter="#movements-table" class="form-control !w-auto !py-1.5">
                <option value="">{{ __('ops.all_types') }}</option>
                @foreach ($types as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
            </select></x-ui.page-header></x-slot:header>
        <div class="space-y-4">
        <h2 class="text-panel-h4 font-bold text-panel-text">{{ __('ops.ledger') }}</h2>
        <x-ui.data-table :table="$table" :url="route('admin.stock.movements.datatable')" id="movements-table" />
        </div>
</x-layouts.admin>
