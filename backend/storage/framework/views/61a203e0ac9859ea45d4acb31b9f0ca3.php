<?php
    $listing = $listing ?? null; // brand / tag landing page: ['title','action','image','description']
    $isSearch = $category === null && ! $listing;
    $pageTitle = $listing ? $listing['title'] : ($isSearch ? ($filter->q ? __('store.search_results', ['q' => $filter->q]) : __('store.search')) : $category->name);
    $action = $listing ? $listing['action'] : ($isSearch ? route('storefront.search') : route('storefront.category.show', $category->slug));
    $selectedValues = $filter->values;
    $activeCount = count($filter->brands) + count($filter->tags) + count($filter->values) + ($filter->priceMin !== null ? 1 : 0) + ($filter->priceMax !== null ? 1 : 0);
?>
<?php if (isset($component)) { $__componentOriginal51ea8e071ed038a4c6542abfe5470d94 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal51ea8e071ed038a4c6542abfe5470d94 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.layouts.storefront','data' => ['title' => $category ? ($category->name) : $pageTitle,'description' => $category?->description ?? ($listing['description'] ?? null),'image' => $category?->image_url ?? ($listing['image'] ?? null),'noindex' => $isSearch]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('layouts.storefront'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($category ? ($category->name) : $pageTitle),'description' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($category?->description ?? ($listing['description'] ?? null)),'image' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($category?->image_url ?? ($listing['image'] ?? null)),'noindex' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($isSearch)]); ?>
    <nav class="mb-4 flex flex-wrap items-center gap-1 text-sm text-slate-500" aria-label="breadcrumb">
        <a href="<?php echo e(route('storefront.home')); ?>" class="hover:text-brand-600"><?php echo e(__('store.breadcrumb_home')); ?></a>
        <?php $__currentLoopData = $crumbs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><span>/</span><?php if($loop->last): ?><span class="font-semibold text-slate-800"><?php echo e($c->name); ?></span><?php else: ?><a href="<?php echo e(route('storefront.category.show', $c->slug)); ?>" class="hover:text-brand-600"><?php echo e($c->name); ?></a><?php endif; ?> <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <?php if($isSearch || $listing): ?><span>/</span><span class="font-semibold text-slate-800"><?php echo e($pageTitle); ?></span><?php endif; ?>
    </nav>

    <?php if($children->isNotEmpty()): ?>
        <div class="mb-4 flex flex-wrap gap-2">
            <?php $__currentLoopData = $children; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ch): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><a href="<?php echo e(route('storefront.category.show', $ch->slug)); ?>" class="rounded-full border border-slate-200 bg-white px-4 py-1.5 text-sm hover:border-brand-500 hover:text-brand-600"><?php echo e($ch->name); ?></a><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    <?php endif; ?>

    <div class="grid gap-6 lg:grid-cols-[16rem_1fr]" data-catalog data-action="<?php echo e($action); ?>">
        <aside class="lg:block" data-filters-panel>
            <button type="button" class="btn btn-secondary mb-3 w-full lg:hidden" data-filters-toggle><?php echo e(__('store.filters')); ?> <?php if($activeCount): ?>(<?php echo e($activeCount); ?>)<?php endif; ?></button>
            <form method="GET" action="<?php echo e($action); ?>" class="hidden space-y-5 rounded-xl border border-slate-200 bg-white p-4 lg:block" data-filter-form>
                <?php if($isSearch): ?><input type="hidden" name="q" value="<?php echo e($filter->q); ?>"><?php endif; ?>
                <input type="hidden" name="sort" value="<?php echo e($filter->sort); ?>" data-sort-input>
                <?php if($facets['brands']->isNotEmpty()): ?>
                    <fieldset><legend class="mb-2 font-bold"><?php echo e(__('store.brand')); ?></legend>
                        <div class="max-h-48 space-y-1 overflow-y-auto text-sm">
                            <?php $__currentLoopData = $facets['brands']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $b): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <label class="flex cursor-pointer items-center gap-2"><input type="checkbox" name="brands[]" value="<?php echo e($b->id); ?>" <?php if(in_array($b->id, $filter->brands)): echo 'checked'; endif; ?> class="accent-brand-600"> <?php echo e($b->name); ?></label>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </div></fieldset>
                <?php endif; ?>
                <?php if($facets['tags']->isNotEmpty()): ?>
                    <fieldset><legend class="mb-2 font-bold"><?php echo e(__('store.tags')); ?></legend>
                        <div class="flex flex-wrap gap-2 text-sm">
                            <?php $__currentLoopData = $facets['tags']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tg): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <label class="cursor-pointer"><input type="checkbox" name="tags[]" value="<?php echo e($tg->id); ?>" <?php if(in_array($tg->id, $filter->tags)): echo 'checked'; endif; ?> class="peer sr-only">
                                    <span class="inline-flex rounded-full border border-slate-300 px-3 py-1 peer-checked:border-brand-600 peer-checked:bg-brand-50 peer-checked:text-brand-700"><?php echo e($tg->name); ?></span></label>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </div></fieldset>
                <?php endif; ?>
                <?php $__currentLoopData = $facets['attributes']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $attr): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <fieldset><legend class="mb-2 font-bold"><?php echo e($attr->name); ?></legend>
                        <div class="flex flex-wrap gap-2 text-sm">
                            <?php $__currentLoopData = $attr->values; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <label class="cursor-pointer"><input type="checkbox" name="values[]" value="<?php echo e($v->id); ?>" <?php if(in_array($v->id, $selectedValues)): echo 'checked'; endif; ?> class="peer sr-only">
                                    <span class="inline-flex items-center gap-1 rounded-full border border-slate-300 px-3 py-1 peer-checked:border-brand-600 peer-checked:bg-brand-50 peer-checked:text-brand-700">
                                        <?php if($v->color_hex): ?><i class="size-3 rounded-full border border-slate-300" style="background: <?php echo e($v->color_hex); ?>"></i><?php endif; ?><?php echo e($v->value); ?></span></label>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </div></fieldset>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                <fieldset><legend class="mb-2 font-bold"><?php echo e(__('store.price')); ?> <small class="font-normal text-slate-400">(<?php echo e($facets['price']['min']); ?> - <?php echo e($facets['price']['max']); ?>)</small></legend>
                    <div class="flex items-center gap-2">
                        <input type="number" min="0" step="1" name="price_min" value="<?php echo e($filter->priceMin); ?>" placeholder="<?php echo e(__('store.price_from')); ?>" class="form-control !px-2">
                        <input type="number" min="0" step="1" name="price_max" value="<?php echo e($filter->priceMax); ?>" placeholder="<?php echo e(__('store.price_to')); ?>" class="form-control !px-2">
                    </div></fieldset>
                <div class="flex gap-2">
                    <button class="btn btn-primary flex-1"><?php echo e(__('store.apply')); ?></button>
                    <?php if($activeCount): ?><a href="<?php echo e($action); ?><?php echo e($isSearch && $filter->q ? '?q='.urlencode($filter->q) : ''); ?>" class="btn btn-secondary" data-filter-reset><?php echo e(__('store.reset')); ?></a><?php endif; ?>
                </div>
            </form>
        </aside>

        <section>
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-white p-3 ring-1 ring-slate-100">
                <div><h1 class="text-lg font-extrabold"><?php echo e($pageTitle); ?></h1><p class="text-xs text-slate-500" data-count><?php echo e(__('store.results_count', ['n' => $items->total()])); ?></p></div>
                <label class="flex items-center gap-2 text-sm"><?php echo e(__('store.sort')); ?>

                    <select class="form-control !w-auto !py-1.5" data-sort>
                        <?php $__currentLoopData = \App\DTOs\Storefront\CatalogFilter::SORTS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($s); ?>" <?php if($filter->sort === $s): echo 'selected'; endif; ?>><?php echo e(__('store.sorts.'.$s)); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select></label>
            </div>
            <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-4" data-grid><?php echo $__env->make('storefront._grid', ['items' => $items], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></div>
            <div class="mt-6" data-pagination><?php echo e($items->links('storefront._pagination')); ?></div>
        </section>
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
<?php /**PATH /var/www/3alraf/backend/resources/views/storefront/catalog.blade.php ENDPATH**/ ?>