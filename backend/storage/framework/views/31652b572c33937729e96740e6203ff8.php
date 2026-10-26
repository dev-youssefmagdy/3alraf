<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['name', 'label' => null, 'type' => 'text', 'value' => null, 'required' => false, 'hint' => null]));

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

foreach (array_filter((['name', 'label' => null, 'type' => 'text', 'value' => null, 'required' => false, 'hint' => null]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<div data-field <?php echo e($attributes->only('class')); ?>>
    <?php if($label): ?><label for="f-<?php echo e($name); ?>" class="form-label"><?php echo e($label); ?><?php if($required): ?><span class="text-red-500">*</span><?php endif; ?></label><?php endif; ?>
    <input id="f-<?php echo e($name); ?>" type="<?php echo e($type); ?>" name="<?php echo e($name); ?>" <?php if($type !== 'password'): ?> value="<?php echo e(old($name, $value)); ?>" <?php endif; ?>
        <?php if($required): echo 'required'; endif; ?> <?php echo e($attributes->except('class')->merge(['class' => 'form-control'])); ?>>
    <?php if($hint): ?><p class="mt-1 text-xs text-slate-500"><?php echo e($hint); ?></p><?php endif; ?>
</div>
<?php /**PATH /var/www/3alraf/backend/resources/views/components/input.blade.php ENDPATH**/ ?>