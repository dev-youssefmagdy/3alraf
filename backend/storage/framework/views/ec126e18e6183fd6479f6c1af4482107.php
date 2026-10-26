<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['label', 'value', 'hint' => null, 'tone' => 'brand']));

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

foreach (array_filter((['label', 'value', 'hint' => null, 'tone' => 'brand']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php
    $toneCls = ['brand' => 'text-brand-600', 'emerald' => 'text-emerald-600', 'slate' => 'text-slate-700'][$tone] ?? 'text-brand-600';
?>
<div <?php echo e($attributes->merge(['class' => 'rounded-xl border border-slate-200 bg-white p-5 shadow-sm'])); ?>>
    <p class="text-sm text-slate-500"><?php echo e($label); ?></p>
    <p class="mt-1 text-3xl font-bold <?php echo e($toneCls); ?>"><?php echo e($value); ?></p>
    <?php if($hint): ?><p class="mt-1 text-xs text-slate-400"><?php echo e($hint); ?></p><?php endif; ?>
</div>
<?php /**PATH /var/www/3alraf/backend/resources/views/components/stat-card.blade.php ENDPATH**/ ?>