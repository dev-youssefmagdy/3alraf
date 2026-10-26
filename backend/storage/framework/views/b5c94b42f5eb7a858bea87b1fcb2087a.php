<?php
    $cur = current_tenant()->currency_symbol;
    $first = collect($picker['variants'])->firstWhere('in_stock', true) ?? ($picker['variants'][0] ?? null);
    $img = $product->images->first()?->url;
    $desc = \Illuminate\Support\Str::limit(strip_tags((string) ($product->meta_description ?: $product->description)), 160, '');
    $ld = ['@context' => 'https://schema.org', '@type' => 'Product', 'name' => $product->name, 'description' => $desc, 'sku' => $product->sku,
        'image' => $product->images->map->url->values()->all(), 'brand' => $product->brand ? ['@type' => 'Brand', 'name' => $product->brand->name] : null,
        'offers' => ['@type' => 'Offer', 'priceCurrency' => current_tenant()->currency_code, 'price' => $first['price'] ?? (string) $tp->effectivePrice(),
            'availability' => ($first['in_stock'] ?? false) ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock', 'url' => url()->current()]];
    if ($summary['count']) { $ld['aggregateRating'] = ['@type' => 'AggregateRating', 'ratingValue' => $summary['avg'], 'reviewCount' => $summary['count']]; }
    $ld = array_filter($ld, fn ($v) => $v !== null);
?>
<?php if (isset($component)) { $__componentOriginal51ea8e071ed038a4c6542abfe5470d94 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal51ea8e071ed038a4c6542abfe5470d94 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.layouts.storefront','data' => ['title' => $product->meta_title ?: $product->name,'description' => $desc,'image' => $img,'type' => 'product','jsonld' => $ld]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('layouts.storefront'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($product->meta_title ?: $product->name),'description' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($desc),'image' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($img),'type' => 'product','jsonld' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($ld)]); ?>
    <nav class="mb-4 flex flex-wrap items-center gap-1 text-sm text-slate-500" aria-label="breadcrumb">
        <a href="<?php echo e(route('storefront.home')); ?>" class="hover:text-brand-600"><?php echo e(__('store.breadcrumb_home')); ?></a>
        <?php $__currentLoopData = $crumbs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><span>/</span><a href="<?php echo e(route('storefront.category.show', $c->slug)); ?>" class="hover:text-brand-600"><?php echo e($c->name); ?></a><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <span>/</span><span class="font-semibold text-slate-800"><?php echo e($product->name); ?></span>
    </nav>

    <div class="grid gap-8 rounded-2xl bg-white p-4 ring-1 ring-slate-100 lg:grid-cols-2" data-product-page data-product-id="<?php echo e($product->id); ?>" data-picker="<?php echo e(json_encode($picker, JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT | JSON_HEX_APOS)); ?>">
        <div>
            <div class="overflow-hidden rounded-xl bg-slate-50">
                <?php if($img): ?><img src="<?php echo e($img); ?>" alt="<?php echo e($product->name); ?>" class="aspect-square w-full object-contain" data-main-image><?php else: ?><div class="aspect-square bg-slate-100"></div><?php endif; ?>
            </div>
            <?php if($product->images->count() > 1): ?>
                <div class="mt-3 flex gap-2 overflow-x-auto">
                    <?php $__currentLoopData = $product->images; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $im): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><button type="button" data-thumb="<?php echo e($im->url); ?>" class="size-16 shrink-0 overflow-hidden rounded-lg border-2 <?php echo e($loop->first ? 'border-brand-600' : 'border-transparent'); ?>"><img src="<?php echo e($im->thumb_url); ?>" alt="" class="size-full object-cover"></button><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="space-y-4">
            <?php if($product->brand): ?><a href="<?php echo e(route('storefront.search', ['brands' => [$product->brand->id]])); ?>" class="text-sm font-semibold text-brand-600"><?php echo e($product->brand->name); ?></a><?php endif; ?>
            <h1 class="text-2xl font-extrabold"><?php echo e($product->name); ?></h1>
            <?php if($summary['count']): ?><p class="text-sm text-amber-500"><?php echo e(str_repeat('★', (int) round($summary['avg']))); ?><span class="text-slate-300"><?php echo e(str_repeat('★', 5 - (int) round($summary['avg']))); ?></span> <a href="#reviews" class="text-slate-500"><?php echo e($summary['avg']); ?> (<?php echo e(__('store.reviews_count', ['n' => $summary['count']])); ?>)</a></p><?php endif; ?>

            <div class="flex flex-wrap items-baseline gap-3">
                <span class="text-3xl font-extrabold text-brand-600" data-price><?php echo e(number_format((float) ($first['price'] ?? $tp->effectivePrice()), 2)); ?></span><span class="font-semibold text-brand-600"><?php echo e($cur); ?></span>
                <span class="text-base text-slate-400 line-through <?php echo e(($first['old'] ?? null) ? '' : 'hidden'); ?>" data-old><?php echo e(($first['old'] ?? null) ? number_format((float) $first['old'], 2) : ''); ?></span>
            </div>
            <p class="hidden text-sm text-brand-600" data-flash><?php echo e(__('store.ends_in')); ?> <span data-flash-time class="font-mono font-bold"></span></p>
            <p class="text-sm font-semibold" data-stock></p>
            <p class="text-xs text-slate-500"><span><?php echo e(__('store.sku')); ?>: <b data-sku><?php echo e($first['sku'] ?? $product->sku); ?></b></span> &middot; <span><?php echo e(__('store.barcode')); ?>: <b data-barcode><?php echo e($first['barcode'] ?? $product->barcode); ?></b></span></p>

            <?php $__currentLoopData = $picker['options']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $opt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div data-option-group="<?php echo e($opt['id']); ?>">
                    <p class="mb-1 text-sm font-bold"><?php echo e($opt['name']); ?></p>
                    <div class="flex flex-wrap gap-2">
                        <?php $__currentLoopData = $opt['values']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <button type="button" data-option="<?php echo e($v['id']); ?>" class="inline-flex items-center gap-1 rounded-lg border-2 border-slate-200 px-3 py-1.5 text-sm transition disabled:cursor-not-allowed disabled:opacity-40 [&.is-active]:border-brand-600 [&.is-active]:bg-brand-50">
                                <?php if($v['color']): ?><i class="size-4 rounded-full border border-slate-300" style="background: <?php echo e($v['color']); ?>"></i><?php endif; ?><?php echo e($v['label']); ?></button>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <p class="hidden text-sm text-red-600" data-combo-error><?php echo e(__('store.unavailable_combo')); ?></p>

            <div class="flex flex-wrap items-end gap-3 pt-2">
                <label class="text-sm font-semibold"><?php echo e(__('store.qty')); ?><input type="number" min="1" value="1" class="form-control mt-1 w-24" data-qty></label>
                <button type="button" class="btn btn-primary !px-8 !py-3" data-add-to-cart-page><?php echo e(__('store.add_to_cart')); ?></button>
                <?php if(auth()->guard('web')->check()): ?><button type="button" data-wish="<?php echo e(route('storefront.wishlist.toggle', $product->id)); ?>" class="btn btn-secondary !py-3" aria-label="<?php echo e(__('store.wishlist')); ?>"><?php echo e($inWishlist ? '♥' : '♡'); ?> <?php echo e(__('store.wishlist')); ?></button>
                <?php else: ?><a href="<?php echo e(route('storefront.login')); ?>" class="btn btn-secondary !py-3" title="<?php echo e(__('store.wishlist_login')); ?>">♡ <?php echo e(__('store.wishlist')); ?></a><?php endif; ?>
            </div>

            <?php if($product->description): ?>
                <div class="border-t border-slate-100 pt-4"><h2 class="mb-2 font-bold"><?php echo e(__('store.description')); ?></h2><div class="text-sm leading-7 text-slate-600"><?php echo nl2br(e($product->description)); ?></div></div>
            <?php endif; ?>
        </div>
    </div>

    <section id="reviews" class="mt-8 rounded-2xl bg-white p-4 ring-1 ring-slate-100">
        <h2 class="mb-3 text-lg font-extrabold"><?php echo e(__('growth.reviews')); ?></h2>
        <?php $__empty_1 = true; $__currentLoopData = $reviews; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <div class="border-t border-slate-100 py-3 text-sm"><span class="text-amber-500"><?php echo e(str_repeat('★', $r->rating)); ?><span class="text-slate-300"><?php echo e(str_repeat('★', 5 - $r->rating)); ?></span></span> <strong><?php echo e($r->customer?->name); ?></strong><p class="mt-1 text-slate-600"><?php echo e($r->body); ?></p></div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <p class="text-sm text-slate-500"><?php echo e(__('growth.no_reviews')); ?></p>
        <?php endif; ?>
        <?php if($canReview): ?>
            <?php if (isset($component)) { $__componentOriginal53747ceb358d30c0105769f8471417f6 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal53747ceb358d30c0105769f8471417f6 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.card','data' => ['class' => 'mt-4 max-w-lg','title' => __('growth.write_review')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'mt-4 max-w-lg','title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('growth.write_review'))]); ?>
                <?php if($myReview): ?><p class="mb-2 text-xs text-slate-500"><?php echo e(__('growth.your_review_status', ['status' => $myReview->status->label()])); ?></p><?php endif; ?>
                <form data-ajax-form data-validate-url="<?php echo e(route('storefront.reviews.validate', $product->slug)); ?>" action="<?php echo e(route('storefront.reviews.store', $product->slug)); ?>" method="POST" class="space-y-3" novalidate>
                    <?php echo csrf_field(); ?>
                    <?php if (isset($component)) { $__componentOriginaled2cde6083938c436304f332ba96bb7c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginaled2cde6083938c436304f332ba96bb7c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.select','data' => ['name' => 'rating','label' => __('growth.fields.rating'),'options' => [5 => '★★★★★', 4 => '★★★★', 3 => '★★★', 2 => '★★', 1 => '★'],'value' => $myReview?->rating ?? 5]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('select'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'rating','label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('growth.fields.rating')),'options' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute([5 => '★★★★★', 4 => '★★★★', 3 => '★★★', 2 => '★★', 1 => '★']),'value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($myReview?->rating ?? 5)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginaled2cde6083938c436304f332ba96bb7c)): ?>
