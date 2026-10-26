<?php $cur = current_tenant()->currency_symbol; $img = $bundle['image'] ?: ($bundle['components'][0]['image'] ?? null); ?>
<?php if (isset($component)) { $__componentOriginal51ea8e071ed038a4c6542abfe5470d94 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal51ea8e071ed038a4c6542abfe5470d94 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.layouts.storefront','data' => ['title' => $bundle['name'],'description' => \Illuminate\Support\Str::limit(strip_tags($bundle['description']), 160, ''),'image' => $img]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('layouts.storefront'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($bundle['name']),'description' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(\Illuminate\Support\Str::limit(strip_tags($bundle['description']), 160, '')),'image' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($img)]); ?>
    <nav class="mb-4 flex flex-wrap items-center gap-1 text-sm text-slate-500" aria-label="breadcrumb">
        <a href="<?php echo e(route('storefront.home')); ?>" class="hover:text-brand-600"><?php echo e(__('store.breadcrumb_home')); ?></a><span>/</span>
        <a href="<?php echo e(route('storefront.bundles.index')); ?>" class="hover:text-brand-600"><?php echo e(__('store.bundles')); ?></a><span>/</span><span class="font-semibold text-slate-800"><?php echo e($bundle['name']); ?></span>
    </nav>
    <div class="grid gap-8 rounded-2xl bg-white p-4 ring-1 ring-slate-100 lg:grid-cols-2" data-bundle-page>
        <div class="overflow-hidden rounded-xl bg-slate-50"><?php if($img): ?><img src="<?php echo e($img); ?>" alt="<?php echo e($bundle['name']); ?>" class="aspect-square w-full object-contain"><?php else: ?><div class="aspect-square bg-slate-100"></div><?php endif; ?></div>
        <div class="space-y-4">
            <span class="inline-block rounded bg-brand-50 px-2 py-0.5 text-xs font-bold text-brand-600"><?php echo e(__('store.bundle')); ?></span>
            <h1 class="text-2xl font-extrabold"><?php echo e($bundle['name']); ?></h1>
            <div class="flex flex-wrap items-baseline gap-3">
                <span class="text-3xl font-extrabold text-brand-600"><?php echo e(number_format((float) $bundle['price'], 2)); ?></span><span class="font-semibold text-brand-600"><?php echo e($cur); ?></span>
                <?php if(bccomp($bundle['savings'], '0', 2) > 0): ?>
                    <span class="text-base text-slate-400 line-through"><?php echo e(number_format((float) $bundle['regular_total'], 2)); ?></span>
                    <span class="rounded bg-emerald-50 px-2 py-0.5 text-sm font-bold text-emerald-700"><?php echo e(__('store.bundle_save', ['amount' => number_format((float) $bundle['savings'], 2).' '.$cur])); ?></span>
                <?php endif; ?>
            </div>
            <?php if($bundle['description']): ?><p class="whitespace-pre-line text-slate-600"><?php echo e($bundle['description']); ?></p><?php endif; ?>
            <div>
                <p class="mb-2 text-sm font-bold"><?php echo e(__('store.bundle_contains')); ?></p>
                <ul class="space-y-2">
                    <?php $__currentLoopData = $bundle['components']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <li class="flex items-center gap-3 rounded-lg border border-slate-100 p-2">
                            <?php if($c['image']): ?><img src="<?php echo e($c['image']); ?>" class="size-12 rounded object-cover" alt="" loading="lazy"><?php endif; ?>
                            <a href="<?php echo e(route('storefront.product.show', $c['slug'])); ?>" class="flex-1 font-semibold hover:text-brand-600"><?php echo e($c['name']); ?></a>
                            <span class="text-sm text-slate-500">&times; <?php echo e($c['quantity']); ?></span>
                        </li>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </ul>
            </div>
            <?php if(! $bundle['in_stock']): ?>
                <p class="font-semibold text-red-600"><?php echo e(__('store.sold_out')); ?></p>
            <?php else: ?>
                <?php if($bundle['available'] !== null && $bundle['available'] <= 5): ?><p class="text-sm font-semibold text-amber-600"><?php echo e(__('store.bundle_left', ['n' => $bundle['available']])); ?></p><?php endif; ?>
                <button type="button" class="btn btn-primary !px-8 !py-3" data-add-bundle="<?php echo e($bundle['id']); ?>"><?php echo e(__('store.add_to_cart')); ?></button>
            <?php endif; ?>
        </div>
    </div>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal51ea8e071ed038a4c6542abfe5470d94)): ?>
<?php $attributes = $__attributesOriginal51ea8e071ed038a4c6542abfe5470d94; ?>
<?php unset($__attributesOriginal51ea8e071ed038a4c6542abfe5470d94); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal51ea8e071ed038a4c6542abfe5470d94)): ?>
<?php $component = $__componentOriginal51ea8e071ed038a4c6542abfe5470d94; ?>
<?php unset($__componentOriginal51ea8e071ed038a4c6542abfe5470d94); ?>
<?php endif; ?>
<?php /**PATH /var/www/3alraf/backend/resources/views/storefront/bundle.blade.php ENDPATH**/ ?>