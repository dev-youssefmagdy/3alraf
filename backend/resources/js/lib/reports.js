import $ from 'jquery';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

const palette = ['#dc2626', '#0ea5e9', '#10b981', '#f59e0b', '#8b5cf6', '#64748b', '#ec4899', '#14b8a6', '#f97316', '#84cc16'];
const nf = new Intl.NumberFormat(document.documentElement.lang || undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const fmt = {
    money: (v) => nf.format(parseFloat(v) || 0),
    pct: (v) => nf.format(parseFloat(v) || 0) + '%',
    int: (v) => (parseFloat(v) || 0).toLocaleString(),
};

/** <canvas data-chart='{type,labels,datasets}'> */
export function initCharts(root = document) {
    root.querySelectorAll('canvas[data-chart]').forEach((el) => {
        if (el._chart) return;
        const cfg = JSON.parse(el.dataset.chart);
        const pie = cfg.type === 'doughnut';
        cfg.datasets.forEach((d, i) => {
            d.backgroundColor = pie ? cfg.labels.map((_, k) => palette[k % palette.length]) : palette[i % palette.length];
            if (cfg.type === 'line') { d.borderColor = palette[i % palette.length]; d.tension = 0.3; d.backgroundColor = palette[i % palette.length] + '22'; d.fill = i === 0; }
        });
        el._chart = new Chart(el, {
            type: cfg.type,
            data: cfg,
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: pie || cfg.datasets.length > 1, position: 'bottom' } }, scales: pie ? {} : { y: { beginAtZero: true } } },
        });
    });
}

/** <table data-static-table data-columns='[{data,title,type}]' data-rows='[...]'> : client-side DataTable over server-computed rows. */
export function initStaticTables(root = document) {
    $(root).find('table[data-static-table]').each(function () {
        if ($.fn.dataTable.isDataTable(this)) return;
        const $t = $(this);
        const columns = ($t.data('columns') || []).map((c) => ({
            data: c.data, title: c.title, className: ['money', 'int', 'pct'].includes(c.type) ? 'text-end' : '',
            render: (v, type) => {
                if (v === null || v === undefined) return '';
                if (fmt[c.type] && type !== 'sort' && type !== 'type') return fmt[c.type](v);
                return ['money', 'int', 'pct'].includes(c.type) ? (parseFloat(v) || 0) : v;
            },
        }));
        $t.DataTable({ data: $t.data('rows') || [], columns, order: [], pageLength: 15, lengthMenu: [10, 15, 25, 50, 100], language: window.__t?.datatable || {} });
    });
}

$(() => { initCharts(); initStaticTables(); });
