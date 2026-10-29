import $ from 'jquery';

/**
 * <div data-repeater> <div data-repeater-list>...items [data-repeater-item]...</div>
 *   <template data-repeater-template>...__INDEX__...</template> <button data-repeater-add> </div>
 * Remove button: [data-repeater-remove]. Fires `repeater:added` on the new item.
 */
let counter = 1000;
$(document).on('click', '[data-repeater-add]', function () {
    const $r = $(this).closest('[data-repeater]');
    const html = $r.find('[data-repeater-template]').first().html().replaceAll('__INDEX__', 'n' + counter++);
    const $item = $(html);
    $r.find('[data-repeater-list]').first().append($item);
    window.initSearchSelect?.($item[0]);
    $item.trigger('repeater:added');
    $r.trigger('repeater:changed');
});
$(document).on('click', '[data-repeater-remove]', function () {
    const $r = $(this).closest('[data-repeater]');
    $(this).closest('[data-repeater-item]').remove();
    $r.trigger('repeater:changed');
});

// attribute form: show colour pickers only for type=color
function syncColor() {
    const isColor = $('[data-attr-type]').val() === 'color';
    $('[data-color-field]').toggle(isColor).find('input').prop('disabled', !isColor);
}
$(document).on('change', '[data-attr-type]', syncColor);
$(document).on('repeater:added', '#attr-values', syncColor);
$(syncColor);

// tenant catalog bulk-add helpers
$(document).on('change', '[data-check-all]', function () { $('[data-row-check]').prop('checked', this.checked); });
$(document).on('click', '[data-apply-defaults]', function () {
    const d = {};
    $('#bulk-defaults [data-default]').each(function () { d[$(this).data('default')] = $(this).val(); });
    $('[data-row]').has('[data-row-check]:checked').each(function () {
        $.each(d, (k, v) => { if (v !== '') $(this).find('[name$="[' + k + ']"]').val(v); });
    });
});
