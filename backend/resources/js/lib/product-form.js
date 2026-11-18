import $ from 'jquery';
import { clearErrors, showErrors } from './ajax-form';
import { priceFromProfit, profitFromPrice } from './product-pricing';

/**
 * Product form: toggles simple/variable sections and builds the variant matrix into the variants repeater.
 * Attributes are added one at a time (searchable picker) into groups; the cartesian product of the ticked values is generated.
 * Existing combinations are never duplicated (matched on sorted value-id hash, same as ProductVariant::hashFor).
 * The picker inputs have no `name`: only the generated variant rows are submitted.
 */
const hashOf = (ids) => [...ids].map(Number).sort((a, b) => a - b).join('-');
const esc = (s) => $('<div>').text(s ?? '').html();
const t = (k, fallback) => window.__t?.[k] || fallback;

function toggleType() {
    const variable = $('#f-type').val() === 'variable';
    $('#variant-section').toggle(variable).find('input,select,button').not('[data-generate]').prop('disabled', !variable);
    $('[data-simple-section]').toggle(!variable);
    if (variable) { syncPicker(); refreshSummary(); }
}

function refreshEmpty() {
    $('#variant-repeater [data-empty]').toggle($('#variant-repeater [data-repeater-item]').length === 0);
    refreshSummary();
}

const $gen = () => $('#variant-generator');
const attrsById = () => new Map(($gen().data('attributes') || []).map((a) => [Number(a.id), a]));
const addedIds = () => $gen().find('[data-attr-group]').map((_, el) => Number($(el).data('attr-group'))).get();
const existingHashes = () => new Set($('#variant-repeater [data-repeater-item]').map((_, el) => String($(el).data('hash'))).get());

/** Ticked values per added group, in the order the groups were added (empty groups are skipped). */
function pickedGroups() {
    const byId = attrsById();
    return addedIds().map((id) => {
        const a = byId.get(id);
        const picked = $gen().find('[data-pick="' + id + '"]:checked').map((_, el) => Number(el.value)).get();
        return { attr: a, values: picked.map((vid) => ({ id: vid, name: a.values.find((v) => Number(v.id) === vid).name })) };
    }).filter((g) => g.values.length);
}

const combosOf = (groups) => groups.reduce((acc, g) => acc.flatMap((c) => g.values.map((v) => [...c, v])), [[]]);

/** Keep the "add attribute" picker in sync: only attributes that are not added yet. */
function syncPicker() {
    const sel = $gen().find('[data-attr-add]')[0];
    if (!sel) return;
    const added = new Set(addedIds());
    const free = ($gen().data('attributes') || []).filter((a) => !added.has(Number(a.id)));
    const ts = sel.tomselect;
    if (ts) {
        ts.clear(true);
        ts.clearOptions();
        free.forEach((a) => ts.addOption({ value: String(a.id), text: a.name }));
        ts.refreshOptions(false);
        free.length ? ts.enable() : ts.disable();
    } else {
        $(sel).find('option:not([value=""])').remove();
        free.forEach((a) => $(sel).append($('<option>').val(a.id).text(a.name)));
        $(sel).val('').prop('disabled', !free.length);
    }
}

function groupHtml(a, checked) {
    const chips = a.values.map((v) => {
        const sw = v.color ? '<span class="inline-block size-3 rounded-full border" style="background:' + esc(v.color) + '"></span>' : '';
        return '<label class="inline-flex cursor-pointer items-center gap-1 rounded-full border border-slate-200 px-2 py-1 text-xs has-[:checked]:border-brand-400 has-[:checked]:bg-brand-50">'
            + '<input type="checkbox" data-pick="' + a.id + '" value="' + v.id + '"' + (checked.has(Number(v.id)) ? ' checked' : '') + '> ' + sw + esc(v.name) + '</label>';
    }).join(' ');
    const empty = a.values.length ? '' : '<p class="text-xs text-slate-500">' + esc(t('vb_no_values', 'This attribute has no values yet.')) + '</p>';
    return '<div data-attr-group="' + a.id + '" class="rounded-lg border border-slate-200 p-3">'
        + '<div class="mb-2 flex flex-wrap items-center gap-2">'
        + '<span class="text-sm font-semibold">' + esc(a.name) + '</span>'
        + '<span data-attr-count dir="ltr" class="badge badge-muted"></span>'
        + '<span class="ms-auto flex items-center gap-1">'
        + '<button type="button" data-attr-all class="btn-sm btn-secondary">' + esc(t('vb_select_all', 'Select all')) + '</button>'
        + '<button type="button" data-attr-none class="btn-sm btn-secondary">' + esc(t('vb_clear', 'Clear')) + '</button>'
        + '<button type="button" data-attr-remove class="btn-sm btn-secondary" aria-label="' + esc(t('vb_remove', 'Remove')) + '" title="' + esc(t('vb_remove', 'Remove')) + '">&times;</button>'
        + '</span></div>'
        + '<div class="flex flex-wrap gap-2">' + chips + '</div>' + empty + '</div>';
}

