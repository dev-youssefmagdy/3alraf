<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['title', 'items', 'href' => null, 'countdown' => null, 'accent' => false]));

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

foreach (array_filter((['title', 'items', 'href' => null, 'countdown' => null, 'accent' => false]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php if($items->isNotEmpty()): ?>
<section <?php echo e($attributes->merge(['class' => 'mt-8 rounded-2xl p-4 '.($accent ? 'bg-brand-50 ring-1 ring-brand-100' : 'bg-white ring-1 ring-slate-100')])); ?>>
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="text-lg font-extrabold text-slate-900"><?php echo e($title); ?></h2>
            <?php if($countdown): ?><span class="rounded-lg bg-brand-600 px-3 py-1 text-sm text-white"><?php echo e(__('store.ends_in')); ?> <span data-countdown="<?php echo e($countdown); ?>" class="font-mono font-bold"></span></span><?php endif; ?>
        </div>
        <?php if($href): ?><a href="<?php echo e($href); ?>" class="text-sm font-semibold text-brand-600 hover:underline"><?php echo e(__('store.view_all')); ?></a><?php endif; ?>
    </div>
    <div class="-mx-1 flex snap-x gap-3 overflow-x-auto px-1 pb-2">
        <?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tp): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div class="w-44 shrink-0 snap-start sm:w-52"><?php if (isset($component)) { $__componentOriginal3fd2897c1d6a149cdb97b41db9ff827a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal3fd2897c1d6a149cdb97b41db9ff827a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.product-card','data' => ['tp' => $tp,'class' => 'h-full']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('product-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['tp' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($tp),'class' => 'h-full']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal3fd2897c1d6a149cdb97b41db9ff827a)): ?>
<?php $attributes = $__attributesOriginal3fd2897c1d6a149cdb97b41db9ff827a; ?>
<?php unset($__attributesOriginal3fd2897c1d6a149cdb97b41db9ff827a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal3fd2897c1d6a149cdb97b41db9ff827a)): ?>
<?php $component = $__componentOriginal3fd2897c1d6a149cdb97b41db9ff827a; ?>
<?php unset($__componentOriginal3fd2897c1d6a149cdb97b41db9ff827a); ?>
<?php endif; ?></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
</section>
<?php endif; ?>
<?php /**PATH /var/www/3alraf/backend/resources/views/components/rail.blade.php ENDPATH**/ ?>