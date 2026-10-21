import $ from 'jquery';

/**
 * <table data-datatable data-url="..." data-columns='[{"data":"name","title":"Name"}]'>
 * Server-side Yajra table with sane defaults (override via data-options JSON).
 */
export function initDataTables(root = document) {
    $(root).find('table[data-datatable]').each(function () {
        const $t = $(this);
        if ($.fn.dataTable.isDataTable(this)) return;
        const extra = $t.data('options') || {};
        $t.DataTable({
            processing: true,
            serverSide: true,
            ajax: { url: $t.data('url'), type: 'GET' },
            columns: $t.data('columns'),
            order: [],
            pageLength: 15,
            lengthMenu: [10, 15, 25, 50, 100],
            language: window.__t?.datatable || {},
            ...extra,
        });
    });
}

$(() => initDataTables());