<?php $attributes = $__attributesOriginaled2cde6083938c436304f332ba96bb7c; ?>
<?php unset($__attributesOriginaled2cde6083938c436304f332ba96bb7c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginaled2cde6083938c436304f332ba96bb7c)): ?>
<?php $component = $__componentOriginaled2cde6083938c436304f332ba96bb7c; ?>
<?php unset($__componentOriginaled2cde6083938c436304f332ba96bb7c); ?>
<?php endif; ?>
                    <?php if (isset($component)) { $__componentOriginal4727f9fd7c3055c2cf9c658d89b16886 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4727f9fd7c3055c2cf9c658d89b16886 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.textarea','data' => ['name' => 'body','label' => __('growth.fields.body'),'value' => $myReview?->body]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('textarea'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'body','label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('growth.fields.body')),'value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($myReview?->body)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4727f9fd7c3055c2cf9c658d89b16886)): ?>
<?php $attributes = $__attributesOriginal4727f9fd7c3055c2cf9c658d89b16886; ?>
<?php unset($__attributesOriginal4727f9fd7c3055c2cf9c658d89b16886); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4727f9fd7c3055c2cf9c658d89b16886)): ?>
<?php $component = $__componentOriginal4727f9fd7c3055c2cf9c658d89b16886; ?>
<?php unset($__componentOriginal4727f9fd7c3055c2cf9c658d89b16886); ?>
<?php endif; ?>
                    <button class="btn btn-primary"><?php echo e(__('growth.submit_review')); ?></button>
                </form>
             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal53747ceb358d30c0105769f8471417f6)): ?>
<?php $attributes = $__attributesOriginal53747ceb358d30c0105769f8471417f6; ?>
<?php unset($__attributesOriginal53747ceb358d30c0105769f8471417f6); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal53747ceb358d30c0105769f8471417f6)): ?>
<?php $component = $__componentOriginal53747ceb358d30c0105769f8471417f6; ?>
<?php unset($__componentOriginal53747ceb358d30c0105769f8471417f6); ?>
<?php endif; ?>
        <?php endif; ?>
    </section>

    <?php if (isset($component)) { $__componentOriginalcb6d9422bf11bb6e2fffe755e2592d2c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalcb6d9422bf11bb6e2fffe755e2592d2c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.rail','data' => ['title' => __('growth.related'),'items' => $related]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('rail'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('growth.related')),'items' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($related)]); ?>
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
<?php /**PATH /var/www/3alraf/backend/resources/views/storefront/product.blade.php ENDPATH**/ ?>