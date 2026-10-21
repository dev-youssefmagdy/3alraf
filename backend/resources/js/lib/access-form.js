import $ from 'jquery';

// role form: "select all" + per-module row toggles keep each other in sync
function sync($f) {
    $f.find('tr').each(function () {
        const $boxes = $(this).find('input[name="permissions[]"]');
        if ($boxes.length) $(this).find('[data-perm-row]').prop('checked', $boxes.length === $boxes.filter(':checked').length);
    });
    const $all = $f.find('input[name="permissions[]"]');
    $f.find('[data-perm-all]').prop('checked', $all.length > 0 && $all.length === $all.filter(':checked').length);
}
$(document).on('change', '[data-perm-all]', function () { const $f = $(this).closest('[data-perm-matrix]'); $f.find('input[name="permissions[]"]').prop('checked', this.checked); sync($f); });
$(document).on('change', '[data-perm-row]', function () { $(this).closest('tr').find('input[name="permissions[]"]').prop('checked', this.checked); sync($(this).closest('[data-perm-matrix]')); });
$(document).on('change', 'input[name="permissions[]"]', function () { sync($(this).closest('[data-perm-matrix]')); });
$(() => $('[data-perm-matrix]').each(function () { sync($(this)); }));
