
<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['table', 'url', 'id' => 'dt']));

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

foreach (array_filter((['table', 'url', 'id' => 'dt']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<div class="overflow-x-auto">
    <table id="<?php echo e($id); ?>" data-datatable data-url="<?php echo e($url); ?>" data-columns='<?php echo json_encode($table->columns(), 15, 512) ?>' class="display w-full"></table>
</div>
<?php /**PATH /var/www/3alraf/backend/resources/views/components/datatable.blade.php ENDPATH**/ ?>