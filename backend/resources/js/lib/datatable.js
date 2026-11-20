import $ from 'jquery';

/**
 * <table data-datatable data-url="..." data-columns='[{"data":"name","title":"Name"}]'>
 * Server-side Yajra table with sane defaults (override via data-options JSON).
 */
/** Below 768 the rows are cards (CSS, [data-mobile-card]); each cell shows its column title through data-label. */
function labelCells(table) {
    const heads = [...table.querySelectorAll('thead th')].map((th) => (th.textContent || '').trim());
    table.querySelectorAll('tbody tr').forEach((tr) => [...tr.children].forEach((td, i) => { if (heads[i]) td.setAttribute('data-label', heads[i]); }));
}

export function initDataTables(root = document) {
    $(root).find('table[data-datatable]').each(function () {
        const $t = $(this);
        if ($.fn.dataTable.isDataTable(this)) return;
        const extra = $t.data('options') || {};
        const userDraw = extra.drawCallback;
        delete extra.drawCallback;
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
            drawCallback() { labelCells($t[0]); if (userDraw) userDraw.apply(this, arguments); },
        });
    });
}

$(() => initDataTables());
