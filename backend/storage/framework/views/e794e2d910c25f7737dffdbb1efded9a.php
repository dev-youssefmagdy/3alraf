<?php if (isset($component)) { $__componentOriginal51ea8e071ed038a4c6542abfe5470d94 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal51ea8e071ed038a4c6542abfe5470d94 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.layouts.storefront','data' => ['title' => $tenant->name,'description' => __('store.seo_default_desc'),'jsonld' => ['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => $tenant->name, 'url' => url('/'.app()->getLocale()),
    'potentialAction' => ['@type' => 'SearchAction', 'target' => route('storefront.search').'?q={q}', 'query-input' => 'required name=q']]]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('layouts.storefront'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($tenant->name),'description' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('store.seo_default_desc')),'jsonld' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => $tenant->name, 'url' => url('/'.app()->getLocale()),
    'potentialAction' => ['@type' => 'SearchAction', 'target' => route('storefront.search').'?q={q}', 'query-input' => 'required name=q']])]); ?>
    
    <?php if($hero->isNotEmpty()): ?>
        <section class="relative cursor-grab select-none overflow-hidden rounded-2xl bg-slate-200 active:cursor-grabbing" data-slider aria-roledescription="carousel">
            <div class="flex will-change-transform" data-slides>
                <?php $__currentLoopData = $hero; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $b): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <a href="<?php echo e($b->safeLink() ?: '#'); ?>" class="block w-full shrink-0" data-slide>
                        <?php if($b->imageUrl()): ?><img src="<?php echo e($b->imageUrl()); ?>" alt="<?php echo e($b->title); ?>" class="aspect-[16/6] w-full object-cover" <?php if(! $loop->first): ?> loading="lazy" <?php endif; ?>><?php endif; ?>
                    </a>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
            <?php if($hero->count() > 1): ?>
                <button type="button" data-slide-prev class="absolute start-3 top-1/2 hidden -translate-y-1/2 rounded-full bg-white/80 px-3 py-1 text-xl shadow md:block" aria-label="prev">&#8249;</button>
                <button type="button" data-slide-next class="absolute end-3 top-1/2 hidden -translate-y-1/2 rounded-full bg-white/80 px-3 py-1 text-xl shadow md:block" aria-label="next">&#8250;</button>
                <div class="absolute inset-x-0 bottom-3 flex justify-center gap-1.5" data-dots><?php $__currentLoopData = $hero; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $b): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><span class="size-2 cursor-pointer rounded-full bg-white/60 transition-all"></span><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    
    <?php if(count($categories)): ?>
        <section class="mt-6">
            <div class="flex gap-4 overflow-x-auto pb-2">
                <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <a href="<?php echo e(route('storefront.category.show', $c['slug'])); ?>" class="group w-24 shrink-0 text-center">
                        <span class="mx-auto grid size-20 place-items-center overflow-hidden rounded-full bg-white ring-1 ring-slate-200 transition group-hover:ring-brand-500">
                            <?php if($c['image']): ?><img src="<?php echo e($c['image']); ?>" alt="" class="size-full object-cover" loading="lazy"><?php else: ?><span class="text-2xl font-bold text-brand-600"><?php echo e(mb_substr($c['name'], 0, 1)); ?></span><?php endif; ?>
                        </span>
                        <span class="mt-2 block text-xs font-semibold text-slate-700 group-hover:text-brand-600"><?php echo e($c['name']); ?></span>
                    </a>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        </section>
    <?php endif; ?>

    
    <?php if($strips->isNotEmpty() || $tiles->isNotEmpty()): ?>
        <section class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-<?php echo e(min(4, max(2, $strips->count() + $tiles->count()))); ?>">
            <?php $__currentLoopData = $strips->concat($tiles); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $b): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <a href="<?php echo e($b->safeLink() ?: '#'); ?>" class="block overflow-hidden rounded-xl bg-slate-100"><?php if($b->imageUrl()): ?><img src="<?php echo e($b->imageUrl()); ?>" alt="<?php echo e($b->title); ?>" class="aspect-[16/7] w-full object-cover transition hover:scale-105" loading="lazy"><?php endif; ?></a>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </section>
    <?php endif; ?>

    
    <?php if($flash): ?>
        <?php if (isset($component)) { $__componentOriginalcb6d9422bf11bb6e2fffe755e2592d2c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalcb6d9422bf11bb6e2fffe755e2592d2c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.rail','data' => ['title' => $flash['sale']->name,'items' => $flash['items'],'countdown' => $flash['sale']->ends_at->toIso8601String(),'accent' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('rail'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($flash['sale']->name),'items' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($flash['items']),'countdown' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($flash['sale']->ends_at->toIso8601String()),'accent' => true]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalcb6d9422bf11bb6e2fffe755e2592d2c)): ?>
<?php $attributes = $__attributesOriginalcb6d9422bf11bb6e2fffe755e2592d2c; ?>
<?php unset($__attributesOriginalcb6d9422bf11bb6e2fffe755e2592d2c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalcb6d9422bf11bb6e2fffe755e2592d2c)): ?>
<?php $component = $__componentOriginalcb6d9422bf11bb6e2fffe755e2592d2c; ?>
<?php unset($__componentOriginalcb6d9422bf11bb6e2fffe755e2592d2c); ?>
<?php endif; ?>
    <?php endif; ?>

    <?php if(! empty($bundles)): ?>
        <section class="mt-8">
            <div class="mb-3 flex items-center justify-between"><h2 class="text-lg font-extrabold"><?php echo e(__('store.bundles')); ?></h2><a href="<?php echo e(route('storefront.bundles.index')); ?>" class="text-sm font-semibold text-brand-600"><?php echo e(__('store.view_all')); ?></a></div>
            <div class="grid grid-cols-2 gap-4 md:grid-cols-4"><?php $__currentLoopData = $bundles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $b): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php echo $__env->make('storefront._bundle-card', ['b' => $b], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div>
        </section>
    <?php endif; ?>

    <?php if (isset($component)) { $__componentOriginalcb6d9422bf11bb6e2fffe755e2592d2c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalcb6d9422bf11bb6e2fffe755e2592d2c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.rail','data' => ['title' => __('store.recommended'),'items' => $rails['recommended']]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('rail'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('store.recommended')),'items' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($rails['recommended'])]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalcb6d9422bf11bb6e2fffe755e2592d2c)): ?>
<?php $attributes = $__attributesOriginalcb6d9422bf11bb6e2fffe755e2592d2c; ?>
<?php unset($__attributesOriginalcb6d9422bf11bb6e2fffe755e2592d2c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalcb6d9422bf11bb6e2fffe755e2592d2c)): ?>
<?php $component = $__componentOriginalcb6d9422bf11bb6e2fffe755e2592d2c; ?>
<?php unset($__componentOriginalcb6d9422bf11bb6e2fffe755e2592d2c); ?>
<?php endif; ?>
    <?php if (isset($component)) { $__componentOriginalcb6d9422bf11bb6e2fffe755e2592d2c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalcb6d9422bf11bb6e2fffe755e2592d2c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.rail','data' => ['title' => __('store.most_sold'),'items' => $rails['most_sold']]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('rail'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('store.most_sold')),'items' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($rails['most_sold'])]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalcb6d9422bf11bb6e2fffe755e2592d2c)): ?>
<?php $attributes = $__attributesOriginalcb6d9422bf11bb6e2fffe755e2592d2c; ?>
<?php unset($__attributesOriginalcb6d9422bf11bb6e2fffe755e2592d2c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalcb6d9422bf11bb6e2fffe755e2592d2c)): ?>
<?php $component = $__componentOriginalcb6d9422bf11bb6e2fffe755e2592d2c; ?>
<?php unset($__componentOriginalcb6d9422bf11bb6e2fffe755e2592d2c); ?>
<?php endif; ?>
    <?php if (isset($component)) { $__componentOriginalcb6d9422bf11bb6e2fffe755e2592d2c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalcb6d9422bf11bb6e2fffe755e2592d2c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.rail','data' => ['title' => __('store.most_reviewed'),'items' => $rails['most_reviewed']]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('rail'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('store.most_reviewed')),'items' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($rails['most_reviewed'])]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalcb6d9422bf11bb6e2fffe755e2592d2c)): ?>
<?php $attributes = $__attributesOriginalcb6d9422bf11bb6e2fffe755e2592d2c; ?>
<?php unset($__attributesOriginalcb6d9422bf11bb6e2fffe755e2592d2c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalcb6d9422bf11bb6e2fffe755e2592d2c)): ?>
<?php $component = $__componentOriginalcb6d9422bf11bb6e2fffe755e2592d2c; ?>
<?php unset($__componentOriginalcb6d9422bf11bb6e2fffe755e2592d2c); ?>
<?php endif; ?>

    <?php $__currentLoopData = $categoryRails; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php if (isset($component)) { $__componentOriginalcb6d9422bf11bb6e2fffe755e2592d2c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalcb6d9422bf11bb6e2fffe755e2592d2c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.rail','data' => ['title' => $r['category']->name,'items' => $r['items'],'href' => route('storefront.category.show', $r['category']->slug)]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('rail'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($r['category']->name),'items' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($r['items']),'href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('storefront.category.show', $r['category']->slug))]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalcb6d9422bf11bb6e2fffe755e2592d2c)): ?>
