{{-- Mobile bottom navigation (below the lg breakpoint, same as the header drawer). --}}
@php
    $bbItems = [
        ['key' => 'home', 'label' => __('store.home'), 'href' => route('storefront.home'), 'active' => request()->routeIs('storefront.home'),
            'icon' => 'M3 11.5 12 4l9 7.5M5 10v10h5v-6h4v6h5V10'],
        ['key' => 'categories', 'label' => __('store.categories'), 'drawer' => true, 'active' => request()->routeIs('storefront.category.*', 'storefront.brand.*', 'storefront.tag.*', 'storefront.search'),
            'icon' => 'M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z'],
        ...(app(\App\Services\GiftFinder\GiftFinderSettings::class)->enabled() ? [['key' => 'gift', 'label' => __('gift_finder.nav_short'), 'href' => route('storefront.gift-finder.show'),
            'active' => request()->routeIs('storefront.gift-finder.*'), 'icon' => 'M20 12v9H4v-9M2 7h20v5H2zM12 21V7M12 7H7.5a2.5 2.5 0 1 1 0-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 1 0 0-5C13 2 12 7 12 7z']] : []),
        ['key' => 'cart', 'label' => __('store.cart'), 'href' => route('storefront.cart.show'), 'active' => request()->routeIs('storefront.cart.*', 'storefront.checkout.*'), 'badge' => true,
            'icon' => 'M3 4h2l2.4 11.2a1 1 0 0 0 1 .8h8.8a1 1 0 0 0 1-.8L20 8H6M9 20h.01M17 20h.01'],
        ['key' => 'account', 'label' => __('store.my_account'), 'href' => auth('web')->check() ? route('storefront.account.index') : route('storefront.login'),
            'active' => request()->routeIs('storefront.account.*', 'storefront.login', 'storefront.register'),
            'icon' => 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 21a8 8 0 0 1 16 0'],
    ];
@endphp
<nav class="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white pb-[env(safe-area-inset-bottom)] shadow-bar lg:hidden" aria-label="{{ __('store.menu') }}" data-bottom-bar>
    <ul class="mx-auto grid h-[72px] max-w-xl items-stretch {{ count($bbItems) > 4 ? 'grid-cols-5' : 'grid-cols-4' }}">
        @foreach ($bbItems as $it)
            @php
                $gift = $it['key'] === 'gift';
                $cls = 'flex h-full w-full flex-col items-center justify-center gap-1 px-0.5 text-[11px] font-bold leading-4 transition '.($it['active'] ? 'text-brand-600' : 'text-slate-600 hover:text-brand-600');
            @endphp
            <li class="min-w-0">
                @if ($it['drawer'] ?? false)
                    <button type="button" data-drawer-open class="{{ $cls }}">
                @else
                    <a href="{{ $it['href'] }}" @if ($gift) data-bottom-gift-finder @endif @if ($it['active']) aria-current="page" @endif class="{{ $cls }}">
                @endif
                    @if ($gift)
                        {{-- raised gift-finder circle: sun, pop while on the gift finder --}}
                        <span class="-mt-7 grid size-[52px] place-items-center rounded-full shadow-raised ring-4 ring-white {{ $it['active'] ? 'bg-pop-600 text-white' : 'bg-sun-400 text-slate-950' }}">
                            <svg class="size-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="{{ $it['icon'] }}"/></svg>
                        </span>
                    @else
                        <span class="relative grid h-8 w-12 place-items-center rounded-full {{ $it['active'] ? 'bg-brand-50' : '' }}">
                            <svg class="size-6" fill="none" stroke="currentColor" stroke-width="{{ $it['active'] ? 2.2 : 1.9 }}" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="{{ $it['icon'] }}"/></svg>
                            @if ($it['badge'] ?? false)<span class="absolute end-0.5 -top-1 grid h-[18px] min-w-[18px] place-items-center rounded-full bg-pop-600 px-1 text-[10px] font-bold leading-none text-white ring-2 ring-white" data-cart-count>0</span>@endif
                        </span>
                    @endif
                    <span class="max-w-full truncate">{{ $it['label'] }}</span>
                @if ($it['drawer'] ?? false)</button>@else</a>@endif
            </li>
        @endforeach
    </ul>
</nav>
