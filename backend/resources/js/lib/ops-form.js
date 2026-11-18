import $ from 'jquery';
import toastr from 'toastr';

// payment method form: show the fields that belong to the chosen type / provider
function syncPayment() {
    const $f = $('[data-payment-form]');
    if (!$f.length) return;
    const type = $f.find('[name=type]').val();
    const $prov = $f.find('[name=provider]');
    $prov.find('option').each(function () { const ok = $(this).data('type') === type; $(this).prop('hidden', !ok).prop('disabled', !ok); });
    if ($prov.find('option:selected').prop('disabled')) $prov.val($prov.find('option:not(:disabled)').first().val());
    const provider = $prov.val();
    $f.find('[data-for-type]').each(function () { const on = String($(this).data('for-type')).split(' ').includes(type); $(this).toggle(on).find('input,select,textarea').prop('disabled', !on); });
    $f.find('[data-for-provider]').each(function () { const on = $(this).data('for-provider') === provider && type === 'online'; $(this).toggle(on).find('input').prop('disabled', !on); });
}
$(document).on('change', '[data-payment-form] [name=type], [data-payment-form] [name=provider]', syncPayment);
$(syncPayment);

// stock document item rows: pick an item -> prefill suggested cost
$(document).on('change', '[data-item-select]', function () {
    const cost = $(this).find('option:selected').data('cost');
    const $c = $(this).closest('[data-repeater-item]').find('[data-item-cost]');
    if ($c.length && !$c.val() && cost !== undefined) $c.val(cost);
});

// POST action buttons (receive / send / cancel): <button data-post-action="url" data-confirm="msg">
$(document).on('click', '[data-post-action]', function () {
    const $b = $(this);
    if ($b.data('confirm') && !window.confirm($b.data('confirm'))) return;
    $b.prop('disabled', true);
    $.ajax({ url: $b.data('post-action'), method: 'POST' })
        .done((res) => { if (res.message) toastr.success(res.message); setTimeout(() => (res.redirect ? (location.href = res.redirect) : location.reload()), 500); })
        .fail((xhr) => { const e = xhr.responseJSON?.errors; toastr.error(e ? Object.values(e)[0][0] : xhr.responseJSON?.message || 'Error'); $b.prop('disabled', false); });
});

// table filters reload the datatable; the table's data-url keeps its fixed parameters (e.g. drafts=1)
const dtFilterFields = (table) => $('[data-dt-filter="' + table + '"]');
const reloadDtFilters = (table) => {
    const params = {};
    dtFilterFields(table).each(function () { if (this.value.trim()) params[this.name] = this.value.trim(); });
    const base = String($(table).data('url')), query = $.param(params);
    const url = query ? base + (base.includes('?') ? '&' : '?') + query : base;
    const dt = $(table).DataTable();
    if (dt.ajax.url() !== url) dt.ajax.url(url).load();
};
let dtFilterTimer;
$(document).on('change', '[data-dt-filter]', function () { clearTimeout(dtFilterTimer); reloadDtFilters($(this).data('dt-filter')); });
$(document).on('input', 'input[data-dt-filter]:not([type=date])', function () {
    const table = $(this).data('dt-filter');
    clearTimeout(dtFilterTimer);
    dtFilterTimer = setTimeout(() => reloadDtFilters(table), 300);
});
$(document).on('click', '[data-dt-filter-reset]', function () {
    const table = $(this).data('dt-filter-reset');
    clearTimeout(dtFilterTimer);
    dtFilterFields(table).each(function () { if (this.tomselect) this.tomselect.clear(true); else this.value = ''; });
    reloadDtFilters(table);
});
