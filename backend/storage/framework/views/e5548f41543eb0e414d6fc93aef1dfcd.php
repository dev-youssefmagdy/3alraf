<a href="<?php echo e(route('storefront.bundle.show', $b['slug'])); ?>" class="group block overflow-hidden rounded-2xl bg-white ring-1 ring-slate-100 transition hover:shadow-md">
    <div class="aspect-square bg-slate-50"><?php if($b['image']): ?><img src="<?php echo e($b['image']); ?>" alt="<?php echo e($b['name']); ?>" class="size-full object-cover" loading="lazy"><?php elseif(! empty($b['components'][0]['image'])): ?><img src="<?php echo e($b['components'][0]['image']); ?>" alt="" class="size-full object-cover" loading="lazy"><?php endif; ?></div>
    <div class="space-y-1 p-3">
        <span class="inline-block rounded bg-brand-50 px-2 py-0.5 text-xs font-bold text-brand-600"><?php echo e(__('store.bundle')); ?></span>
        <p class="line-clamp-2 font-semibold"><?php echo e($b['name']); ?></p>
        <p><b class="text-brand-600"><?php echo e(number_format((float) $b['price'], 2)); ?></b> <small><?php echo e(current_tenant()->currency_symbol); ?></small>
            <?php if(bccomp($b['savings'], '0', 2) > 0): ?><span class="ms-1 text-xs text-slate-400 line-through"><?php echo e(number_format((float) $b['regular_total'], 2)); ?></span><?php endif; ?></p>
        <?php if (! ($b['in_stock'])): ?><p class="text-xs font-semibold text-red-600"><?php echo e(__('store.sold_out')); ?></p><?php endif; ?>
    </div>
</a>
<?php /**PATH /var/www/3alraf/backend/resources/views/storefront/_bundle-card.blade.php ENDPATH**/ ?>