function addGroup(id, checked = new Set()) {
    const a = attrsById().get(Number(id));
    if (!a || addedIds().includes(Number(id))) return;
    $gen().find('[data-attr-groups]').append(groupHtml(a, checked));
    syncPicker();
    refreshSummary();
}

/** Group counters, empty state, live "A (2) × B (3) = 6 combinations · 4 new" line, Generate button state. */
function refreshSummary() {
    const $g = $gen();
    if (!$g.length) return;
    $g.find('[data-attr-group]').each(function () {
        const $grp = $(this), total = $grp.find('[data-pick]').length, on = $grp.find('[data-pick]:checked').length;
        $grp.find('[data-attr-count]').text(on + ' / ' + total);
    });
    $g.find('[data-attr-empty]').toggle(addedIds().length === 0);

    const groups = pickedGroups();
    const $sum = $g.find('[data-attr-summary]');
    let fresh = 0;
    if (groups.length) {
        const have = existingHashes();
        const combos = combosOf(groups);
        fresh = combos.filter((c) => !have.has(hashOf(c.map((v) => v.id)))).length;
        const formula = groups.map((g) => esc(g.attr.name) + ' (' + g.values.length + ')').join(' × ');
        $sum.html(formula + ' = <strong>' + combos.length + '</strong> ' + esc(t('vb_combinations', 'combinations')) + ' · <strong>' + fresh + '</strong> ' + esc(t('vb_new', 'new')));
    } else {
        $sum.text(addedIds().length ? t('pick_values', 'Select attribute values first.') : '');
    }
    $g.find('[data-generate]').prop('disabled', fresh === 0 || $('#f-type').val() !== 'variable');
}

function initBuilder() {
    const $g = $gen();
    const byId = attrsById();
    const used = new Set(($g.data('used') || []).map(Number));
    syncPicker();
    // editing: pre-add the attributes the existing variants use, with their values ticked
    byId.forEach((a) => { if (a.values.some((v) => used.has(Number(v.id)))) addGroup(a.id, used); });
    refreshSummary();
}

function generate() {
    const groups = pickedGroups();
    if (!groups.length) return window.toastr?.warning(t('pick_values', 'Select attribute values first.'));

    const $rep = $('#variant-repeater');
    const have = existingHashes();
    const baseSku = ($('#f-sku').val() || 'SKU').trim();
    let added = 0;

    combosOf(groups).forEach((combo) => {
        const ids = combo.map((c) => c.id);
        const hash = hashOf(ids);
        if (have.has(hash)) return;
        const tpl = $rep.find('[data-repeater-template]').first().html().replaceAll('__INDEX__', 'n' + Date.now() + added);
        const $row = $(tpl).find('tr').first();
        $row.attr('data-hash', hash).data('hash', hash);
        $row.find('[data-label]').text(combo.map((c) => c.name).join(' / '));
        const idx = $row.find('input[name$="[id]"]').attr('name').match(/variants\[(.*?)\]/)[1];
        $row.find('[data-values]').html(ids.map((id) => '<input type="hidden" name="variants[' + idx + '][values][]" value="' + id + '">').join(''));
        $row.find('input[name$="[sku]"]').val(baseSku + '-' + ids.join('-'));
        $rep.find('[data-repeater-list]').append($row);
        have.add(hash);
        added++;
    });
    refreshEmpty();
    if (added) window.toastr?.success(t('vb_generated', ':n variants added.').replace(':n', added));
    else window.toastr?.info(t('nothing_new', 'No new combinations.'));
}

$(() => {
    if (!$('#variant-generator').length) return;
    initBuilder();
    toggleType();
    refreshEmpty();
    $(document).on('change', '#f-type', toggleType);
    $(document).on('change', '#variant-generator [data-attr-add]', function () { if (this.value) addGroup(this.value); });
    $(document).on('change', '#variant-generator [data-pick]', refreshSummary);
    $(document).on('click', '#variant-generator [data-attr-all]', function () { $(this).closest('[data-attr-group]').find('[data-pick]').prop('checked', true); refreshSummary(); });
    $(document).on('click', '#variant-generator [data-attr-none]', function () { $(this).closest('[data-attr-group]').find('[data-pick]').prop('checked', false); refreshSummary(); });
    $(document).on('click', '#variant-generator [data-attr-remove]', function () { $(this).closest('[data-attr-group]').remove(); syncPicker(); refreshSummary(); });
    $(document).on('click', '#variant-generator [data-generate]', generate);
    $(document).on('repeater:changed', '#variant-repeater', refreshEmpty);
});

