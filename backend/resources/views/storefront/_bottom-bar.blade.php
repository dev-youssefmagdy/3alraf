{{-- Mobile bottom navigation (below the lg breakpoint, same as the header drawer). --}}
@php
    $bbItems = [
        ['key' => 'home', 'label' => __('store.home'), 'href' => route('storefront.home'), 'active' => request()->routeIs('storefront.home'),
            'icon' => 'M3 11.5 12 4l9 7.5M5 10v10h5v-6h4v6h5V10'],
        ['key' => 'categories', 'label' => __('store.categories'), 'drawer' => true, 'active' => request()->routeIs('storefront.category.*', 'storefront.brand.*', 'storefront.tag.*', 'storefront.search'),
            'icon' => 'M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z'],
        ['key' => 'cart', 'label' => __('store.cart'), 'href' => route('storefront.cart.show'), 'active' => request()->routeIs('storefront.cart.*', 'storefront.checkout.*'), 'badge' => true,
            'icon' => 'M3 4h2l2.4 11.2a1 1 0 0 0 1 .8h8.8a1 1 0 0 0 1-.8L20 8H6M9 20h.01M17 20h.01'],
        ['key' => 'wishlist', 'label' => __('store.wishlist'), 'href' => auth('web')->check() ? route('storefront.wishlist.index') : route('storefront.login'), 'active' => request()->routeIs('storefront.wishlist.*'),
            'icon' => 'M12 20s-7-4.4-9-9a5 5 0 0 1 9-3 5 5 0 0 1 9 3c-2 4.6-9 9-9 9z'],
        ['key' => 'account', 'label' => auth('web')->check() ? __('store.my_account') : __('store.login'), 'href' => auth('web')->check() ? route('storefront.account.index') : route('storefront.login'),
            'active' => request()->routeIs('storefront.account.*', 'storefront.login', 'storefront.register'),
            'icon' => 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 21a8 8 0 0 1 16 0'],
    ];
@endphp
<nav class="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white/95 pb-[env(safe-area-inset-bottom)] shadow-[0_-4px_16px_rgba(15,23,42,0.06)] backdrop-blur lg:hidden" aria-label="{{ __('store.menu') }}" data-bottom-bar>
    <ul class="mx-auto grid max-w-xl grid-cols-5">
        @foreach ($bbItems as $it)
            <li>
                @if ($it['drawer'] ?? false)
                    <button type="button" data-drawer-open class="flex w-full flex-col items-center gap-0.5 px-1 py-2 text-[11px] font-semibold {{ $it['active'] ? 'text-brand-600' : 'text-slate-500' }}">
                @else
                    <a href="{{ $it['href'] }}" @if ($it['active']) aria-current="page" @endif class="flex flex-col items-center gap-0.5 px-1 py-2 text-[11px] font-semibold {{ $it['active'] ? 'text-brand-600' : 'text-slate-500 hover:text-brand-600' }}">
                @endif
                    <span class="relative">
                        <svg class="size-6" fill="{{ $it['active'] && $it['key'] === 'home' ? 'currentColor' : 'none' }}" fill-opacity="0.15" stroke="currentColor" stroke-width="{{ $it['active'] ? 2.2 : 1.8 }}" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="{{ $it['icon'] }}"/></svg>
                        @if ($it['badge'] ?? false)<span class="absolute -end-2 -top-1.5 grid min-w-4 place-items-center rounded-full bg-brand-600 px-1 text-[10px] font-bold leading-4 text-white" data-cart-count>0</span>@endif
                    </span>
                    <span class="max-w-full truncate">{{ $it['label'] }}</span>
                @if ($it['drawer'] ?? false)</button>@else</a>@endif
            </li>
        @endforeach
    </ul>
</nav>
