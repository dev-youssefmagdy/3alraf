<?php if (isset($component)) { $__componentOriginalc8c9fd5d7827a77a31381de67195f0c3 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc8c9fd5d7827a77a31381de67195f0c3 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.layouts.admin','data' => ['guard' => 'tenant','title' => __('admin.edit').': '.$tp->product->name]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('layouts.admin'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['guard' => 'tenant','title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('admin.edit').': '.$tp->product->name)]); ?>
    <?php if (isset($component)) { $__componentOriginal53747ceb358d30c0105769f8471417f6 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal53747ceb358d30c0105769f8471417f6 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.card','data' => ['class' => 'max-w-4xl']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'max-w-4xl']); ?>
        <form data-ajax-form data-method="PUT" data-validate-url="<?php echo e(route('tenant.catalog.validate', $tp)); ?>"
            action="<?php echo e(route('tenant.catalog.update', $tp)); ?>" method="POST" class="space-y-5" novalidate>
            <?php echo csrf_field(); ?>
            <div class="flex items-center gap-3">
                <?php if($u = $tp->product->primaryImageUrl()): ?><img src="<?php echo e($u); ?>" class="size-16 rounded object-cover" alt=""><?php endif; ?>
                <div><div class="font-semibold"><?php echo e($tp->product->name); ?></div><div class="text-xs text-slate-500"><?php echo e($tp->product->sku); ?></div></div>
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <?php if (isset($component)) { $__componentOriginalc2fcfa88dc54fee60e0757a7e0572df1 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc2fcfa88dc54fee60e0757a7e0572df1 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.input','data' => ['name' => 'price','type' => 'number','step' => '0.01','min' => '0','label' => __('admin.fields.price'),'value' => $tp->price,'required' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'price','type' => 'number','step' => '0.01','min' => '0','label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('admin.fields.price')),'value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($tp->price),'required' => true]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc2fcfa88dc54fee60e0757a7e0572df1)): ?>
<?php $attributes = $__attributesOriginalc2fcfa88dc54fee60e0757a7e0572df1; ?>
<?php unset($__attributesOriginalc2fcfa88dc54fee60e0757a7e0572df1); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc2fcfa88dc54fee60e0757a7e0572df1)): ?>
<?php $component = $__componentOriginalc2fcfa88dc54fee60e0757a7e0572df1; ?>
<?php unset($__componentOriginalc2fcfa88dc54fee60e0757a7e0572df1); ?>
<?php endif; ?>
                <?php if (isset($component)) { $__componentOriginalc2fcfa88dc54fee60e0757a7e0572df1 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc2fcfa88dc54fee60e0757a7e0572df1 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.input','data' => ['name' => 'sale_price','type' => 'number','step' => '0.01','min' => '0','label' => __('admin.fields.sale_price'),'value' => $tp->sale_price]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'sale_price','type' => 'number','step' => '0.01','min' => '0','label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('admin.fields.sale_price')),'value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($tp->sale_price)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc2fcfa88dc54fee60e0757a7e0572df1)): ?>
<?php $attributes = $__attributesOriginalc2fcfa88dc54fee60e0757a7e0572df1; ?>
<?php unset($__attributesOriginalc2fcfa88dc54fee60e0757a7e0572df1); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc2fcfa88dc54fee60e0757a7e0572df1)): ?>
<?php $component = $__componentOriginalc2fcfa88dc54fee60e0757a7e0572df1; ?>
<?php unset($__componentOriginalc2fcfa88dc54fee60e0757a7e0572df1); ?>
<?php endif; ?>
                <?php if (isset($component)) { $__componentOriginalc2fcfa88dc54fee60e0757a7e0572df1 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc2fcfa88dc54fee60e0757a7e0572df1 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.input','data' => ['name' => 'cost','type' => 'number','step' => '0.01','min' => '0','label' => __('admin.fields.cost'),'value' => $tp->cost,'required' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'cost','type' => 'number','step' => '0.01','min' => '0','label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('admin.fields.cost')),'value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($tp->cost),'required' => true]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc2fcfa88dc54fee60e0757a7e0572df1)): ?>
<?php $attributes = $__attributesOriginalc2fcfa88dc54fee60e0757a7e0572df1; ?>
<?php unset($__attributesOriginalc2fcfa88dc54fee60e0757a7e0572df1); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc2fcfa88dc54fee60e0757a7e0572df1)): ?>
<?php $component = $__componentOriginalc2fcfa88dc54fee60e0757a7e0572df1; ?>
<?php unset($__componentOriginalc2fcfa88dc54fee60e0757a7e0572df1); ?>
<?php endif; ?>
            </div>
            <?php if (isset($component)) { $__componentOriginal74b62b190a03153f11871f645315f4de = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal74b62b190a03153f11871f645315f4de = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.checkbox','data' => ['name' => 'is_active','label' => __('admin.fields.is_active'),'checked' => $tp->is_active]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('checkbox'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'is_active','label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('admin.fields.is_active')),'checked' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($tp->is_active)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal74b62b190a03153f11871f645315f4de)): ?>
