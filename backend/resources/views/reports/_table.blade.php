{{-- client-side DataTable over rows already computed by the server ($columns, $rows) --}}
<div class="overflow-x-auto"><table data-static-table data-columns='@json($columns)' data-rows='@json($rows)' class="display w-full"></table></div>
