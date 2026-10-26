<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['tp']));

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

foreach (array_filter((['tp']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php
    $p = $tp->product;
    $d = $tp->display ?? app(\App\Services\Storefront\CardPresenter::class)->decorate(collect([$tp]))->first()->display;
    $url = route('storefront.product.show', ['locale' => app()->getLocale(), 'slug' => $p->slug]);
?>
<div <?php echo e($attributes->merge(['class' => 'group relative flex flex-col rounded-xl border border-slate-200 bg-white p-3 transition hover:shadow-lg'])); ?> data-product-card>
    <?php if($d['percent']): ?><span class="absolute start-3 top-3 z-10 rounded-full bg-brand-600 px-2 py-0.5 text-[11px] font-bold text-white"><?php echo e(__('store.off', ['p' => $d['percent']])); ?></span><?php endif; ?>
    <a href="<?php echo e($url); ?>" class="block overflow-hidden rounded-lg">
        <?php if($img = $p->primaryImageUrl()): ?><img src="<?php echo e($img); ?>" alt="<?php echo e($p->name); ?>" loading="lazy" class="aspect-square w-full object-cover transition group-hover:scale-105">
        <?php else: ?><div class="aspect-square w-full bg-slate-100"></div><?php endif; ?>
    </a>
    <a href="<?php echo e($url); ?>" class="mt-2 line-clamp-2 min-h-10 text-sm font-semibold text-slate-800 hover:text-brand-600"><?php echo e($p->name); ?></a>
    <?php if($d['rating']): ?><p class="mt-1 text-xs text-amber-500"><?php echo e(str_repeat('★', (int) round($d['rating']))); ?><span class="text-slate-300"><?php echo e(str_repeat('★', 5 - (int) round($d['rating']))); ?></span> <span class="text-slate-400">(<?php echo e($d['reviews']); ?>)</span></p><?php endif; ?>
    <div class="mt-1 flex flex-wrap items-baseline gap-x-2">
        <span class="text-base font-bold <?php echo e($d['flash'] ? 'text-brand-600' : 'text-slate-900'); ?>"><?php if($d['from'] ?? false): ?><small class="text-xs font-medium text-slate-500"><?php echo e(__('store.from_price')); ?></small> <?php endif; ?><?php echo e(number_format((float) $d['price'], 2)); ?> <small class="text-xs font-medium"><?php echo e(current_tenant()->currency_symbol); ?></small></span>
        <?php if($d['old']): ?><span class="text-xs text-slate-400 line-through"><?php echo e(number_format((float) $d['old'], 2)); ?></span><?php endif; ?>
    </div>
    <?php if($d['flash'] && $d['ends_at']): ?><p class="mt-1 text-[11px] text-brand-600"><?php echo e(__('store.ends_in')); ?> <span data-countdown="<?php echo e($d['ends_at']); ?>" class="font-mono font-bold"></span></p><?php endif; ?>
    <div class="mt-auto pt-3">
        <?php if($p->isVariable()): ?>
            <a href="<?php echo e($url); ?>" class="btn btn-secondary w-full !py-1.5"><?php echo e(__('store.choose_options')); ?></a>
        <?php else: ?>
            <button type="button" class="btn btn-primary w-full !py-1.5" data-add-to-cart data-product="<?php echo e($p->id); ?>" data-variant="0"><?php echo e(__('store.add_to_cart')); ?></button>
        <?php endif; ?>
    </div>
</div>
<?php /**PATH /var/www/3alraf/backend/resources/views/components/product-card.blade.php ENDPATH**/ ?>