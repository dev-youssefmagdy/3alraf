<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['title' => config('app.name'), 'bodyClass' => 'bg-slate-50', 'siteName' => null]));

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

foreach (array_filter((['title' => config('app.name'), 'bodyClass' => 'bg-slate-50', 'siteName' => null]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php
    $jsStrings = [
        'confirm_delete' => __('admin.confirm_delete'),
        'fix_errors' => __('admin.fix_errors'),
        'error' => __('admin.error'),
        'pick_values' => __('admin.pick_values'),
        'nothing_new' => __('admin.nothing_new'),
        'datatable' => [
            'search' => __('admin.search').':',
            'processing' => __('admin.processing'),
            'emptyTable' => __('admin.no_data'),
            'zeroRecords' => __('admin.no_data'),
        ],
    ];
?>
<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>" dir="<?php echo e(($isRtl ?? false) ? 'rtl' : 'ltr'); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title><?php echo e($title); ?> | <?php echo e($siteName ?: config('app.name')); ?></title>
    <link rel="icon" type="image/png" href="<?php echo e(asset('logo.png')); ?>">
    <?php echo e($head ?? ''); ?>

    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
    <script>window.__t = <?php echo e(Illuminate\Support\Js::from($jsStrings)); ?>;</script>
</head>
<body class="<?php echo e($bodyClass); ?> min-h-screen font-sans text-slate-800 antialiased">
    <?php echo e($slot); ?>

</body>
</html>
<?php /**PATH /var/www/3alraf/backend/resources/views/components/layouts/base.blade.php ENDPATH**/ ?>