<?php $attributes = $__attributesOriginal74b62b190a03153f11871f645315f4de; ?>
<?php unset($__attributesOriginal74b62b190a03153f11871f645315f4de); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal74b62b190a03153f11871f645315f4de)): ?>
<?php $component = $__componentOriginal74b62b190a03153f11871f645315f4de; ?>
<?php unset($__componentOriginal74b62b190a03153f11871f645315f4de); ?>
<?php endif; ?>

            <?php if($tp->variants->isNotEmpty()): ?>
                <div>
                    <h3 class="mb-1 font-semibold"><?php echo e(__('admin.variants')); ?></h3>
                    <p class="mb-2 text-xs text-slate-500"><?php echo e(__('admin.variant_override_hint')); ?></p>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead><tr class="text-xs uppercase text-slate-500"><th class="text-start"><?php echo e(__('admin.variant')); ?></th><th class="text-start"><?php echo e(__('admin.fields.price')); ?></th><th class="text-start"><?php echo e(__('admin.fields.sale_price')); ?></th><th class="text-start"><?php echo e(__('admin.fields.cost')); ?></th><th class="text-start"><?php echo e(__('admin.fields.is_active')); ?></th></tr></thead>
                            <tbody>
                            <?php $__currentLoopData = $tp->variants->filter(fn ($tv) => $tv->variant && ! $tv->variant->trashed())->values(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $tv): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <tr class="border-t border-slate-100">
                                    <td class="py-2">
                                        <input type="hidden" name="variants[<?php echo e($i); ?>][id]" value="<?php echo e($tv->id); ?>">
                                        <?php echo e($tv->variant->label()); ?> <span class="text-xs text-slate-400"><?php echo e($tv->variant->sku); ?></span>
                                    </td>
                                    <td data-field><input type="number" step="0.01" min="0" name="variants[<?php echo e($i); ?>][price]" value="<?php echo e($tv->price); ?>" placeholder="<?php echo e($tp->price); ?>" class="form-control !w-28"></td>
                                    <td data-field><input type="number" step="0.01" min="0" name="variants[<?php echo e($i); ?>][sale_price]" value="<?php echo e($tv->sale_price); ?>" class="form-control !w-28"></td>
                                    <td data-field><input type="number" step="0.01" min="0" name="variants[<?php echo e($i); ?>][cost]" value="<?php echo e($tv->cost); ?>" placeholder="<?php echo e($tp->cost); ?>" class="form-control !w-28"></td>
                                    <td><input type="hidden" name="variants[<?php echo e($i); ?>][is_active]" value="0"><input type="checkbox" name="variants[<?php echo e($i); ?>][is_active]" value="1" <?php if($tv->is_active): echo 'checked'; endif; ?> class="size-4 rounded border-slate-300 text-brand-600"></td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
            <?php if (isset($component)) { $__componentOriginal98d29adaa4406bec3dfa33b112d9a220 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal98d29adaa4406bec3dfa33b112d9a220 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.form-actions','data' => ['cancel' => route('tenant.catalog.index')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('form-actions'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['cancel' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('tenant.catalog.index'))]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal98d29adaa4406bec3dfa33b112d9a220)): ?>
<?php $attributes = $__attributesOriginal98d29adaa4406bec3dfa33b112d9a220; ?>
<?php unset($__attributesOriginal98d29adaa4406bec3dfa33b112d9a220); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal98d29adaa4406bec3dfa33b112d9a220)): ?>
<?php $component = $__componentOriginal98d29adaa4406bec3dfa33b112d9a220; ?>
<?php unset($__componentOriginal98d29adaa4406bec3dfa33b112d9a220); ?>
<?php endif; ?>
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
    <?php ($pq = $qrCodes->get(\App\Models\TenantProduct::class.':'.$tp->id)); ?>
    <?php if (isset($component)) { $__componentOriginal53747ceb358d30c0105769f8471417f6 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal53747ceb358d30c0105769f8471417f6 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.card','data' => ['class' => 'mt-4 max-w-4xl']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'mt-4 max-w-4xl']); ?>
        <h3 class="mb-1 font-semibold"><?php echo e(__('admin.qr_code')); ?></h3>
        <p class="mb-3 text-xs text-slate-500"><?php echo e(__('admin.qr_hint')); ?></p>
        <div class="flex flex-wrap gap-6">
            <div class="text-center">
                <img src="<?php echo e($pq?->file_url ?? route('tenant.catalog.qr', $tp)); ?>" alt="QR" class="size-40 rounded border border-slate-200" loading="lazy">
                <div class="mt-1 text-xs text-slate-500"><?php echo e(__('admin.qr_product')); ?></div>
                <a href="<?php echo e(route('tenant.catalog.qr', $tp)); ?>" class="btn-sm btn-secondary mt-2" download><?php echo e(__('admin.qr_download')); ?></a>
            </div>
            <?php $__currentLoopData = $tp->variants->filter(fn ($tv) => $tv->is_active && $tv->variant && ! $tv->variant->trashed() && $tv->variant->is_active); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tv): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php ($vq = $qrCodes->get(\App\Models\TenantProductVariant::class.':'.$tv->id)); ?>
                <div class="text-center">
                    <img src="<?php echo e($vq?->file_url ?? route('tenant.catalog.qr', [$tp, 'variant' => $tv->id])); ?>" alt="QR" class="size-40 rounded border border-slate-200" loading="lazy">
                    <div class="mt-1 text-xs text-slate-500"><?php echo e($tv->variant->label()); ?></div>
                    <a href="<?php echo e(route('tenant.catalog.qr', [$tp, 'variant' => $tv->id])); ?>" class="btn-sm btn-secondary mt-2" download><?php echo e(__('admin.qr_download')); ?></a>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
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
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc8c9fd5d7827a77a31381de67195f0c3)): ?>
<?php $attributes = $__attributesOriginalc8c9fd5d7827a77a31381de67195f0c3; ?>
<?php unset($__attributesOriginalc8c9fd5d7827a77a31381de67195f0c3); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc8c9fd5d7827a77a31381de67195f0c3)): ?>
<?php $component = $__componentOriginalc8c9fd5d7827a77a31381de67195f0c3; ?>
<?php unset($__componentOriginalc8c9fd5d7827a77a31381de67195f0c3); ?>
<?php endif; ?>
<?php /**PATH /var/www/3alraf/backend/resources/views/tenant/catalog/form.blade.php ENDPATH**/ ?>