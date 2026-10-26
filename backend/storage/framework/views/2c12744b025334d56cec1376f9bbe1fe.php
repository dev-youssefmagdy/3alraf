<?php
    use App\Enums\OrderStatus as S;
    $cur = current_tenant()->currency_symbol;
    $flow = [S::PENDING, S::CONFIRMED, S::PROCESSING, S::SHIPPED, S::DELIVERED];
    $idx = collect($flow)->search(fn ($s) => $s === $order->status);
    $mine = $order->customer_id && $order->customer_id === auth('web')->id();
    $payer = $order->payingMethod();
    $isWallet = $payer?->type?->value === 'wallet';
    $canPayOnline = $payer?->type?->value === 'online' && in_array($order->payment_status->value, ['unpaid', 'failed'], true) && ! in_array($order->status, [S::CANCELLED, S::RETURNED], true);
    $canProof = $isWallet && in_array($order->payment_status->value, ['unpaid', 'failed'], true) && ! in_array($order->status, [S::CANCELLED, S::RETURNED], true);
    $money = fn ($v) => number_format((float) $v, 2).' '.$cur;
?>
<?php if (isset($component)) { $__componentOriginal51ea8e071ed038a4c6542abfe5470d94 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal51ea8e071ed038a4c6542abfe5470d94 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.layouts.storefront','data' => ['title' => __('store.order_received').' '.$order->number,'noindex' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('layouts.storefront'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('store.order_received').' '.$order->number),'noindex' => true]); ?>
    <div class="mx-auto max-w-4xl space-y-5">
        <div class="rounded-2xl bg-white p-6 text-center ring-1 ring-slate-100">
            <div class="mx-auto mb-3 grid size-14 place-items-center rounded-full bg-emerald-50 text-3xl text-emerald-600">&#10003;</div>
            <h1 class="text-2xl font-extrabold"><?php echo e(__('store.order_received')); ?></h1>
            <p class="mt-1 text-slate-500"><?php echo e(__('store.thank_you')); ?></p>
            <p class="mt-3 text-lg"><?php echo e(__('store.order_number')); ?>: <b class="text-brand-600"><?php echo e($order->number); ?></b></p>
            <p class="mt-1 text-xs text-slate-400"><?php echo e(__('store.bookmark')); ?></p>
            <div class="mt-4 flex flex-wrap justify-center gap-2">
                <a class="btn btn-secondary" href="<?php echo e(route('storefront.orders.show', ['token' => $order->public_token, 'invoice' => 1])); ?>" target="_blank"><?php echo e(__('store.print_invoice')); ?></a>
                <a class="btn btn-secondary" href="<?php echo e(route('storefront.orders.invoice.pdf', ['token' => $order->public_token])); ?>">PDF</a>
                <a class="btn btn-primary" href="<?php echo e(route('storefront.home')); ?>"><?php echo e(__('store.continue_shopping')); ?></a>
            </div>
        </div>

        <section class="rounded-2xl bg-white p-5 ring-1 ring-slate-100">
            <h2 class="mb-4 font-extrabold"><?php echo e(__('store.timeline')); ?></h2>
            <?php if($idx === false): ?>
                <span class="badge <?php echo e($order->status->color()); ?>"><?php echo e($order->status->label()); ?></span>
            <?php else: ?>
                <ol class="flex items-start justify-between gap-1 text-center text-xs">
                    <?php $__currentLoopData = $flow; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <li class="flex-1"><span class="mx-auto mb-1 grid size-8 place-items-center rounded-full <?php echo e($i <= $idx ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-400'); ?>"><?php echo e($i <= $idx ? '✓' : $i + 1); ?></span>
                            <span class="<?php echo e($i <= $idx ? 'font-bold text-slate-800' : 'text-slate-400'); ?>"><?php echo e($s->label()); ?></span></li>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </ol>
            <?php endif; ?>
            <ul class="mt-4 space-y-1 border-t border-slate-100 pt-3 text-xs text-slate-500">
                <?php $__currentLoopData = $order->histories->sortByDesc('id'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $h): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($h->created_at?->format('Y-m-d H:i')); ?> - <b><?php echo e($h->to_status instanceof S ? $h->to_status->label() : $h->to_status); ?></b> <?php if($h->note): ?><span>(<?php echo e($h->note); ?>)</span><?php endif; ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </ul>
        </section>

        <div class="grid gap-5 md:grid-cols-2">
            <section class="space-y-1 rounded-2xl bg-white p-5 text-sm ring-1 ring-slate-100">
                <h2 class="mb-2 font-extrabold"><?php echo e(__('store.shipping_address')); ?></h2>
                <p><b><?php echo e($order->customer_name); ?></b> - <?php echo e($order->customer_phone); ?></p>
                <p><?php echo e($order->city?->name); ?> - <?php echo e($order->address); ?></p>
                <p class="text-slate-500"><?php echo e($order->shippingCompany?->name); ?></p>
                <h2 class="mb-2 mt-4 font-extrabold"><?php echo e(__('store.payment_method')); ?></h2>
                <p><?php echo e($order->paymentMethod?->name); ?> <span class="badge badge-muted"><?php echo e($order->payment_status->label()); ?></span></p>
                <?php if($order->prepay_method_id): ?>
                    <p><?php echo e(__('orders.prepaid_shipping')); ?>: <b><?php echo e($money($order->prepaid_shipping)); ?></b> (<?php echo e($payer?->name); ?>)</p>
                    <p><?php echo e(__('orders.cod_amount')); ?>: <b><?php echo e($money($order->cod_amount)); ?></b></p>
                <?php endif; ?>
                <?php if($payer?->instructions): ?><p class="text-xs text-slate-500"><?php echo e($payer->instructions); ?></p><?php endif; ?>
                <?php if($isWallet && $payer->account_number): ?><p><?php echo e(__('store.transfer_to')); ?>: <b><?php echo e($payer->account_number); ?></b></p><?php endif; ?>
                <?php if($order->payment_proof): ?><a class="text-brand-600 underline" target="_blank" href="<?php echo e(route('storefront.orders.proof.file', $order->public_token)); ?>"><?php echo e(__('orders.view_proof')); ?></a> <span class="badge badge-muted"><?php echo e($order->proof_status?->label()); ?></span><?php endif; ?>
                <?php if($canPayOnline): ?>
                    <button type="button" class="btn btn-primary mt-3" data-pay-action="<?php echo e(route('storefront.orders.pay', $order->public_token)); ?>"><?php echo e(__('orders.pay_now')); ?></button>
                <?php endif; ?>
                <?php if($canProof): ?>
                    <form data-ajax-form action="<?php echo e(route('storefront.orders.proof', $order->public_token)); ?>" method="POST" enctype="multipart/form-data" class="mt-3 space-y-2" novalidate>
                        <?php echo csrf_field(); ?>
                        <?php if (isset($component)) { $__componentOriginalc2fcfa88dc54fee60e0757a7e0572df1 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc2fcfa88dc54fee60e0757a7e0572df1 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.input','data' => ['name' => 'proof','type' => 'file','accept' => 'image/*,.pdf','label' => __('store.upload_proof')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'proof','type' => 'file','accept' => 'image/*,.pdf','label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('store.upload_proof'))]); ?>
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
                        <button class="btn btn-primary"><?php echo e(__('store.send')); ?></button>
                    </form>
                <?php endif; ?>
            </section>

            <section class="rounded-2xl bg-white p-5 text-sm ring-1 ring-slate-100">
                <h2 class="mb-2 font-extrabold"><?php echo e(__('store.order_summary')); ?></h2>
                <dl class="space-y-2">
                    <div class="flex justify-between"><dt><?php echo e(__('store.subtotal')); ?></dt><dd><?php echo e($money($order->subtotal)); ?></dd></div>
                    <?php if((float) $order->discount > 0): ?><div class="flex justify-between text-emerald-600"><dt><?php echo e(__('store.discount')); ?></dt><dd>-<?php echo e($money($order->discount)); ?></dd></div><?php endif; ?>
                    <div class="flex justify-between"><dt><?php echo e(__('store.shipping')); ?></dt><dd><?php echo e((float) $order->shipping_net > 0 ? $money($order->shipping_net) : __('store.free')); ?></dd></div>
                    <?php if((float) $order->packaging_fee > 0): ?><div class="flex justify-between"><dt><?php echo e(__('store.packaging')); ?>: <?php echo e($order->packagingLabel()); ?></dt><dd><?php echo e($money($order->packaging_fee)); ?></dd></div><?php endif; ?>
                    <?php if((float) $order->taxTotal() > 0): ?><div class="flex justify-between"><dt><?php echo e(__('store.tax')); ?></dt><dd><?php echo e($money($order->taxTotal())); ?></dd></div><?php endif; ?>
                    <?php if((float) $order->cod_fee > 0): ?><div class="flex justify-between"><dt><?php echo e($order->codFeeLabel()); ?></dt><dd><?php echo e($money($order->cod_fee)); ?></dd></div><?php endif; ?>
                    <?php if((float) $order->payment_fee_charged > 0): ?><div class="flex justify-between"><dt><?php echo e(__('store.payment_fee')); ?></dt><dd><?php echo e($money($order->payment_fee_charged)); ?></dd></div><?php endif; ?>
                    <div class="flex justify-between border-t border-slate-100 pt-2 text-base font-extrabold"><dt><?php echo e(__('store.total')); ?></dt><dd class="text-brand-600"><?php echo e($money($order->total)); ?></dd></div>
                </dl>
            </section>
        </div>

        <section class="rounded-2xl bg-white p-5 ring-1 ring-slate-100">
            <h2 class="mb-2 font-extrabold"><?php echo e(__('store.items')); ?></h2>
            <?php $grp = null; ?>
            <?php $__currentLoopData = $order->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $it): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php if($it->bundle_group && $it->bundle_group !== $grp): ?><div class="border-t border-slate-100 bg-slate-50 px-2 py-1.5 text-xs font-bold text-brand-600"><?php echo e(__('store.bundle')); ?>: <?php echo e($it->bundle_name); ?></div><?php endif; ?>
                <?php $grp = $it->bundle_group; ?>
                <div class="flex justify-between gap-2 border-t border-slate-100 py-2 text-sm <?php echo e($it->bundle_group ? 'ps-4' : ''); ?>"><span><?php echo e($it->name); ?> <span class="text-slate-400">&times; <?php echo e($it->quantity); ?></span><?php if($it->returned_qty): ?><span class="badge badge-warn"><?php echo e(__('store.returned')); ?> <?php echo e($it->returned_qty); ?></span><?php endif; ?></span><b><?php echo e($money($it->line_subtotal)); ?></b></div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </section>

        
        <?php if(false && $mine && $order->status === S::DELIVERED): ?>
            <section class="rounded-2xl bg-white p-5 ring-1 ring-slate-100">
                <h2 class="mb-3 font-extrabold"><?php echo e(__('store.request_return')); ?></h2>
                <form data-ajax-form action="<?php echo e(route('storefront.orders.return', $order->public_token)); ?>" method="POST" class="space-y-3 text-sm" novalidate>
                    <?php echo csrf_field(); ?>
                    <?php $__currentLoopData = $order->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $it): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="flex items-center justify-between gap-3"><span><?php if($it->bundle_name): ?><span class="text-xs font-bold text-brand-600">[<?php echo e($it->bundle_name); ?>]</span> <?php endif; ?><?php echo e($it->name); ?></span>
                            <input type="hidden" name="items[<?php echo e($i); ?>][order_item_id]" value="<?php echo e($it->id); ?>">
                            <input type="number" min="0" max="<?php echo e($it->quantity - $it->returned_qty); ?>" value="0" name="items[<?php echo e($i); ?>][quantity]" class="form-control w-24" aria-label="<?php echo e(__('store.return_qty')); ?>"></div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    <?php if (isset($component)) { $__componentOriginalc2fcfa88dc54fee60e0757a7e0572df1 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc2fcfa88dc54fee60e0757a7e0572df1 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.input','data' => ['name' => 'reason','label' => __('store.return_reason')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'reason','label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('store.return_reason'))]); ?>
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
                    <button class="btn btn-secondary"><?php echo e(__('store.request_return')); ?></button>
                </form>
            </section>
        <?php endif; ?>
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
<?php /**PATH /var/www/3alraf/backend/resources/views/storefront/order.blade.php ENDPATH**/ ?>