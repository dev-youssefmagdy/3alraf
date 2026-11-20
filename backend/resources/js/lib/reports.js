import $ from 'jquery';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

// Colours come from the panel CSS tokens (series 1 = the panel accent); fixed fallback when a page has no data-panel.
const cssVar = (n, d) => getComputedStyle(document.documentElement).getPropertyValue(n).trim() || d;
const palette = () => [cssVar('--p-accent', '#dc2626'), cssVar('--p-chart-2', '#2563eb'), cssVar('--p-chart-3', '#d97706'), cssVar('--p-chart-4', '#15803d'), cssVar('--p-chart-5', '#9d97ac'), '#ec4899', '#14b8a6', '#f97316', '#84cc16', '#8b5cf6'];
const alpha = (c, a) => (/^#[0-9a-f]{6}$/i.test(c) ? c + Math.round(a * 255).toString(16).padStart(2, '0') : c);
const nf = new Intl.NumberFormat(document.documentElement.lang || undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const fmt = {
    money: (v) => nf.format(parseFloat(v) || 0),
    pct: (v) => nf.format(parseFloat(v) || 0) + '%',
    int: (v) => (parseFloat(v) || 0).toLocaleString(),
};

/** <canvas data-chart='{type,labels,datasets}'> */
export function initCharts(root = document) {
    const rtl = document.documentElement.dir === 'rtl';
    const colors = palette();
    const grid = cssVar('--p-border', '#e5e7eb'), tick = cssVar('--p-text-subtle', '#6b7280'), surface = cssVar('--p-surface', '#fff');
    root.querySelectorAll('canvas[data-chart]').forEach((el) => {
        if (el._chart) return;
        const cfg = JSON.parse(el.dataset.chart);
        const pie = cfg.type === 'doughnut';
        cfg.datasets.forEach((d, i) => {
            d.backgroundColor = pie ? cfg.labels.map((_, k) => colors[k % colors.length]) : colors[i % colors.length];
            if (pie) { d.borderColor = surface; d.borderWidth = 3; d.hoverOffset = 4; }
            if (cfg.type === 'line') { d.borderColor = colors[i % colors.length]; d.tension = 0.3; d.borderWidth = 2.5; d.pointRadius = 0; d.pointHoverRadius = 4; d.backgroundColor = alpha(colors[i % colors.length], 0.12); d.fill = i === 0; }
            if (cfg.type === 'bar') d.borderRadius = 6;
        });
        el._chart = new Chart(el, {
            type: cfg.type,
            data: cfg,
            options: {
                responsive: true, maintainAspectRatio: false, cutout: pie ? '68%' : undefined,
                plugins: { legend: { display: pie || cfg.datasets.length > 1, position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, color: tick, font: { family: 'Cairo, sans-serif' } } }, tooltip: { rtl, textDirection: rtl ? 'rtl' : 'ltr' } },
                scales: pie ? {} : {
                    x: { reverse: rtl, grid: { display: false }, border: { display: false }, ticks: { color: tick, maxRotation: 0, autoSkipPadding: 12 } },
                    y: { position: rtl ? 'right' : 'left', beginAtZero: true, grid: { color: grid }, border: { display: false }, ticks: { color: tick, ...(cfg.integer ? { precision: 0 } : {}) } },
                },
            },
        });
    });
}

// dark / light switch: redraw with the new token values
window.addEventListener('panel:theme', () => { document.querySelectorAll('canvas[data-chart]').forEach((el) => { if (el._chart) { el._chart.destroy(); el._chart = null; } }); initCharts(); });

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
