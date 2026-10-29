import $ from 'jquery';
import toastr from 'toastr';

// Ordered pin lists (product lists + custom pages): [data-pin-list] with select, add button and <li data-pin-item> rows holding hidden product_ids[].
$(document).on('click', '[data-pin-add]', function () {
    const $w = $(this).closest('[data-pin-list]');
    const $sel = $w.find('[data-pin-select]');
    const id = $sel.val();
    if (!id) return;
    if ($w.find('input[name="product_ids[]"][value="' + id + '"]').length) { toastr.warning(window.__t?.nothing_new || 'Already added'); return; }
    const $li = $('<li data-pin-item class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-1.5 text-sm"></li>');
    $li.append($('<input type="hidden" name="product_ids[]">').val(id), $('<span class="flex-1"></span>').text($sel.find('option:selected').text()),
        '<button type="button" data-pin-up class="btn-sm btn-secondary">↑</button><button type="button" data-pin-down class="btn-sm btn-secondary">↓</button><button type="button" data-pin-remove class="btn-sm btn-danger">&times;</button>');
    $w.find('[data-pin-items]').append($li);
    if ($sel[0].tomselect) $sel[0].tomselect.clear(); else $sel.val('');
});
$(document).on('click', '[data-pin-remove]', function () { $(this).closest('[data-pin-item]').remove(); });
$(document).on('click', '[data-pin-up]', function () { const $i = $(this).closest('[data-pin-item]'); $i.prev().before($i); });
$(document).on('click', '[data-pin-down]', function () { const $i = $(this).closest('[data-pin-item]'); $i.next().after($i); });

// Affiliate link copy buttons: <button data-copy="text">
$(document).on('click', '[data-copy]', function () {
    const text = String($(this).data('copy'));
    const done = () => toastr.success($(this).data('copied') || 'Copied');
    if (navigator.clipboard?.writeText) navigator.clipboard.writeText(text).then(done, () => window.prompt('Copy:', text));
    else window.prompt('Copy:', text);
});
