import TomSelect from 'tom-select';

// Any <select data-search> becomes a searchable dropdown. The native <select> stays the source of truth
// (value + "change" event), so forms and existing scripts keep working unchanged.
const init = (root = document) => {
    root.querySelectorAll('select[data-search]:not(.tomselected)').forEach((el) => {
        new TomSelect(el, {
            allowEmptyOption: true,
            maxOptions: null,
            create: false,
            controlInput: '<input>',
            plugins: el.multiple ? ['remove_button'] : [],
            render: { no_results: () => `<div class="no-results">${document.documentElement.lang === 'ar' ? 'لا توجد نتائج' : 'No results'}</div>` },
        });
    });
};

document.addEventListener('DOMContentLoaded', () => init());
window.initSearchSelect = init;
