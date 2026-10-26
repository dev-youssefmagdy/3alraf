<?php $pick = $selectedAddress ?? ($addresses->firstWhere('is_default', true)?->id ?? $addresses->first()?->id); ?>
<?php $__empty_1 = true; $__currentLoopData = $addresses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $a): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
    <label class="flex cursor-pointer items-start gap-3 rounded-xl border-2 border-slate-200 bg-white p-3 text-sm transition has-[:checked]:border-brand-600 has-[:checked]:bg-brand-50">
        <input type="radio" name="address_id" value="<?php echo e($a->id); ?>" data-city="<?php echo e($a->city_id); ?>" class="mt-1 accent-brand-600" <?php if($pick === $a->id): echo 'checked'; endif; ?>>
        <span class="min-w-0 flex-1">
            <span class="flex flex-wrap items-center gap-2"><b><?php echo e($a->label ?: $a->name); ?></b> <?php if($a->is_default): ?><span class="badge badge-success"><?php echo e(__('store.default')); ?></span><?php endif; ?></span>
            <span class="block text-slate-600"><?php echo e($a->name); ?> · <bdi dir="ltr"><?php echo e($a->phone); ?></bdi></span>
            <span class="block text-slate-500"><?php echo e($a->city?->name); ?> - <?php echo e($a->address); ?></span>
        </span>
        <button type="button" class="btn-sm shrink-0 bg-slate-100 text-slate-700 hover:bg-slate-200" data-address-edit data-address="<?php echo e(json_encode(['id' => $a->id, 'label' => $a->label, 'name' => $a->name, 'phone' => $a->phone, 'city_id' => $a->city_id, 'address' => $a->address, 'is_default' => (bool) $a->is_default, 'url' => route('storefront.account.addresses.update', ['locale' => app()->getLocale(), 'id' => $a->id])])); ?>"><?php echo e(__('store.edit')); ?></button>
    </label>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
    <p class="rounded-xl border border-dashed border-slate-300 p-4 text-center text-sm text-slate-500"><?php echo e(__('store.no_addresses_hint')); ?></p>
<?php endif; ?>
<?php /**PATH /var/www/3alraf/backend/resources/views/storefront/_checkout-addresses.blade.php ENDPATH**/ ?>