<?php $attributes = $__attributesOriginalcb6d9422bf11bb6e2fffe755e2592d2c; ?>
<?php unset($__attributesOriginalcb6d9422bf11bb6e2fffe755e2592d2c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalcb6d9422bf11bb6e2fffe755e2592d2c)): ?>
<?php $component = $__componentOriginalcb6d9422bf11bb6e2fffe755e2592d2c; ?>
<?php unset($__componentOriginalcb6d9422bf11bb6e2fffe755e2592d2c); ?>
<?php endif; ?>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

    
    <?php if($brands->isNotEmpty()): ?>
        <section class="mt-8 rounded-2xl bg-white p-4 ring-1 ring-slate-100">
            <h2 class="mb-3 text-lg font-extrabold"><?php echo e(__('store.brands')); ?></h2>
            <div class="flex flex-wrap items-center gap-4">
                <?php $__currentLoopData = $brands; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $br): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <a href="<?php echo e(route('storefront.brand.show', $br->slug)); ?>" class="grid h-16 w-32 place-items-center rounded-lg border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-600 hover:border-brand-500">
                        <?php if($br->logo_url ?? null): ?><img src="<?php echo e($br->logo_url); ?>" alt="<?php echo e($br->name); ?>" class="max-h-12 object-contain" loading="lazy"><?php else: ?><?php echo e($br->name); ?><?php endif; ?>
                    </a>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        </section>
    <?php endif; ?>

    
    <?php if($popup && $popup->imageUrl()): ?>
        <div class="fixed inset-0 z-50 hidden place-items-center bg-black/50 p-4" data-popup="<?php echo e($popup->id); ?>">
            <div class="relative max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl">
                <button type="button" data-popup-close class="absolute end-2 top-2 grid size-8 place-items-center rounded-full bg-white/90 text-xl leading-none shadow" aria-label="<?php echo e(__('store.close')); ?>">&times;</button>
                <a href="<?php echo e($popup->safeLink() ?: '#'); ?>"><img src="<?php echo e($popup->imageUrl()); ?>" alt="<?php echo e($popup->title); ?>" class="w-full"></a>
            </div>
        </div>
    <?php endif; ?>
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
<?php /**PATH /var/www/3alraf/backend/resources/views/storefront/home.blade.php ENDPATH**/ ?>