<?php if (isset($component)) { $__componentOriginald4c772c02301431d3253f64117700596 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald4c772c02301431d3253f64117700596 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.layouts.base','data' => ['title' => __('admin.site.title'),'isRtl' => $lang === 'ar','bodyClass' => 'bg-slate-50']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('layouts.base'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('admin.site.title')),'is-rtl' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($lang === 'ar'),'body-class' => 'bg-slate-50']); ?>
    <main class="mx-auto flex min-h-screen max-w-5xl flex-col px-4 py-10">
        <header class="mb-10 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <img src="<?php echo e(asset('logo.png')); ?>" alt="" class="size-10 object-contain">
                <span class="text-lg font-bold"><?php echo e(config('app.name')); ?></span>
            </div>
            <a class="text-sm font-semibold text-brand-600" href="<?php echo e(route('central.landing', ['lang' => $lang === 'ar' ? 'en' : 'ar'])); ?>"><?php echo e(__('admin.site.switch_lang')); ?></a>
        </header>

        <section class="mb-8 text-center">
            <h1 class="text-3xl font-extrabold"><?php echo e(__('admin.site.headline')); ?></h1>
            <p class="mt-2 text-slate-500"><?php echo e(__('admin.site.sub')); ?></p>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <?php $__empty_1 = true; $__currentLoopData = $tenants; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <a href="<?php echo e(route('central.choose', $t['slug'])); ?>" data-tenant-card="<?php echo e($t['slug']); ?>"
                   class="group rounded-2xl border bg-white p-6 shadow-sm transition hover:border-brand-600 hover:shadow-md <?php echo e($preferred === $t['slug'] ? 'border-brand-600 ring-2 ring-brand-600/20' : 'border-slate-200'); ?>">
                    <div class="text-5xl"><?php echo e($t['flag']); ?></div>
                    <h2 class="mt-3 text-lg font-bold"><?php echo e($t['name']); ?></h2>
                    <?php if($preferred === $t['slug']): ?><span class="text-xs font-semibold text-brand-600"><?php echo e(__('admin.site.last_choice')); ?></span><?php endif; ?>
                    <dl class="mt-3 space-y-1 text-sm text-slate-500">
                        <div class="flex justify-between"><dt><?php echo e(__('admin.site.currency')); ?></dt><dd class="font-semibold text-slate-700"><?php echo e($t['currency']); ?></dd></div>
                        <div class="flex justify-between"><dt><?php echo e(__('admin.site.languages')); ?></dt><dd class="font-semibold text-slate-700"><?php echo e(implode('، ', $t['languages'])); ?></dd></div>
                    </dl>
                    <span class="mt-4 inline-block text-sm font-semibold text-brand-600"><?php echo e(__('admin.site.go')); ?> &rarr;</span>
                </a>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <p class="col-span-full text-center text-slate-500"><?php echo e(__('admin.site.empty')); ?></p>
            <?php endif; ?>
        </section>

        <footer class="mt-auto pt-12 text-center text-sm text-slate-400">
            <a href="<?php echo e(route('central.login')); ?>"><?php echo e(__('admin.site.admin')); ?></a>
        </footer>
    </main>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald4c772c02301431d3253f64117700596)): ?>
<?php $attributes = $__attributesOriginald4c772c02301431d3253f64117700596; ?>
<?php unset($__attributesOriginald4c772c02301431d3253f64117700596); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald4c772c02301431d3253f64117700596)): ?>
<?php $component = $__componentOriginald4c772c02301431d3253f64117700596; ?>
<?php unset($__componentOriginald4c772c02301431d3253f64117700596); ?>
<?php endif; ?>
<?php /**PATH /var/www/3alraf/backend/resources/views/central/landing.blade.php ENDPATH**/ ?>