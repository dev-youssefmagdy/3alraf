{{-- Affiliate panel shell (/{locale}/affiliate, guard `affiliate`): <x-panel.shell panel="affiliate"> (mint), storefront CSS bundle (lite).
     Figma "Affiliate/App shell": sidebar on desktop, bottom tabs on phones (<768). Sections of the dashboard are reached by anchors. --}}
@props(['title' => '', 'header' => null])
@php
    $user = auth('affiliate')->user();
    $g = fn ($k) => __('growth.'.$k);
    $dash = route('storefront.affiliate.dashboard');
    $onDash = request()->routeIs('storefront.affiliate.dashboard');
    $I = [
        'home' => 'M3 12l9-9 9 9M5 10v10h14V10', 'links' => 'M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8zM7.5 7.5h.01',
        'cash' => 'M3 7h18v10H3zM12 10a2 2 0 100 4 2 2 0 000-4z', 'clock' => 'M12 8v4l3 2M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'star' => 'M12 3l2.9 6 6.6.9-4.8 4.6 1.2 6.5L12 17.8 6.1 21l1.2-6.5L2.5 9.9 9.1 9z',
    ];
    $nav = [
        ['label' => $g('dashboard'), 'href' => $dash, 'icon' => $I['home'], 'active' => $onDash, 'badge' => 0, 'tab' => 'home'],
        ['label' => $g('my_links'), 'href' => route('storefront.affiliate.products'), 'icon' => $I['links'], 'active' => request()->routeIs('storefront.affiliate.products'), 'badge' => 0, 'tab' => 'links'],
        ['label' => $g('commissions'), 'href' => $dash.'#commissions-title', 'icon' => $I['cash'], 'active' => false, 'badge' => 0, 'tab' => 'commissions'],
        ['label' => $g('payouts'), 'href' => $dash.'#payouts-title', 'icon' => $I['clock'], 'active' => false, 'badge' => 0, 'tab' => 'payouts'],
        ['label' => $g('bonuses'), 'href' => $dash.'#bonus-title', 'icon' => $I['star'], 'active' => false, 'badge' => 0, 'tab' => null],
    ];
    $route = request()->route();
    $locales = \App\Models\Language::activeList()->reject(fn ($l) => $l->code === app()->getLocale())
        ->map(fn ($l) => ['code' => $l->code, 'name' => $l->native_name, 'href' => route($route->getName(), ['locale' => $l->code] + $route->parameters())])->values()->all();
@endphp
<x-panel.shell panel="affiliate" :lite="true" :title="$title" :header="$header" :panelName="__('store.affiliate_program')" :home="$dash" :logout="route('storefront.affiliate.logout')"
    :nav="$nav" :locales="$locales" :user="$user" :search="__('admin.cmdk_placeholder')">
    <x-slot:external>
        <a href="{{ route('storefront.home') }}" class="hidden rounded-full border border-slate-300 px-3 py-1.5 text-xs font-bold text-slate-700 hover:border-panel hover:text-panel-text sm:inline-flex">{{ __('store.breadcrumb_home') }} ↗</a>
    </x-slot:external>
    {{ $slot }}
    <x-slot:mobileNav>
        {{-- Figma Affiliate/Bottom nav: 4 tabs, active = soft accent pill --}}
        <nav class="fixed inset-x-0 bottom-0 z-30 grid grid-cols-4 border-t border-slate-200 bg-white px-2 pb-[env(safe-area-inset-bottom)] md:hidden" aria-label="{{ __('store.affiliate_program') }}" data-bottom-nav>
            @foreach (collect($nav)->whereNotNull('tab') as $n)
                <a href="{{ $n['href'] }}" class="flex flex-col items-center gap-0.5 py-2 text-[11px] {{ $n['active'] ? 'font-bold text-panel-text' : 'text-slate-600' }}" @if ($n['active']) aria-current="page" @endif>
                    <span class="rounded-full px-4 py-1 {{ $n['active'] ? 'bg-panel-soft' : '' }}"><svg class="size-[22px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $n['icon'] }}"/></svg></span>
                    {{ $n['label'] }}
                </a>
            @endforeach
        </nav>
    </x-slot:mobileNav>
</x-panel.shell>
