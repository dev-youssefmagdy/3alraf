<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['name', 'label', 'checked' => false, 'value' => 1, 'bool' => true]));

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

foreach (array_filter((['name', 'label', 'checked' => false, 'value' => 1, 'bool' => true]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<label class="inline-flex items-center gap-2 text-sm text-slate-700">
    <input type="checkbox" name="<?php echo e($name); ?>" value="<?php echo e($value); ?>" <?php if(old($name, $checked)): echo 'checked'; endif; ?> <?php if($bool): ?> data-bool <?php endif; ?> class="size-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
    <?php echo e($label); ?>

</label>
<?php /**PATH /var/www/3alraf/backend/resources/views/components/checkbox.blade.php ENDPATH**/ ?>