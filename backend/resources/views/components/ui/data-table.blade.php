{{-- DataTable (Figma DataTable): server-side Yajra DataTable skinned to the panel tokens. $table = App\DataTables\BaseDataTable, $url = ajax endpoint. The table's filters()
     become the filter toolbar (desktop row / mobile sheet), wired by resources/js/lib/ops-form.js. Every data-* hook and id is unchanged. Below 768 the rows turn into
     cards (data-mobile-card, labels from the column titles; resources/js/lib/datatable.js). Slots: toolbar actions (primary action at the end side). --}}
@props(['table', 'url', 'id' => 'dt', 'mobileCard' => true])
@php($filters = $table->filters())
<div class="min-w-0 md:rounded-2xl md:border md:border-panel-border md:bg-panel-surface md:p-4 md:shadow-panel-xs" data-dt-surface>
<x-ui.table-toolbar :id="$id.'-filters'" data-dt-toolbar>
    @if ($filters)
        <div data-dt-filters class="grid w-full items-end gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6">
            @foreach ($filters as $filter)
                @php($fieldId = $id.'-filter-'.$filter->name)
                <div>
                    <label for="{{ $fieldId }}" class="form-label">{{ $filter->label }}</label>
                    @if ($filter->type === 'select')
                        <select id="{{ $fieldId }}" name="{{ $filter->name }}" data-dt-filter="#{{ $id }}" @if ($filter->searchable) data-search @endif class="form-control">
                            <option value="">{{ __('admin.table_filters.all') }}</option>
                            @foreach ($filter->options() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                        </select>
                    @else
                        <input id="{{ $fieldId }}" type="{{ $filter->type }}" name="{{ $filter->name }}" data-dt-filter="#{{ $id }}" autocomplete="off" class="form-control">
                    @endif
                </div>
            @endforeach
            <div><button type="button" data-dt-filter-reset="#{{ $id }}" class="btn btn-secondary btn-md">{{ __('admin.table_filters.reset') }}</button></div>
        </div>
    @endif
</x-ui.table-toolbar>
<div class="min-w-0">
    <table id="{{ $id }}" data-datatable @if ($mobileCard) data-mobile-card @endif data-url="{{ $url }}" data-columns='@json($table->clientColumns())' class="display w-full"></table>
</div>
</div>
