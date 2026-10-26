<?php ($tenant = current_tenant()); ?>
<!DOCTYPE html>
<html lang="<?php echo e(app()->getLocale()); ?>" dir="<?php echo e($tenant?->isRtl(app()->getLocale()) ? 'rtl' : 'ltr'); ?>">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo e(__('orders.invoice')); ?> <?php echo e($order->number); ?></title>
    <style>
        body { font-family: system-ui, sans-serif; color: #1e293b; margin: 0; background: #f1f5f9; }
        .sheet { max-width: 800px; margin: 24px auto; background: #fff; padding: 32px; border-radius: 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; font-size: 14px; }
        th, td { padding: 8px; border-bottom: 1px solid #e2e8f0; text-align: start; }
        .tot { margin-inline-start: auto; width: 280px; margin-top: 16px; font-size: 14px; }
        .tot div { display: flex; justify-content: space-between; padding: 3px 0; }
        .actions { max-width: 800px; margin: 16px auto 0; display: flex; gap: 8px; }
        button, .btn { padding: 8px 14px; border: 0; background: #dc2626; color: #fff; border-radius: 6px; cursor: pointer; font-size: 14px; text-decoration: none; }
        @media print { body { background: #fff; } .sheet { margin: 0; padding: 0; } .actions { display: none; } }
    </style>
</head>
<body>
    <div class="actions"><button onclick="window.print()"><?php echo e(__('orders.print')); ?></button> <a class="btn" href="<?php echo e(($public ?? false) ? route('storefront.orders.invoice.pdf', ['locale' => app()->getLocale(), 'token' => $order->public_token]) : route('tenant.orders.invoice.pdf', $order->id)); ?>">PDF</a></div>
    <div class="sheet">
        <div style="display:flex;justify-content:space-between;align-items:flex-start">
            <div><h1 style="margin:0"><?php echo e($tenant?->name); ?></h1><div style="color:#64748b;font-size:14px"><?php echo e(__('orders.invoice')); ?> #<?php echo e($order->number); ?><br><?php echo e($order->placed_at?->format('Y-m-d H:i')); ?></div></div>
            <div style="text-align:end;font-size:14px"><strong><?php echo e($order->customer_name); ?></strong><br><?php echo e($order->customer_phone); ?><br><?php echo e($order->city?->name); ?> - <?php echo e($order->address); ?></div>
        </div>
        <table>
            <thead><tr><th><?php echo e(__('ops.fields.item')); ?></th><th><?php echo e(__('orders.fields.unit_price')); ?></th><th><?php echo e(__('ops.fields.quantity')); ?></th><th><?php echo e(__('ops.fields.total')); ?></th></tr></thead>
            <tbody><?php ($grp = null); ?>
<?php $__currentLoopData = $order->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if($i->bundle_group && $i->bundle_group !== $grp): ?><tr><td colspan="4"><b><?php echo e($i->bundle_name); ?></b></td></tr><?php endif; ?>
<?php ($grp = $i->bundle_group); ?>
<tr><td style="<?php echo e($i->bundle_group ? 'padding-inline-start:16px' : ''); ?>"><?php echo e($i->name); ?></td><td><?php echo e(number_format($i->unit_price, 2)); ?></td><td><?php echo e($i->quantity); ?></td><td><?php echo e(number_format($i->line_subtotal, 2)); ?></td></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></tbody>
        </table>
        <div class="tot">
            <?php ($m = fn ($v) => \App\Support\Money::format((string) $v, $order->currency)); ?>
            <div><span><?php echo e(__('orders.fields.subtotal')); ?></span><span><?php echo e($m($order->subtotal)); ?></span></div>
            <?php if($order->discount > 0): ?><div><span><?php echo e(__('orders.fields.discount')); ?></span><span>- <?php echo e($m($order->discount)); ?></span></div><?php endif; ?>
            <div><span><?php echo e(__('orders.fields.shipping')); ?></span><span><?php echo e($m($order->shipping_net)); ?></span></div>
            <?php if($order->packaging_fee > 0): ?><div><span><?php echo e(__('orders.packaging.title')); ?>: <?php echo e($order->packagingLabel()); ?></span><span><?php echo e($m($order->packaging_fee)); ?></span></div><?php endif; ?>
            <?php if($order->taxTotal() > 0): ?><div><span><?php echo e(__('orders.fields.tax')); ?></span><span><?php echo e($m($order->taxTotal())); ?></span></div><?php endif; ?>
            <?php if($order->cod_fee > 0): ?><div><span><?php echo e($order->codFeeLabel()); ?></span><span><?php echo e($m($order->cod_fee)); ?></span></div><?php endif; ?>
            <?php if($order->payment_fee_charged > 0): ?><div><span><?php echo e(__('orders.fields.payment_fee')); ?></span><span><?php echo e($m($order->payment_fee_charged)); ?></span></div><?php endif; ?>
            <div style="font-weight:700;border-top:1px solid #cbd5e1"><span><?php echo e(__('ops.fields.total')); ?></span><span><?php echo e($m($order->total)); ?></span></div>
            <div><span><?php echo e(__('orders.fields.paid')); ?></span><span><?php echo e($m($order->paid_amount)); ?></span></div>
            <div><span><?php echo e(__('orders.fields.due')); ?></span><span><?php echo e($m($order->due())); ?></span></div>
        </div>
        <p style="margin-top:24px;font-size:13px;color:#64748b"><?php echo e($order->paymentMethod?->name); ?> - <?php echo e($order->payment_status->label()); ?> - <?php echo e($order->status->label()); ?></p>
        <?php if(($public ?? false) && $order->payingMethod()?->type === \App\Enums\PaymentMethodType::WALLET && ! in_array($order->payment_status, [\App\Enums\PaymentStatus::PAID, \App\Enums\PaymentStatus::REFUNDED]) && ! in_array($order->status, [\App\Enums\OrderStatus::CANCELLED])): ?>
            <div class="actions" style="margin:12px 0 0;display:block;font-size:14px">
                <p><?php echo e($order->paymentMethod->instructions); ?> <?php echo e($order->paymentMethod->account_number); ?></p>
                <form data-ajax-form action="<?php echo e(route('storefront.orders.proof', ['locale' => app()->getLocale(), 'token' => $order->public_token])); ?>" method="POST" enctype="multipart/form-data">
                    <?php echo csrf_field(); ?> <input type="file" name="proof" accept="image/*,.pdf" required> <button type="submit"><?php echo e(__('orders.upload_proof')); ?></button>
                </form>
            </div>
            <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
        <?php endif; ?>
    </div>
</body>
</html>
<?php /**PATH /var/www/3alraf/backend/resources/views/tenant/orders/invoice.blade.php ENDPATH**/ ?>