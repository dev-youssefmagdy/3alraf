
<?php if(! empty($chart) && ! empty($chart['labels'])): ?>
    <div class="relative h-72"><canvas data-chart='<?php echo json_encode($chart, 15, 512) ?>'></canvas></div>
<?php else: ?>
    <p class="py-10 text-center text-sm text-slate-400"><?php echo e(__('admin.no_data')); ?></p>
<?php endif; ?>
<?php /**PATH /var/www/3alraf/backend/resources/views/reports/_chart.blade.php ENDPATH**/ ?>