<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['method']));

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

foreach (array_filter((['method']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php
    $icons = [
        'cod' => 'M3 7h18v10H3zM12 14a2 2 0 100-4 2 2 0 000 4zM6 10v.01M18 14v.01',
        'wallet' => 'M3 7a2 2 0 012-2h13v3M3 7v10a2 2 0 002 2h14V8H5a2 2 0 01-2-1zM16 13.500h.01',
        'online' => 'M3 6h18v12H3zM3 10h18M7 15h3',
    ];
?>
<span class="inline-flex items-center gap-1.5 rounded bg-white px-2 py-1 text-xs font-semibold text-slate-700" title="<?php echo e($method['name']); ?>">
    <svg class="size-4 text-brand-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="<?php echo e($icons[$method['type']] ?? $icons['online']); ?>"/></svg><?php echo e($method['name']); ?>

</span>
<?php /**PATH /var/www/3alraf/backend/resources/views/components/payment-icon.blade.php ENDPATH**/ ?>