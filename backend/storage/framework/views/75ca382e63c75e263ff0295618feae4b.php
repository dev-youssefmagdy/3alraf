<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['title' => '', 'description' => null, 'image' => null, 'type' => 'website', 'jsonld' => null, 'noindex' => false]));

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

foreach (array_filter((['title' => '', 'description' => null, 'image' => null, 'type' => 'website', 'jsonld' => null, 'noindex' => false]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php
    $tenant = current_tenant();
    $locale = app()->getLocale();
    $desc = \Illuminate\Support\Str::limit(trim(strip_tags($description ?: __('store.seo_default_desc'))), 160, '');
    $canonical = url()->current();
    $routeLocale = request()->route('locale');
    $sf = app(\App\Services\Storefront\StorefrontData::class);
    $menu = $sf->menu();
    $footer = $sf->footer();
    $langs = \App\Models\Language::activeList()->filter(fn ($l) => in_array($l->code, $tenant->locales, true));
    $switchUrl = fn ($code) => $routeLocale
        ? url('/'.$code.(($rest = request()->path()) && ($pos = strpos($rest, '/')) !== false ? substr($rest, $pos) : '')).(request()->getQueryString() ? '?'.request()->getQueryString() : '')
        : url()->current().'?lang='.$code;
    $sfJs = ['symbol' => $tenant->currency_symbol, 'locale' => $locale, 'dir' => ($isRtl ?? false) ? 'rtl' : 'ltr', 'logged' => auth('web')->check(),
        'routes' => ['cart' => route('storefront.cart.show'), 'cartItems' => route('storefront.cart.add'), 'quote' => route('storefront.cart.quote'), 'suggest' => route('storefront.search.suggest'),
            'checkout' => route('storefront.checkout.create'), 'shipping' => route('storefront.checkout.shipping'), 'login' => route('storefront.login')],
        'i' => ['d' => __('store.d'), 'h' => __('store.h'), 'm' => __('store.m'), 's' => __('store.s'), 'free' => __('store.free'), 'cart_empty' => __('store.cart_empty'), 'remove' => __('store.remove'),
            'no_results' => __('store.no_results'), 'products' => __('store.suggest_products'), 'categories' => __('store.suggest_categories'), 'in_stock' => __('store.in_stock'),
            'out_of_stock' => __('store.out_of_stock'), 'low_stock' => __('store.low_stock', ['n' => ':n']), 'select_city_hint' => __('store.select_city_hint'), 'no_shipping' => __('store.no_shipping'),
            'eta' => __('store.eta_days', ['d' => ':d']), 'cod_na' => __('store.cod_not_for_shipping'), 'unavailable' => __('store.unavailable_combo'), 'item_unavailable' => __('store.item_unavailable'),
            'wishlist_login' => __('store.wishlist_login'), 'code_applied' => __('store.code_applied'), 'fee_note' => __('store.fee_note', ['fee' => ':fee']),
            'subtotal' => __('store.subtotal'), 'discount' => __('store.discount'), 'shipping' => __('store.shipping'), 'tax' => __('store.tax'), 'packaging' => __('store.packaging'), 'payment_fee' => __('store.payment_fee'), 'cod_fee' => __('store.cod_fee'), 'total' => __('store.total'), 'prepaid_now' => __('store.prepaid_now'), 'cod_balance' => __('store.cod_balance'),
            'unit' => __('store.unit_price'), 'qty' => __('store.qty')]];
?>
<?php if (isset($component)) { $__componentOriginald4c772c02301431d3253f64117700596 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald4c772c02301431d3253f64117700596 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.layouts.base','data' => ['title' => $title ?: $tenant->name,'bodyClass' => 'bg-slate-50','siteName' => $tenant->name]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('layouts.base'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($title ?: $tenant->name),'body-class' => 'bg-slate-50','site-name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($tenant->name)]); ?>
     <?php $__env->slot('head', null, []); ?> 
        <meta name="description" content="<?php echo e($desc); ?>">
        <link rel="canonical" href="<?php echo e($canonical); ?>">
        <?php if($noindex): ?><meta name="robots" content="noindex,nofollow"><?php endif; ?>
        <?php if($routeLocale): ?><?php $__currentLoopData = $langs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><link rel="alternate" hreflang="<?php echo e($l->code); ?>" href="<?php echo e($switchUrl($l->code)); ?>"><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?> <?php endif; ?>
        <meta property="og:site_name" content="<?php echo e($tenant->name); ?>"><meta property="og:type" content="<?php echo e($type); ?>">
        <meta property="og:title" content="<?php echo e($title ?: $tenant->name); ?>"><meta property="og:description" content="<?php echo e($desc); ?>"><meta property="og:url" content="<?php echo e($canonical); ?>">
        <meta property="og:locale" content="<?php echo e($locale); ?>">
        <?php if($image): ?><meta property="og:image" content="<?php echo e($image); ?>"><meta name="twitter:card" content="summary_large_image"><meta name="twitter:image" content="<?php echo e($image); ?>"><?php endif; ?>
        <?php if($jsonld): ?><script type="application/ld+json"><?php echo json_encode($jsonld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG); ?></script><?php endif; ?>
        <script>window.__sf = <?php echo e(Illuminate\Support\Js::from($sfJs)); ?>;</script>
     <?php $__env->endSlot(); ?>

    <div class="bg-brand-600 py-1.5 text-center text-xs font-medium text-white"><?php echo e(__('store.promo')); ?></div>

    <header class="sticky top-0 z-40 border-b border-slate-200 bg-white shadow-sm">
        <div class="mx-auto flex max-w-7xl items-center gap-3 px-4 py-3">
            <button type="button" class="rounded-lg border border-slate-200 p-2 lg:hidden" data-drawer-open aria-label="<?php echo e(__('store.menu')); ?>">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <a href="<?php echo e(route('storefront.home')); ?>" class="flex shrink-0 items-center gap-2 text-xl font-extrabold text-brand-600"><img src="<?php echo e(asset('logo.png')); ?>" alt="<?php echo e($tenant->name); ?>" class="h-14 w-auto max-w-[240px] object-contain"></a>

            <form action="<?php echo e(route('storefront.search')); ?>" method="GET" class="relative order-last w-full lg:order-none lg:max-w-2xl lg:flex-1" data-search-form role="search">
                <input type="search" name="q" value="<?php echo e(request('q')); ?>" autocomplete="off" placeholder="<?php echo e(__('store.search_placeholder')); ?>" class="form-control !rounded-full !py-2.5 !pe-12 ps-5" data-search-input>
                <button class="absolute inset-y-0 end-0 px-4 text-slate-500 hover:text-brand-600" aria-label="<?php echo e(__('store.search')); ?>">
                    <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                </button>
                <div class="absolute inset-x-0 top-full z-50 mt-1 hidden overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl" data-suggest></div>
            </form>

            <nav class="ms-auto flex items-center gap-1 text-sm lg:ms-0">
                <div class="relative">
                    <button type="button" data-dropdown-toggle="lang-menu" class="rounded-lg px-2 py-2 text-slate-600 hover:bg-slate-100"><?php echo e(strtoupper($locale)); ?></button>
                    <div id="lang-menu" data-dropdown class="absolute end-0 z-50 mt-1 hidden min-w-32 rounded-lg border border-slate-200 bg-white py-1 shadow-lg">
                        <?php $__currentLoopData = $langs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><a href="<?php echo e($switchUrl($l->code)); ?>" hreflang="<?php echo e($l->code); ?>" class="block px-4 py-2 hover:bg-slate-50 <?php echo e($l->code === $locale ? 'font-bold text-brand-600' : ''); ?>"><?php echo e($l->native_name); ?></a><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                </div>
                <div class="relative hidden sm:block">
                    <button type="button" data-dropdown-toggle="acct-menu" class="flex items-center gap-1 rounded-lg px-2 py-2 text-slate-600 hover:bg-slate-100">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
                        <span class="hidden md:inline"><?php echo e(auth('web')->check() ? \Illuminate\Support\Str::limit(auth('web')->user()->name, 14) : __('store.my_account')); ?></span>
                    </button>
                    <div id="acct-menu" data-dropdown class="absolute end-0 z-50 mt-1 hidden min-w-44 rounded-lg border border-slate-200 bg-white py-1 shadow-lg">
                        <?php if(auth()->guard('web')->check()): ?>
                            <a class="block px-4 py-2 hover:bg-slate-50" href="<?php echo e(route('storefront.account.index')); ?>"><?php echo e(__('store.my_orders')); ?></a>
                            <a class="block px-4 py-2 hover:bg-slate-50" href="<?php echo e(route('storefront.wishlist.index')); ?>"><?php echo e(__('store.wishlist')); ?></a>
                            <form method="POST" action="<?php echo e(route('storefront.logout')); ?>"><?php echo csrf_field(); ?><button class="block w-full px-4 py-2 text-start hover:bg-slate-50"><?php echo e(__('store.logout')); ?></button></form>
                        <?php else: ?>
                            <a class="block px-4 py-2 hover:bg-slate-50" href="<?php echo e(route('storefront.login')); ?>"><?php echo e(__('store.login')); ?></a>
                            <a class="block px-4 py-2 hover:bg-slate-50" href="<?php echo e(route('storefront.register')); ?>"><?php echo e(__('store.register')); ?></a>
                        <?php endif; ?>
                        <a class="block border-t border-slate-100 px-4 py-2 hover:bg-slate-50" href="<?php echo e(route('storefront.affiliate.'.(auth('affiliate')->check() ? 'dashboard' : 'login'))); ?>"><?php echo e(__('store.affiliate_program')); ?></a>
                    </div>
                </div>
                <div class="relative">
                    <button type="button" data-dropdown-toggle="mini-cart" class="relative flex items-center gap-1 rounded-lg px-2 py-2 text-slate-600 hover:bg-slate-100" aria-label="<?php echo e(__('store.cart')); ?>">
                        <svg class="size-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 4h2l2.4 11.2a1 1 0 0 0 1 .8h8.8a1 1 0 0 0 1-.8L20 8H6"/><circle cx="9" cy="20" r="1"/><circle cx="17" cy="20" r="1"/></svg>
                        <span class="absolute -end-0.5 -top-0.5 grid min-w-5 place-items-center rounded-full bg-brand-600 px-1 text-[11px] font-bold text-white" data-cart-count>0</span>
                    </button>
                    <div id="mini-cart" data-dropdown class="absolute end-0 z-50 mt-1 hidden w-80 max-w-[90vw] rounded-xl border border-slate-200 bg-white p-3 shadow-xl" onclick="event.stopPropagation()">
                        <div data-mini-cart class="max-h-80 divide-y divide-slate-100 overflow-y-auto text-sm"></div>
                        <div class="mt-3 hidden border-t border-slate-100 pt-3" data-mini-cart-foot>
                            <div class="mb-2 flex justify-between font-bold"><span><?php echo e(__('store.subtotal')); ?></span><span data-mini-subtotal></span></div>
                            <a href="<?php echo e(route('storefront.cart.show')); ?>" class="btn btn-secondary w-full"><?php echo e(__('store.cart')); ?></a>
                            <a href="<?php echo e(route('storefront.checkout.create')); ?>" class="btn btn-primary mt-2 w-full"><?php echo e(__('store.checkout')); ?></a>
                        </div>
                    </div>
                </div>
            </nav>
        </div>

        
        <nav class="hidden border-t border-slate-100 bg-white lg:block" aria-label="<?php echo e(__('store.categories')); ?>">
            <ul class="mx-auto flex max-w-7xl items-center gap-1 px-4 text-sm font-semibold">
                <?php $__currentLoopData = $menu; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $root): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <li class="group static">
                        <a href="<?php echo e(route('storefront.category.show', $root['slug'])); ?>" class="block px-3 py-2.5 text-slate-700 hover:text-brand-600 group-hover:text-brand-600"><?php echo e($root['name']); ?></a>
                        <?php if($root['children']): ?>
                            <div class="absolute inset-x-0 top-full hidden border-t border-slate-100 bg-white shadow-xl group-hover:block">
                                <div class="mx-auto grid max-w-7xl grid-cols-2 gap-6 px-4 py-6 md:grid-cols-4 lg:grid-cols-5">
                                    <?php $__currentLoopData = $root['children']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c2): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <div>
                                            <a href="<?php echo e(route('storefront.category.show', $c2['slug'])); ?>" class="mb-2 block font-bold text-slate-800 hover:text-brand-600"><?php echo e($c2['name']); ?></a>
                                            <ul class="space-y-1 text-xs font-normal">
                                                <?php $__currentLoopData = $c2['children']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c3): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                    <li><a href="<?php echo e(route('storefront.category.show', $c3['slug'])); ?>" class="text-slate-500 hover:text-brand-600"><?php echo e($c3['name']); ?></a></li>
                                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                            </ul>
                                        </div>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </ul>
        </nav>
    </header>

    
    <div class="fixed inset-0 z-50 hidden" data-drawer>
        <div class="absolute inset-0 bg-black/40" data-drawer-close></div>
        <aside class="absolute inset-y-0 start-0 w-80 max-w-[85vw] overflow-y-auto bg-white p-4 shadow-xl">
            <div class="mb-3 flex items-center justify-between"><strong><?php echo e(__('store.categories')); ?></strong><button type="button" data-drawer-close class="p-1 text-2xl leading-none" aria-label="<?php echo e(__('store.close')); ?>">&times;</button></div>
            <?php
                $renderTree = function (array $nodes) use (&$renderTree) {
                    echo '<ul class="space-y-1 ps-3 text-sm">';
                    foreach ($nodes as $n) {
                        echo '<li><a class="block py-1.5 text-slate-700 hover:text-brand-600" href="'.e(route('storefront.category.show', $n['slug'])).'">'.e($n['name']).'</a>';
                        if ($n['children']) { $renderTree($n['children']); }
                        echo '</li>';
                    }
                    echo '</ul>';
                };
            ?>
            <?php ($renderTree($menu)); ?>
            <div class="mt-4 space-y-2 border-t border-slate-100 pt-3 text-sm">
                <?php if(auth()->guard('web')->check()): ?><a class="block" href="<?php echo e(route('storefront.account.index')); ?>"><?php echo e(__('store.my_account')); ?></a><?php else: ?><a class="block" href="<?php echo e(route('storefront.login')); ?>"><?php echo e(__('store.login')); ?></a><a class="block" href="<?php echo e(route('storefront.register')); ?>"><?php echo e(__('store.register')); ?></a><?php endif; ?>
            </div>
        </aside>
    </div>

    <main class="mx-auto min-h-[50vh] max-w-7xl px-4 py-6 max-lg:pb-24"><?php echo e($slot); ?></main>

    <footer class="mt-10 max-lg:mb-16 border-t border-slate-200 bg-brand-50 text-slate-600">
        <div class="mx-auto grid max-w-7xl gap-8 px-4 py-10 sm:grid-cols-2 lg:grid-cols-4">
            <div><h3 class="mb-3 text-lg font-bold text-slate-900"><?php echo e($tenant->name); ?></h3><p class="text-sm leading-6 text-slate-500"><?php echo e(__('store.seo_default_desc')); ?></p></div>
            <div><h4 class="mb-3 font-semibold text-slate-900"><?php echo e(__('store.about')); ?></h4>
                <ul class="space-y-2 text-sm"><?php $__currentLoopData = $footer['pages']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $fp): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><a class="hover:text-slate-900" href="<?php echo e(route('storefront.pages.show', $fp['slug'])); ?>"><?php echo e($fp['title']); ?></a></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></ul></div>
            <div><h4 class="mb-3 font-semibold text-slate-900"><?php echo e(__('store.customer_service')); ?></h4>
                <ul class="space-y-2 text-sm"><li><a class="hover:text-slate-900" href="<?php echo e(route('storefront.account.index')); ?>"><?php echo e(__('store.my_orders')); ?></a></li><li><a class="hover:text-slate-900" href="<?php echo e(route('storefront.cart.show')); ?>"><?php echo e(__('store.cart')); ?></a></li><li><a class="hover:text-slate-900" href="<?php echo e(route('storefront.affiliate.login')); ?>"><?php echo e(__('store.affiliate_program')); ?></a></li></ul></div>
            <div><h4 class="mb-3 font-semibold text-slate-900"><?php echo e(__('store.download_app')); ?></h4>
                <div class="flex flex-wrap gap-2">
                    <?php $__currentLoopData = ['ios' => ['App Store', 'M8 2h8a1 1 0 011 1v18a1 1 0 01-1 1H8a1 1 0 01-1-1V3a1 1 0 011-1zm4 17h.01'], 'android' => ['Google Play', 'M5 3l14 9-14 9z']]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => [$label, $path]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php if($footer['apps'][$k] ?? null): ?><a href="<?php echo e($footer['apps'][$k]); ?>" rel="noopener" target="_blank" class="flex items-center gap-2 rounded-lg border border-slate-300 px-3 py-2 text-xs hover:border-brand-500 hover:text-slate-900" aria-label="<?php echo e($label); ?>">
                        <?php else: ?><span class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-xs text-slate-500" title="<?php echo e(__('store.coming_soon')); ?>"><?php endif; ?>
                            <svg class="size-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="<?php echo e($path); ?>"/></svg><?php echo e($label); ?>

                        <?php if($footer['apps'][$k] ?? null): ?></a><?php else: ?></span><?php endif; ?>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
                <h4 class="mb-2 mt-5 font-semibold text-slate-900"><?php echo e(__('store.we_accept')); ?></h4>
                <div class="flex flex-wrap gap-2"><?php $__currentLoopData = $footer['payments']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pm): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if (isset($component)) { $__componentOriginalf0bac1c0095b1e5966ba158ba697efbe = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf0bac1c0095b1e5966ba158ba697efbe = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.payment-icon','data' => ['method' => $pm]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('payment-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['method' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($pm)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf0bac1c0095b1e5966ba158ba697efbe)): ?>
<?php $attributes = $__attributesOriginalf0bac1c0095b1e5966ba158ba697efbe; ?>
<?php unset($__attributesOriginalf0bac1c0095b1e5966ba158ba697efbe); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf0bac1c0095b1e5966ba158ba697efbe)): ?>
<?php $component = $__componentOriginalf0bac1c0095b1e5966ba158ba697efbe; ?>
<?php unset($__componentOriginalf0bac1c0095b1e5966ba158ba697efbe); ?>
<?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div></div>
        </div>
        <div class="border-t border-slate-200 py-4 text-center text-xs text-slate-500">&copy; <?php echo e(now()->year); ?> <?php echo e($tenant->name); ?>. <?php echo e(__('store.rights')); ?></div>
    </footer>

    <?php echo $__env->make('storefront._bottom-bar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
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
<?php /**PATH /var/www/3alraf/backend/resources/views/components/layouts/storefront.blade.php ENDPATH**/ ?>