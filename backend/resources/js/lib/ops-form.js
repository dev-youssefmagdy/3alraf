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

// stock/movement filters reload the datatable
$(document).on('change', '[data-dt-filter]', function () {
    const $t = $($(this).data('dt-filter')).DataTable();
    const params = {};
    $('[data-dt-filter="' + $(this).data('dt-filter') + '"]').each(function () { if (this.value) params[this.name] = this.value; });
    const base = $t.ajax.url().split('?')[0];
    $t.ajax.url(base + '?' + $.param(params)).load();
});
