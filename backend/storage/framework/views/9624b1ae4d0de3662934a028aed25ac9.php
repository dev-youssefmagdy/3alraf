<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['name', 'label' => null, 'options' => [], 'value' => null, 'placeholder' => null, 'required' => false]));

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

foreach (array_filter((['name', 'label' => null, 'options' => [], 'value' => null, 'placeholder' => null, 'required' => false]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<div data-field <?php echo e($attributes->only('class')); ?>>
    <?php if($label): ?><label for="f-<?php echo e($name); ?>" class="form-label"><?php echo e($label); ?><?php if($required): ?><span class="text-red-500">*</span><?php endif; ?></label><?php endif; ?>
    <select id="f-<?php echo e($name); ?>" name="<?php echo e($name); ?>" <?php echo e($attributes->except('class')->merge(['class' => 'form-control'])); ?>>
        <?php if($placeholder !== null): ?><option value=""><?php echo e($placeholder); ?></option><?php endif; ?>
        <?php $__currentLoopData = $options; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($k); ?>" <?php if((string) old($name, $value) === (string) $k): echo 'selected'; endif; ?>><?php echo e($v); ?></option>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </select>
</div>
<?php /**PATH /var/www/3alraf/backend/resources/views/components/select.blade.php ENDPATH**/ ?>