import $ from 'jquery';
import { clearErrors, showErrors } from './ajax-form';

/**
 * Product form: toggles simple/variable sections and builds the variant matrix
 * (cartesian product of the ticked attribute values) into the variants repeater.
 * Existing combinations are never duplicated (matched on sorted value-id hash, same as ProductVariant::hashFor).
 */
const hashOf = (ids) => [...ids].map(Number).sort((a, b) => a - b).join('-');
const esc = (s) => $('<div>').text(s ?? '').html();

function toggleType() {
    const variable = $('#f-type').val() === 'variable';
    $('#variant-section').toggle(variable).find('input,select').prop('disabled', !variable);
    $('[data-simple-section]').toggle(!variable);
}

function refreshEmpty() {
    $('#variant-repeater [data-empty]').toggle($('#variant-repeater [data-repeater-item]').length === 0);
}

function buildPickers($gen) {
    const attrs = $gen.data('attributes') || [];
    const used = new Set(($gen.data('used') || []).map(Number));
    const $box = $gen.find('[data-attr-pickers]').empty();
    attrs.forEach((a) => {
        const vals = a.values.map((v) => {
            const sw = v.color ? '<span class="inline-block size-3 rounded-full border" style="background:' + esc(v.color) + '"></span>' : '';
            return '<label class="inline-flex items-center gap-1 rounded-full border border-slate-200 px-2 py-1 text-xs"><input type="checkbox" data-pick="' + a.id + '" value="' + v.id + '" ' + (used.has(v.id) ? 'checked' : '') + '> ' + sw + esc(v.name) + '</label>';
        }).join(' ');
        $box.append('<div><div class="mb-1 text-sm font-semibold">' + esc(a.name) + '</div><div class="flex flex-wrap gap-2">' + vals + '</div></div>');
    });
}

function generate() {
    const $gen = $('#variant-generator');
    const attrs = $gen.data('attributes') || [];
    const groups = attrs.map((a) => {
        const picked = $gen.find('[data-pick="' + a.id + '"]:checked').map((_, el) => Number(el.value)).get();
        return picked.map((id) => ({ id, name: a.values.find((v) => v.id === id).name }));
    }).filter((g) => g.length);
    if (!groups.length) return window.toastr?.warning(window.__t?.pick_values || 'Select attribute values first.');

    const combos = groups.reduce((acc, g) => acc.flatMap((c) => g.map((v) => [...c, v])), [[]]);
    const $rep = $('#variant-repeater');
    const have = new Set($rep.find('[data-repeater-item]').map((_, el) => String($(el).data('hash'))).get());
    const baseSku = ($('#f-sku').val() || 'SKU').trim();
    let added = 0;

    combos.forEach((combo) => {
        const ids = combo.map((c) => c.id);
        const hash = hashOf(ids);
        if (have.has(hash)) return;
        $rep.find('[data-repeater-add]').length || null;
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
    if (!added) window.toastr?.info(window.__t?.nothing_new || 'No new combinations.');
}

$(() => {
    if (!$('#variant-generator').length) return;
    buildPickers($('#variant-generator'));
    toggleType();
    refreshEmpty();
    $(document).on('change', '#f-type', toggleType);
    $(document).on('click', '#variant-generator [data-generate]', generate);
    $(document).on('repeater:changed', '#variant-repeater', refreshEmpty);
});

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