/*
 * Profit % (never submitted): typing it sets price and sale price from the cost; editing the price recalculates it.
 * A typed profit is kept when the cost changes (prices follow). A variant without its own cost uses the product's.
 */
const priceField = ($group, name) => $group.find('[name="' + name + '"],[name$="[' + name + ']"]');
const productGroup = () => $('[data-price-group="product"]');
const costOf = ($group) => priceField($group, 'cost').val() || priceField(productGroup(), 'cost').val();

function showProfit($group) {
    $group.data('profitTyped', false).find('[data-profit]').val(profitFromPrice(costOf($group), priceField($group, 'price').val()) ?? '');
}

function applyProfit($group) {
    const price = priceFromProfit(costOf($group), $group.find('[data-profit]').val());
    if (price === null) return;
    // the extra argument marks this as our own update; the event still clears a stale inline error
    ['price', 'sale_price'].forEach((name) => priceField($group, name).val(price).trigger('input', [true]));
}

const syncProfit = ($group) => ($group.data('profitTyped') ? applyProfit($group) : showProfit($group));

$(document).on('input', '[data-price-group] [data-profit]', function () {
    const $group = $(this).closest('[data-price-group]');
    $group.data('profitTyped', this.value !== '');
    applyProfit($group);
});
$(document).on('input', '[data-price-group] [name="price"],[data-price-group] [name$="[price]"]', function (e, own) {
    if (!own) showProfit($(this).closest('[data-price-group]'));
});
$(document).on('input', '[data-price-group] [name="cost"],[data-price-group] [name$="[cost]"]', function () {
    const $group = $(this).closest('[data-price-group]');
    syncProfit($group);
    if ($group.is(productGroup())) {
        $('[data-price-group="variant"]').filter((_, row) => !priceField($(row), 'cost').val()).each((_, row) => syncProfit($(row)));
    }
});
$(() => $('[data-price-group]').each((_, group) => showProfit($(group))));

/* Product edit page: gallery videos + shared image/video order (P8). Every answer carries the re-rendered card; the product form itself stays untouched. */
const swapMediaCard = (res) => {
    if (res.message) window.toastr?.success(res.message);
    if (res.data?.html) $('[data-product-media]').replaceWith(res.data.html);
};
const mediaError = (xhr) => {
    const errs = xhr.responseJSON?.errors;
    window.toastr?.error(errs ? Object.values(errs)[0][0] : xhr.responseJSON?.message || window.__t?.error || 'Error');
};

$(document).on('submit', '[data-product-video-form]', function (e) {
    e.preventDefault();
    const $form = $(this), $card = $form.closest('[data-product-media]'), $progress = $form.find('[data-video-progress]'), $submit = $form.find('[type=submit]');
    const file = $form.find('[name=video]')[0].files[0];
    const tooLarge = () => showErrors($form, { video: [$card.data('too-large')] });
    clearErrors($form);
    if (file && file.size > Number($card.data('max-bytes'))) return tooLarge(); // before spending minutes on an upload the server must refuse

    $submit.prop('disabled', true).addClass('opacity-60');
    $progress.removeClass('hidden').val(0);
    $.ajax({
        url: $form.attr('action'), method: 'POST', data: new FormData(this), processData: false, contentType: false,
        xhr: () => { const x = new XMLHttpRequest(); x.upload.addEventListener('progress', (ev) => { if (ev.lengthComputable) $progress.val(Math.round((ev.loaded / ev.total) * 100)); }); return x; },
    }).done(swapMediaCard).fail((xhr) => {
        if (xhr.status === 422 && xhr.responseJSON?.errors) showErrors($form, xhr.responseJSON.errors);
        else if (xhr.status === 413) tooLarge(); // refused by PHP (post_max_size) or the web server before the app could validate it
        else mediaError(xhr);
        $submit.prop('disabled', false).removeClass('opacity-60');
        $progress.addClass('hidden');
    });
});

$(document).on('click', '[data-media-move]', function () {
    const $card = $(this).closest('[data-product-media]'), $item = $(this).closest('[data-media-key]');
    if (Number($(this).data('media-move')) < 0) $item.prev().before($item); else $item.next().after($item);
    const order = $card.find('[data-media-key]').map((_, el) => $(el).attr('data-media-key')).get();
    $card.find('[data-media-move]').prop('disabled', true);
    $.post($card.data('order-url'), { order }).done(swapMediaCard).fail((xhr) => { mediaError(xhr); setTimeout(() => location.reload(), 1200); });
});

$(document).on('click', '[data-media-delete]', function () {
    if (!window.confirm(window.__t?.confirm_delete || 'Delete this record?')) return;
    $.post($(this).data('media-delete'), { _method: 'DELETE' }).done(swapMediaCard).fail(mediaError);
});
