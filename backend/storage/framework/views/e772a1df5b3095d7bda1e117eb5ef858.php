
<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['title' => '', 'guard' => 'central']));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter((['title' => '', 'guard' => 'central']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php
    $isCentral = $guard === 'central';
    $user = auth($guard)->user();
    $tenant = current_tenant();
    $menu = $isCentral
        ? [['central.dashboard', 'dashboard', 'M3 12l9-9 9 9M5 10v10h14V10'], ['central.tenants.index', 'tenants', 'M3 21h18M5 21V7l7-4 7 4v14', 'tenants.view'], ['central.languages.index', 'languages', 'M3 5h12M9 3v2m1 4c-1 4-4 7-7 8m3-5c1 2 4 5 8 6M12 21l4-9 4 9m-7-3h6', 'languages.view'],
            ['central.reports.index', 'reports.reports', 'M4 19V9m5 10V5m5 14v-7m5 7V8', 'reports.view'],
            ['central.admins.index', 'access.admins', 'M17 20v-2a4 4 0 00-4-4H7a4 4 0 00-4 4v2M10 10a4 4 0 100-8 4 4 0 000 8z', 'admins.view'],
            ['central.roles.index', 'access.roles', 'M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7z', 'roles.view'],
            ['central.products.index', 'products', 'M20 7l-8-4-8 4m16 0v10l-8 4m8-14l-8 4m0 10L4 17V7m8 14V11', 'products.view'],
            ['central.categories.index', 'categories', 'M4 6h16M4 12h10M4 18h6', 'categories.view'],
            ['central.brands.index', 'brands', 'M7 7h.01M3 11V5a2 2 0 012-2h6l9 9-8 8-9-9z', 'brands.view'],
            ['central.audit.index', 'access.audit_log', 'M9 12h6M9 16h6M7 3h8l4 4v14H7z', 'audit.view'],
            ['central.tags.index', 'tags', 'M7 7h.01M3 11V5a2 2 0 012-2h6l9 9-8 8-9-9z', 'tags.view'],
            ['central.attributes.index', 'attributes', 'M4 6h16M4 12h16M4 18h7', 'attributes.view']]
        : [['tenant.dashboard', 'dashboard', 'M3 12l9-9 9 9M5 10v10h14V10'], ['tenant.reports.index', 'reports.reports', 'M4 19V9m5 10V5m5 14v-7m5 7V8', 'reports.view'],
            ['tenant.admins.index', 'access.admins', 'M17 20v-2a4 4 0 00-4-4H7a4 4 0 00-4 4v2M10 10a4 4 0 100-8 4 4 0 000 8z', 'admins.view'],
            ['tenant.audit.index', 'access.audit_log', 'M9 12h6M9 16h6M7 3h8l4 4v14H7z', 'audit.view'],
            ['tenant.roles.index', 'access.roles', 'M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7z', 'roles.view'],
            ['tenant.catalog.index', 'catalog', 'M20 7l-8-4-8 4m16 0v10l-8 4m8-14l-8 4m0 10L4 17V7m8 14V11', 'catalog.view'],
            ['tenant.orders.index', 'orders.orders', 'M3 4h2l2 12h10l2-9H6M9 20h.01M17 20h.01', 'orders.view'],
            ['tenant.returns.index', 'orders.returns', 'M9 14l-4-4 4-4M5 10h9a5 5 0 010 10h-3', 'returns.view'],
            ['tenant.customers.index', 'orders.customers', 'M16 11a4 4 0 10-8 0 4 4 0 008 0zM4 21a8 8 0 0116 0', 'customers.view'],
            ['tenant.discount-codes.index', 'orders.discount_codes', 'M7 7h.01M3 11V5a2 2 0 012-2h6l9 9-8 8-9-9z', 'discount_codes.view'],
            ['tenant.flash-sales.index', 'orders.flash_sales', 'M13 2L4 14h7l-1 8 9-12h-7z', 'flash_sales.view'],
            ['tenant.bundles.index', 'orders.bundles', 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4', 'bundles.view'],
            ['tenant.packaging.index', 'orders.packaging.title', 'M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4', 'packaging.view'],
            ['tenant.affiliates.index', 'growth.affiliates', 'M17 20v-2a4 4 0 00-4-4H7a4 4 0 00-4 4v2M10 10a4 4 0 100-8 4 4 0 000 8zM21 20v-2a4 4 0 00-3-3.9', 'affiliates.view'],
            ['tenant.affiliate-tiers.index', 'growth.tiers', 'M4 20h4v-6H4zM10 20h4V8h-4zM16 20h4V4h-4z', 'affiliate_tiers.view'],
            ['tenant.affiliate-payouts.index', 'growth.payouts', 'M12 8c-2 0-3 1-3 2s1 2 3 2 3 1 3 2-1 2-3 2m0-10V6m0 12v-2', 'affiliate_payouts.view'],
            ['tenant.product-lists.index', 'growth.product_lists', 'M5 3l7 4 7-4v14l-7 4-7-4z', 'product_lists.view'],
            ['tenant.reviews.index', 'growth.reviews', 'M12 3l2.9 6 6.6.9-4.8 4.6 1.2 6.5L12 17.8 6.1 21l1.2-6.5L2.5 9.9 9.1 9z', 'reviews.view'],
            ['tenant.custom-pages.index', 'growth.custom_pages', 'M7 3h8l4 4v14H7zM14 3v5h5', 'custom_pages.view'],
            ['tenant.static-pages.index', 'growth.static_pages', 'M6 3h12v18H6zM9 8h6M9 12h6M9 16h4', 'static_pages.view'],
            ['tenant.cities.index', 'ops.cities', 'M17.7 8.3a6 6 0 10-11.4 0c0 4 5.7 11.7 5.7 11.7s5.7-7.7 5.7-11.7z', 'cities.view'],
            ['tenant.branches.index', 'ops.branches', 'M3 9l1-5h16l1 5M5 9v11h14V9M9 20v-6h6v6', 'branches.view'],
            ['tenant.suppliers.index', 'ops.suppliers', 'M17 20v-2a4 4 0 00-4-4H7a4 4 0 00-4 4v2M10 10a4 4 0 100-8 4 4 0 000 8z', 'suppliers.view'],
            ['tenant.shipping-companies.index', 'ops.shipping_companies', 'M3 7h11v9H3zM14 10h4l3 3v3h-7M7 19a1.5 1.5 0 100-3 1.5 1.5 0 000 3zm10 0a1.5 1.5 0 100-3 1.5 1.5 0 000 3z', 'shipping.view'],
            ['tenant.payment-methods.index', 'ops.payment_methods', 'M3 7h18v10H3zM3 11h18', 'payment_methods.view'],
            ['tenant.notification-settings.edit', 'notifications.settings.title', 'M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0a3 3 0 11-6 0', 'settings.view'],
            ['tenant.cod-fees.edit', 'ops.cod_fees', 'M12 8c-2 0-3 1-3 2s1 2 3 2 3 1 3 2-1 2-3 2m0-10V6m0 12v-2', 'settings.view'],
            ['tenant.banners.index', 'ops.banners', 'M4 5h16v14H4zM4 15l4-4 4 4 3-3 5 5', 'banners.view'],
            ['tenant.expense-categories.index', 'ops.expense_categories', 'M4 6h16M4 12h16M4 18h16', 'expense_categories.view'],
            ['tenant.expenses.index', 'ops.expenses', 'M12 8c-2 0-3 1-3 2s1 2 3 2 3 1 3 2-1 2-3 2m0-10V6m0 12v-2', 'expenses.view'],
            ['tenant.stock.index', 'ops.stock', 'M20 7l-8-4-8 4m16 0v10l-8 4m8-14l-8 4m0 10L4 17V7m8 14V11', 'stock.view', 'tenant.stock.index'],
            ['tenant.stock.movements', 'ops.stock_movements', 'M4 6h16M4 12h16M4 18h16', 'stock.view', 'tenant.stock.movements*'],
            ['tenant.purchases.index', 'ops.purchases', 'M3 4h2l2 12h10l2-9H6', 'purchases.view'],
            ['tenant.stock-transfers.index', 'ops.stock_transfers', 'M7 7h12l-3-3m3 13H7l3 3', 'stock_transfers.view'],
            ['tenant.stock-adjustments.index', 'ops.stock_adjustments', 'M4 20h4L19 9l-4-4L4 16z', 'stock_adjustments.view']];
    $aiMenu = $isCentral
        ? [['central.ai.providers.index', 'ai.menu.providers', 'M5 12h14M12 5v14M4 4h16v16H4z', 'ai_providers.view', 'central.ai.providers.*'], ['central.ai.models.index', 'ai.menu.models', 'M12 3l8 4.5v9L12 21l-8-4.5v-9z', 'ai_models.view', 'central.ai.models.*'],
            ['central.ai.tenants.index', 'ai.menu.tenants', 'M3 21h18M5 21V7l7-4 7 4v14', 'ai_tenants.view', 'central.ai.tenants.*'], ['central.ai.usage.index', 'ai.menu.usage', 'M4 19V9m5 10V5m5 14v-7m5 7V8', 'ai_usage.view', 'central.ai.usage.*']]
        : (app(\App\Services\Ai\AiAccess::class)->enabled($tenant)
            ? [['tenant.ai.settings.edit', 'ai.menu.settings', 'M12 3l8 4.5v9L12 21l-8-4.5v-9zM12 8v8M8 12h8', 'ai_settings.view', 'tenant.ai.*']] : []);
?>
<?php if (isset($component)) { $__componentOriginald4c772c02301431d3253f64117700596 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald4c772c02301431d3253f64117700596 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.layouts.base','data' => ['title' => $title]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('layouts.base'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($title)]); ?>
    <div class="flex min-h-screen">
        <aside id="sidebar" class="fixed inset-y-0 start-0 z-40 hidden w-64 shrink-0 flex-col bg-slate-900 p-4 md:static md:flex">
            <a href="<?php echo e(route($isCentral ? 'central.dashboard' : 'tenant.dashboard')); ?>" class="mb-6 flex items-center gap-2 px-2 text-lg font-bold text-white">
                <img src="<?php echo e(asset('logo.png')); ?>" alt="" class="size-8 rounded-lg object-contain">
                <span><?php echo e($isCentral ? config('app.name') : ($tenant?->name ?? config('app.name'))); ?></span>
            </a>
            <nav class="space-y-1">
                <?php $__currentLoopData = $menu; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php if(isset($item[3]) && ! $user?->can($item[3])) continue; ?>
                    <a href="<?php echo e(route($item[0])); ?>" class="side-link <?php echo e(request()->routeIs($item[4] ?? (Str::endsWith($item[0], '.dashboard') ? $item[0] : Str::beforeLast($item[0], '.').'.*')) ? 'active' : ''); ?>">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="<?php echo e($item[2]); ?>"/></svg>
                        <?php echo e(str_contains($item[1], '.') ? __($item[1]) : __('admin.'.$item[1])); ?>

                    </a>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </nav>
            <?php ($aiVisible = collect($aiMenu)->filter(fn ($i) => $user?->can($i[3]))); ?>
            <?php if($aiVisible->isNotEmpty()): ?>
                <p class="mb-1 mt-5 px-3 text-xs font-semibold uppercase tracking-wide text-slate-500"><?php echo e(__('ai.menu.group')); ?></p>
                <nav class="space-y-1">
                    <?php $__currentLoopData = $aiVisible; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <a href="<?php echo e(route($item[0])); ?>" class="side-link <?php echo e(request()->routeIs($item[4]) ? 'active' : ''); ?>">
                            <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="<?php echo e($item[2]); ?>"/></svg>
                            <?php echo e(__($item[1])); ?>

                        </a>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </nav>
            <?php endif; ?>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-30 flex items-center gap-3 border-b border-slate-200 bg-white px-4 py-3">
                <button type="button" data-sidebar-toggle class="md:hidden" aria-label="Menu">
                    <svg class="size-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>

                <?php if($isCentral && auth('central')->user()?->can('reports.view')): ?>
                    <form method="POST" action="<?php echo e(route('central.switch-tenant')); ?>" class="flex items-center gap-2">
                        <?php echo csrf_field(); ?>
                        <label class="hidden text-xs text-slate-500 sm:block"><?php echo e(__('admin.tenant_switcher')); ?></label>
                        <select name="tenant_id" onchange="this.form.submit()" class="form-control !w-auto !py-1.5">
                            <option value=""><?php echo e(__('admin.all_tenants')); ?></option>
                            <?php $__currentLoopData = \App\Models\Tenant::orderBy('name')->get(['id', 'name']); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($t->id); ?>" <?php if(selected_tenant_id() === $t->id): echo 'selected'; endif; ?>><?php echo e($t->name); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </form>
                <?php endif; ?>

                <div class="ms-auto flex items-center gap-3">
                    <?php $__currentLoopData = \App\Models\Language::activeList(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php if($l->code === app()->getLocale()) continue; ?>
                        <a href="<?php echo e(route($guard.'.locale', $l->code)); ?>" class="text-xs font-semibold uppercase text-slate-500 hover:text-brand-600"><?php echo e($l->native_name); ?></a>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

                    
                    <div class="relative">
                        <button type="button" data-dropdown-toggle="notif-menu" class="relative rounded-full p-2 text-slate-500 hover:bg-slate-100" aria-label="<?php echo e(__('admin.notifications')); ?>">
                            <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0a3 3 0 11-6 0"/></svg>
                            <span id="notif-count" class="absolute -end-0.5 -top-0.5 hidden min-w-4 rounded-full bg-brand-600 px-1 text-center text-[10px] font-bold leading-4 text-white"></span>
                        </button>
                        <div id="notif-menu" data-dropdown class="absolute end-0 mt-2 hidden w-80 rounded-xl border border-slate-200 bg-white shadow-lg"
                            data-url="<?php echo e(route($guard.'.notifications.index')); ?>" data-read-url="<?php echo e(route($guard.'.notifications.read', '__ID__')); ?>" data-read-all-url="<?php echo e(route($guard.'.notifications.read-all')); ?>"
                            <?php if(config('broadcasting.default') === 'reverb' && config('broadcasting.connections.reverb.key')): ?>
                                data-channel="App.Models.<?php echo e($isCentral ? 'CentralAdmin' : 'TenantAdmin'); ?>.<?php echo e($user->id); ?>" data-auth-url="<?php echo e(route($guard.'.broadcasting.auth')); ?>"
                                data-ws-key="<?php echo e(config('broadcasting.connections.reverb.key')); ?>" data-ws-host="<?php echo e(config('broadcasting.connections.reverb.options.host')); ?>"
                                data-ws-port="<?php echo e(config('broadcasting.connections.reverb.options.port')); ?>" data-ws-scheme="<?php echo e(config('broadcasting.connections.reverb.options.scheme')); ?>"
                            <?php endif; ?>
                            data-empty="<?php echo e(__('admin.no_notifications')); ?>">
                            <div class="flex items-center justify-between border-b border-slate-100 px-3 py-2 text-sm font-semibold"><?php echo e(__('admin.notifications')); ?>

                                <a id="notif-read-all" href="#" class="text-xs font-normal text-brand-600"><?php echo e(__('orders.mark_all_read')); ?></a>
                            </div>
                            <div id="notif-list" class="max-h-80 overflow-y-auto"><p class="p-3 text-sm text-slate-500"><?php echo e(__('admin.no_notifications')); ?></p></div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 text-sm">
                        <span class="hidden text-slate-600 sm:inline"><?php echo e($user?->name); ?></span>
                        <form method="POST" action="<?php echo e(route($guard.'.logout')); ?>"><?php echo csrf_field(); ?>
                            <button class="btn-sm btn-secondary"><?php echo e(__('admin.logout')); ?></button>
                        </form>
                    </div>
                </div>
            </header>

            <main class="flex-1 p-4 md:p-6">
                <?php if($title): ?><h1 class="mb-5 text-xl font-bold text-slate-800"><?php echo e($title); ?></h1><?php endif; ?>
                <?php echo e($slot); ?>

            </main>
        </div>
    </div>
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
<?php /**PATH /var/www/3alraf/backend/resources/views/components/layouts/admin.blade.php ENDPATH**/ ?>