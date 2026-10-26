<?php if (isset($component)) { $__componentOriginal51ea8e071ed038a4c6542abfe5470d94 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal51ea8e071ed038a4c6542abfe5470d94 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.layouts.storefront','data' => ['title' => __('store.checkout'),'noindex' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('layouts.storefront'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('store.checkout')),'noindex' => true]); ?>
    <h1 class="mb-4 text-2xl font-extrabold"><?php echo e(__('store.checkout')); ?></h1>
    <form data-ajax-form data-checkout data-validate-url="<?php echo e(route('storefront.checkout.validate')); ?>" action="<?php echo e(route('storefront.checkout.store')); ?>" method="POST" class="grid gap-6 lg:grid-cols-[1fr_22rem]" novalidate>
        <?php echo csrf_field(); ?>
        <div class="space-y-5">
            <?php if (isset($component)) { $__componentOriginal53747ceb358d30c0105769f8471417f6 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal53747ceb358d30c0105769f8471417f6 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.card','data' => ['title' => __('store.shipping_address')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('store.shipping_address'))]); ?>
                <div class="space-y-3">
                    <div class="space-y-2" data-address-list data-list-url="<?php echo e(route('storefront.checkout.addresses')); ?>"><?php echo $__env->make('storefront._checkout-addresses', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></div>
                    <button type="button" class="btn btn-secondary w-full" data-address-add>+ <?php echo e(__('store.add_address')); ?></button>
                    <?php if (isset($component)) { $__componentOriginalc2fcfa88dc54fee60e0757a7e0572df1 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc2fcfa88dc54fee60e0757a7e0572df1 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.input','data' => ['name' => 'notes','label' => __('store.notes')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'notes','label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('store.notes'))]); ?>
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

            <?php if (isset($component)) { $__componentOriginal53747ceb358d30c0105769f8471417f6 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal53747ceb358d30c0105769f8471417f6 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.card','data' => ['title' => __('store.shipping_method')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('store.shipping_method'))]); ?>
                <div class="space-y-2 text-sm" data-shipping-options><p class="text-slate-500"><?php echo e(__('store.select_city_hint')); ?></p></div>
                <input type="hidden" name="shipping_company_id" value="" data-shipping-input>
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

            <?php if (isset($component)) { $__componentOriginal53747ceb358d30c0105769f8471417f6 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal53747ceb358d30c0105769f8471417f6 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.card','data' => ['title' => __('store.payment_method')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('store.payment_method'))]); ?>
                <div class="space-y-2 text-sm" data-payment-options>
                    <?php $__currentLoopData = $methods; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $m): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <label class="flex cursor-pointer items-start gap-2 rounded-lg border border-slate-200 p-3 has-[:checked]:border-brand-600 has-[:checked]:bg-brand-50 has-[:disabled]:opacity-50" data-method-row>
                            <input type="radio" name="payment_method_id" value="<?php echo e($m->id); ?>" data-type="<?php echo e($m->type->value); ?>" data-name="<?php echo e($m->name); ?>" data-instructions="<?php echo e($m->instructions); ?>" data-account="<?php echo e($m->account_number); ?>" class="mt-1 accent-brand-600">
                            <span><b><?php echo e($m->name); ?></b>
                                <?php ($feeText = (float) $m->fee_percent > 0 ? rtrim(rtrim(number_format((float) $m->fee_percent, 3), '0'), '.').'%' : ((float) $m->fee_fixed > 0 ? number_format((float) $m->fee_fixed, 2) : null)); ?>
                                <?php if($feeText): ?><small class="text-slate-500"> (<?php echo e(__('store.fee_note', ['fee' => $feeText])); ?>)</small><?php endif; ?>
                                <?php if($m->instructions): ?><span class="mt-1 block text-xs text-slate-500"><?php echo e($m->instructions); ?></span><?php endif; ?>
                                <?php if($m->account_number): ?><span class="mt-1 block text-xs text-slate-600"><?php echo e(__('store.transfer_to')); ?>: <b><?php echo e($m->account_number); ?></b></span><?php endif; ?>
                                <span class="hidden text-xs text-red-600" data-cod-note><?php echo e(__('store.cod_not_for_shipping')); ?></span></span>
                        </label>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
                <?php if($codUpfront): ?>
                    <div class="mt-4 hidden space-y-2 border-t border-slate-100 pt-3 text-sm" data-prepay>
                        <h3 class="font-bold"><?php echo e(__('store.prepay_title')); ?></h3>
                        <p class="text-xs text-slate-500"><?php echo e(__('store.prepay_hint')); ?></p>
                        <?php $__currentLoopData = $methods->whereIn('type', [\App\Enums\PaymentMethodType::ONLINE, \App\Enums\PaymentMethodType::WALLET]); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $m): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <label class="flex cursor-pointer items-start gap-2 rounded-lg border border-slate-200 p-3 has-[:checked]:border-brand-600 has-[:checked]:bg-brand-50">
                                <input type="radio" name="prepay_method_id" value="<?php echo e($m->id); ?>" data-type="<?php echo e($m->type->value); ?>" data-name="<?php echo e($m->name); ?>" data-instructions="<?php echo e($m->instructions); ?>" data-account="<?php echo e($m->account_number); ?>" class="mt-1 accent-brand-600">
                                <span><b><?php echo e($m->name); ?></b>
                                    <?php if($m->account_number): ?><span class="mt-1 block text-xs text-slate-600"><?php echo e(__('store.transfer_to')); ?>: <b><?php echo e($m->account_number); ?></b></span><?php endif; ?></span>
                            </label>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                <?php endif; ?>
                <?php ($proofKb = (int) config('checkout.proof.max_kb')); ?>
                <div class="mt-4 hidden space-y-2 border-t border-slate-100 pt-3 text-sm" data-offline-proof>
                    <h3 class="font-bold"><?php echo e(__('store.offline_proof_title')); ?></h3>
                    <div class="rounded-lg bg-slate-50 p-3 text-xs text-slate-600" data-offline-details></div>
                    <p class="text-xs text-slate-500"><?php echo e(__('store.offline_proof_hint', ['mb' => round($proofKb / 1024, 1)])); ?></p>
                    <div data-field>
                        <input type="file" name="proof" class="form-control" accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf" disabled
                            data-max-kb="<?php echo e($proofKb); ?>" data-msg-transfer="<?php echo e(__('store.transfer_to')); ?>" data-msg-type="<?php echo e(__('store.proof_client_type')); ?>" data-msg-size="<?php echo e(__('store.proof_client_size', ['mb' => round($proofKb / 1024, 1)])); ?>">
                    </div>
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
        </div>

        <aside class="h-fit space-y-3 rounded-2xl bg-white p-4 ring-1 ring-slate-100 lg:sticky lg:top-32">
            <h2 class="font-extrabold"><?php echo e(__('store.order_summary')); ?></h2>
            <div class="divide-y divide-slate-100 text-sm" data-checkout-lines></div>
            <div class="flex gap-2" data-field>
                <input type="text" name="discount_code" class="form-control" placeholder="<?php echo e(__('store.discount_code')); ?>" data-code-input>
                <button type="button" class="btn btn-secondary shrink-0" data-apply-code><?php echo e(__('store.apply_code')); ?></button>
            </div>
            <?php echo $__env->make('storefront._packaging', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <dl class="space-y-2 border-t border-slate-100 pt-3 text-sm" data-totals></dl>
            <button type="submit" class="btn btn-primary w-full !py-3"><?php echo e(__('store.place_order')); ?></button>
        </aside>
    </form>

    
    <?php if (isset($component)) { $__componentOriginal9f64f32e90b9102968f2bc548315018c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9f64f32e90b9102968f2bc548315018c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.modal','data' => ['id' => 'address-modal','title' => __('store.add_address'),'dataTitleAdd' => ''.e(__('store.add_address')).'','dataTitleEdit' => ''.e(__('store.edit_address')).'']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('modal'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'address-modal','title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('store.add_address')),'data-title-add' => ''.e(__('store.add_address')).'','data-title-edit' => ''.e(__('store.edit_address')).'']); ?>
        <form data-ajax-form data-address-form data-validate-url="<?php echo e(route('storefront.account.addresses.validate')); ?>" data-store-url="<?php echo e(route('storefront.account.addresses.store')); ?>" action="<?php echo e(route('storefront.account.addresses.store')); ?>" method="POST" class="grid gap-3 sm:grid-cols-2" novalidate>
            <?php echo csrf_field(); ?>
            <input type="hidden" name="inline" value="1">
            <?php if (isset($component)) { $__componentOriginalc2fcfa88dc54fee60e0757a7e0572df1 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc2fcfa88dc54fee60e0757a7e0572df1 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.input','data' => ['name' => 'label','label' => __('store.label'),'class' => 'sm:col-span-2']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'label','label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('store.label')),'class' => 'sm:col-span-2']); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.input','data' => ['name' => 'name','label' => __('store.name'),'value' => $customer->name,'required' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'name','label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('store.name')),'value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($customer->name),'required' => true]); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.input','data' => ['name' => 'phone','type' => 'tel','label' => __('store.phone'),'value' => $customer->phone,'required' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'phone','type' => 'tel','label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('store.phone')),'value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($customer->phone),'required' => true]); ?>
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
            <?php if (isset($component)) { $__componentOriginaled2cde6083938c436304f332ba96bb7c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginaled2cde6083938c436304f332ba96bb7c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.select','data' => ['name' => 'city_id','label' => __('store.city'),'options' => $cities->mapWithKeys(fn ($c) => [$c->id => $c->name])->all(),'placeholder' => __('store.select_city'),'required' => true,'class' => 'sm:col-span-2']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('select'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'city_id','label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('store.city')),'options' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($cities->mapWithKeys(fn ($c) => [$c->id => $c->name])->all()),'placeholder' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('store.select_city')),'required' => true,'class' => 'sm:col-span-2']); ?>
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
            <?php if (isset($component)) { $__componentOriginalc2fcfa88dc54fee60e0757a7e0572df1 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc2fcfa88dc54fee60e0757a7e0572df1 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.input','data' => ['name' => 'address','label' => __('store.address'),'required' => true,'class' => 'sm:col-span-2']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'address','label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('store.address')),'required' => true,'class' => 'sm:col-span-2']); ?>
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
            <label class="flex items-center gap-2 text-sm sm:col-span-2"><input type="checkbox" name="is_default" value="1" data-bool class="accent-brand-600"> <?php echo e(__('store.set_default')); ?></label>
            <div class="flex justify-end gap-2 sm:col-span-2">
                <button type="button" class="btn btn-secondary" data-modal-close><?php echo e(__('admin.cancel')); ?></button>
                <button type="submit" class="btn btn-primary"><?php echo e(__('store.save')); ?></button>
            </div>
        </form>
     <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9f64f32e90b9102968f2bc548315018c)): ?>
<?php $attributes = $__attributesOriginal9f64f32e90b9102968f2bc548315018c; ?>
<?php unset($__attributesOriginal9f64f32e90b9102968f2bc548315018c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9f64f32e90b9102968f2bc548315018c)): ?>
<?php $component = $__componentOriginal9f64f32e90b9102968f2bc548315018c; ?>
<?php unset($__componentOriginal9f64f32e90b9102968f2bc548315018c); ?>
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
<?php /**PATH /var/www/3alraf/backend/resources/views/storefront/checkout.blade.php ENDPATH**/ ?>