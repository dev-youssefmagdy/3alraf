import $ from 'jquery';
import toastr from 'toastr';

/**
 * <form data-ajax-form data-validate-url="..." [data-method=PUT] action="..."> :
 *  1) POST FormData to data-validate-url (same FormRequest rules) -> inline errors on 422
 *  2) then submit to action; toastr + redirect on success.
 * Errors keyed `a.b.c` map to inputs named `a[b][c]` (or `a[b][c][]`).
 */
export function clearErrors($form) {
    $form.find('.is-invalid').removeClass('is-invalid');
    $form.find('.field-error').remove();
}

function findField($form, key) {
    const name = key.split('.').reduce((a, p, i) => (i === 0 ? p : a + '[' + p + ']'), '');
    let $f = $form.find('[name="' + name + '"]');
    if (!$f.length) $f = $form.find('[name="' + name + '[]"]');
    if (!$f.length && key.includes('.')) $f = findField($form, key.split('.').slice(0, -1).join('.'));
    return $f;
}

export function showErrors($form, errors) {
    clearErrors($form);
    let first = null;
    $.each(errors, (key, msgs) => {
        const $f = findField($form, key);
        const msg = Array.isArray(msgs) ? msgs[0] : msgs;
        if ($f.length) {
            $f.addClass('is-invalid');
            const $anchor = $f.closest('[data-field]').length ? $f.closest('[data-field]') : $f.last();
            $anchor.after('<p class="field-error">' + $('<div>').text(msg).html() + '</p>');
            // reveal hidden translatable tab
            const $pane = $f.closest('[data-tab-pane]');
            if ($pane.length && $pane.hasClass('hidden')) $pane.closest('[data-tabs]').find('[data-tab-target="' + $pane.data('tab-pane') + '"]').trigger('click');
            first = first || $f[0];
        } else {
            toastr.error(msg);
        }
    });
    if (first) first.scrollIntoView({ block: 'center', behavior: 'smooth' });
}

function setBusy($form, busy) {
    const $b = $form.find('[type=submit]');
    $b.prop('disabled', busy);
    $b.toggleClass('opacity-60', busy);
}

function handleFail($form, xhr) {
    if (xhr.status === 422 && xhr.responseJSON?.errors) {
        showErrors($form, xhr.responseJSON.errors);
        toastr.error($form.data('error-msg') || window.__t?.fix_errors || 'Please fix the highlighted fields.');
    } else if (xhr.status === 419) {
        toastr.error('Session expired. Reloading...');
        setTimeout(() => location.reload(), 1200);
    } else {
        toastr.error(xhr.responseJSON?.message || window.__t?.error || 'Something went wrong.');
    }
}

function payload($form) {
    const fd = new FormData($form[0]);
    const method = ($form.data('method') || '').toString().toUpperCase();
    if (method && method !== 'POST') fd.set('_method', method);
    // unchecked checkboxes -> explicit 0 for boolean fields marked data-bool
    $form.find('input[type=checkbox][data-bool]').each(function () { if (!this.checked) fd.set(this.name, '0'); });
    return fd;
}

$(document).on('submit', 'form[data-ajax-form]', function (e) {
    e.preventDefault();
    const $form = $(this);
    if ($form.data('busy')) return;
    const ajax = (url, fd) => $.ajax({ url, method: 'POST', data: fd, processData: false, contentType: false });

    clearErrors($form);
    $form.data('busy', true);
    setBusy($form, true);
    const done = () => { $form.data('busy', false); setBusy($form, false); };

    const submit = () => ajax($form.attr('action'), payload($form)).done((res) => {
        if (res.message) toastr.success(res.message);
        if (res.redirect) setTimeout(() => (location.href = res.redirect), res.message ? 600 : 0);
        else done();
        $form.trigger('ajax-form:success', [res]);
    }).fail((xhr) => { handleFail($form, xhr); done(); });

    const vUrl = $form.data('validate-url');
    if (!vUrl) return submit();
    ajax(vUrl, payload($form)).done(submit).fail((xhr) => { handleFail($form, xhr); done(); });
});

// clear single-field error on edit
$(document).on('input change', 'form[data-ajax-form] .is-invalid', function () {
    $(this).removeClass('is-invalid').closest('div').find('.field-error').first().remove();
});

// generic ajax delete: <button data-delete="url" data-table="#id">
$(document).on('click', '[data-delete]', function () {
    const $btn = $(this);
    const msg = window.__t?.confirm_delete || 'Delete this record?';
    if (!window.confirm(msg)) return;
    $.ajax({ url: $btn.data('delete'), method: 'POST', data: { _method: 'DELETE' } })
        .done((res) => {
            toastr.success(res.message || 'Deleted');
            const $t = $btn.closest('table.dataTable');
            if ($t.length) $t.DataTable().ajax.reload(null, false);
            else if (res.redirect) location.href = res.redirect;
        })
        .fail((xhr) => {
            const errs = xhr.responseJSON?.errors;
            toastr.error(errs ? Object.values(errs)[0][0] : xhr.responseJSON?.message || 'Error');
        });
});
