import $ from 'jquery';

/**
 * Product form: toggles simple/variable sections and builds the variant matrix
 * (cartesian product of the ticked attribute values) into the variants repeater.
 * Existing combinations are never duplicated (matched on sorted value-id hash, same as ProductVariant::hashFor).
 */
const hashOf = (ids) => [...ids].map(Number).sort((a, b) => a - b).join('-');
const esc = (s) => $('<div>').text(s ?? '').html();

function toggleType() {
    const variable = $('#product-type').val() === 'variable';
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
    $(document).on('change', '#product-type', toggleType);
    $(document).on('click', '#variant-generator [data-generate]', generate);
    $(document).on('repeater:changed', '#variant-repeater', refreshEmpty);
});
