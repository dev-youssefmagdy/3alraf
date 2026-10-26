<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['title' => null]));

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

foreach (array_filter((['title' => null]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<div <?php echo e($attributes->merge(['class' => 'rounded-xl border border-slate-200 bg-white shadow-sm'])); ?>>
    <?php if($title || isset($actions)): ?>
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-3">
            <h2 class="text-base font-semibold text-slate-800"><?php echo e($title); ?></h2>
            <?php if(isset($actions)): ?><div class="flex items-center gap-2"><?php echo e($actions); ?></div><?php endif; ?>
        </div>
    <?php endif; ?>
    <div class="p-5"><?php echo e($slot); ?></div>
</div>
<?php /**PATH /var/www/3alraf/backend/resources/views/components/card.blade.php ENDPATH**/ ?>