<?php ($m = fn ($v) => \App\Support\Money::format((string) $v, $order->currency)); ?>
<dl class="mt-4 ms-auto max-w-sm space-y-1 text-sm">
    <div class="flex justify-between"><dt class="text-slate-500"><?php echo e(__('orders.fields.subtotal')); ?></dt><dd><?php echo e($m($order->subtotal)); ?></dd></div>
    <?php if($order->discount > 0): ?><div class="flex justify-between"><dt class="text-slate-500"><?php echo e(__('orders.fields.discount')); ?><?php if($order->discountCode): ?> (<?php echo e($order->discountCode->code); ?>)<?php endif; ?></dt><dd>- <?php echo e($m($order->discount)); ?></dd></div><?php endif; ?>
    <div class="flex justify-between"><dt class="text-slate-500"><?php echo e(__('orders.fields.shipping')); ?></dt><dd><?php echo e($m($order->shipping_net)); ?></dd></div>
    <?php if($order->packaging_fee > 0): ?><div class="flex justify-between"><dt class="text-slate-500"><?php echo e(__('orders.packaging.title')); ?>: <?php echo e($order->packagingLabel()); ?></dt><dd><?php echo e($m($order->packaging_fee)); ?></dd></div><?php endif; ?>
    <?php if($order->taxTotal() > 0): ?><div class="flex justify-between"><dt class="text-slate-500"><?php echo e(__('orders.fields.tax')); ?></dt><dd><?php echo e($m($order->taxTotal())); ?></dd></div><?php endif; ?>
    <?php if($order->cod_fee > 0): ?><div class="flex justify-between"><dt class="text-slate-500"><?php echo e($order->codFeeLabel()); ?></dt><dd><?php echo e($m($order->cod_fee)); ?></dd></div><?php endif; ?>
    <?php if($order->payment_fee_charged > 0): ?><div class="flex justify-between"><dt class="text-slate-500"><?php echo e(__('orders.fields.payment_fee')); ?></dt><dd><?php echo e($m($order->payment_fee_charged)); ?></dd></div><?php endif; ?>
    <div class="flex justify-between border-t border-slate-200 pt-1 font-semibold"><dt><?php echo e(__('ops.fields.total')); ?></dt><dd><?php echo e($m($order->total)); ?></dd></div>
    <div class="flex justify-between"><dt class="text-slate-500"><?php echo e(__('orders.fields.paid')); ?></dt><dd><?php echo e($m($order->paid_amount)); ?></dd></div>
    <div class="flex justify-between"><dt class="text-slate-500"><?php echo e(__('orders.fields.due')); ?></dt><dd><?php echo e($m($order->due())); ?></dd></div>
    <?php if($order->refunded_amount > 0): ?><div class="flex justify-between"><dt class="text-slate-500"><?php echo e(__('orders.fields.refunded')); ?></dt><dd><?php echo e($m($order->refunded_amount)); ?></dd></div><?php endif; ?>
</dl>
<?php /**PATH /var/www/3alraf/backend/resources/views/tenant/orders/_totals.blade.php ENDPATH**/ ?>