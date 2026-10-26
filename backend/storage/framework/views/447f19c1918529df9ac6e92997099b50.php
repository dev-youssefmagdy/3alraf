
<form method="GET" class="mb-4 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-3">
    <?php if (! ($snapshot ?? false)): ?>
        <div><label class="form-label"><?php echo e(__('reports.from')); ?></label><input type="date" name="from" value="<?php echo e($f->from); ?>" class="form-control"></div>
        <div><label class="form-label"><?php echo e(__('reports.to')); ?></label><input type="date" name="to" value="<?php echo e($f->to); ?>" class="form-control"></div>
        <div class="flex gap-1 pb-0.5">
            <?php $__currentLoopData = [7, 30, 90]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $d): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <a href="?from=<?php echo e(now()->subDays($d - 1)->toDateString()); ?>&to=<?php echo e(now()->toDateString()); ?><?php echo e($f->branchId ? '&branch_id='.$f->branchId : ''); ?>" class="btn-sm btn-secondary"><?php echo e(__('reports.last_days', ['n' => $d])); ?></a>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    <?php endif; ?>
    <?php if(! empty($branches)): ?>
        <div><label class="form-label"><?php echo e(__('reports.branch')); ?></label>
            <select name="branch_id" class="form-control"><option value=""><?php echo e(__('ops.all_branches')); ?></option>
                <?php $__currentLoopData = $branches; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $id => $name): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($id); ?>" <?php if($f->branchId === $id): echo 'selected'; endif; ?>><?php echo e($name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select></div>
    <?php endif; ?>
    <button class="btn btn-primary"><?php echo e(__('reports.apply')); ?></button>
    <?php echo e($slot ?? ''); ?>

</form>
<?php /**PATH /var/www/3alraf/backend/resources/views/reports/_filter.blade.php ENDPATH**/ ?>