<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['id', 'title' => null]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter((['id', 'title' => null]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<div id="<?php echo e($id); ?>" data-modal class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 p-4">
    <div <?php echo e($attributes->merge(['class' => 'w-full max-w-lg rounded-xl bg-white shadow-xl'])); ?>>
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
            <h3 class="font-semibold text-slate-800"><?php echo e($title); ?></h3>
            <button type="button" data-modal-close class="text-slate-400 hover:text-slate-700" aria-label="Close">&times;</button>
        </div>
        <div class="p-5"><?php echo e($slot); ?></div>
        <?php if(isset($footer)): ?><div class="flex justify-end gap-2 border-t border-slate-100 px-5 py-3"><?php echo e($footer); ?></div><?php endif; ?>
    </div>
</div>
<?php /**PATH /var/www/3alraf/backend/resources/views/components/modal.blade.php ENDPATH**/ ?>