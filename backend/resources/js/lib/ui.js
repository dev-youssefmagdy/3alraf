import $ from 'jquery';

// translatable tabs
$(document).on('click', '[data-tab-target]', function () {
    const $w = $(this).closest('[data-tabs]');
    $w.find('[data-tab-target]').removeClass('border-brand-600 text-brand-600').addClass('border-transparent text-slate-500');
    $(this).addClass('border-brand-600 text-brand-600').removeClass('border-transparent text-slate-500');
    $w.find('[data-tab-pane]').addClass('hidden');
    $w.find('[data-tab-pane="' + $(this).data('tab-target') + '"]').removeClass('hidden');
});

// modals
$(document).on('click', '[data-modal-open]', function () { $('#' + $(this).data('modal-open')).removeClass('hidden').addClass('flex'); });
$(document).on('click', '[data-modal-close]', function () { $(this).closest('[data-modal]').addClass('hidden').removeClass('flex'); });

// dropdowns
$(document).on('click', '[data-dropdown-toggle]', function (e) {
    e.stopPropagation();
    const $m = $('#' + $(this).data('dropdown-toggle'));
    $('[data-dropdown]').not($m).addClass('hidden');
    $m.toggleClass('hidden');
});
$(document).on('click', function () { $('[data-dropdown]').addClass('hidden'); });
$(document).on('click', '[data-sidebar-toggle]', function () { $('#sidebar').toggleClass('hidden'); });
