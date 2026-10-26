<?php if($paginator->hasPages()): ?>
<nav class="flex flex-wrap items-center justify-center gap-1 text-sm" aria-label="pagination">
    <?php $__currentLoopData = $elements; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $element): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php if(is_string($element)): ?><span class="px-2 text-slate-400"><?php echo e($element); ?></span><?php endif; ?>
        <?php if(is_array($element)): ?>
            <?php $__currentLoopData = $element; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $page => $url): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php if($page == $paginator->currentPage()): ?><span class="grid size-9 place-items-center rounded-lg bg-brand-600 font-bold text-white"><?php echo e($page); ?></span>
                <?php else: ?><a href="<?php echo e($url); ?>" data-page-link class="grid size-9 place-items-center rounded-lg border border-slate-200 bg-white hover:border-brand-600 hover:text-brand-600"><?php echo e($page); ?></a><?php endif; ?>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <?php endif; ?>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</nav>
<?php endif; ?>
<?php /**PATH /var/www/3alraf/backend/resources/views/storefront/_pagination.blade.php ENDPATH**/ ?>