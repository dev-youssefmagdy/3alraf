{{-- Affiliate panel shell (/{locale}/affiliate, guard `affiliate`): <x-panel.shell panel="affiliate"> (mint), storefront CSS bundle (lite).
     Figma "Affiliate/App shell": sidebar on desktop, icon rail on tablet, 4-tab bottom nav on phones (Home, Links, Earnings, Account). Sections of the dashboard are reached by anchors. --}}
@props(['title' => '', 'header' => null])
@php
    $user = auth('affiliate')->user();
    $g = fn ($k) => __('growth.'.$k);
    $dash = route('storefront.affiliate.dashboard');
    $onDash = request()->routeIs('storefront.affiliate.dashboard');
    $nav = [
        ['label' => $g('dashboard'), 'href' => $dash, 'icon' => 'home', 'active' => $onDash, 'badge' => 0, 'tab' => 'home'],
        ['label' => $g('my_links'), 'href' => route('storefront.affiliate.products'), 'icon' => 'link', 'active' => request()->routeIs('storefront.affiliate.products'), 'badge' => 0, 'tab' => 'links'],
        ['label' => $g('commissions'), 'href' => $dash.'#commissions-title', 'icon' => 'wallet', 'active' => false, 'badge' => 0, 'tab' => 'commissions'],
        ['label' => $g('payouts'), 'href' => $dash.'#payouts-title', 'icon' => 'clock', 'active' => false, 'badge' => 0, 'tab' => 'payouts'],
        ['label' => $g('bonuses'), 'href' => $dash.'#bonus-title', 'icon' => 'star', 'active' => false, 'badge' => 0, 'tab' => null],
    ];
    $bottom = collect($nav)->whereNotNull('tab')->values()->all();
    $route = request()->route();
    $locales = \App\Models\Language::activeList()->reject(fn ($l) => $l->code === app()->getLocale())
        ->map(fn ($l) => ['code' => $l->code, 'name' => $l->native_name, 'href' => route($route->getName(), ['locale' => $l->code] + $route->parameters())])->values()->all();
@endphp
<x-panel.shell panel="affiliate" :lite="true" :title="$title" :header="$header" :panelName="__('store.affiliate_program')" :home="$dash" :logout="route('storefront.affiliate.logout')"
    :nav="$nav" :locales="$locales" :user="$user" :search="__('admin.cmdk_placeholder')" :bottom="$bottom">
    <x-slot:external>
        <a href="{{ route('storefront.home') }}" class="hidden min-h-11 items-center gap-1.5 rounded-xl px-2.5 text-xs font-bold text-panel-text-muted hover:bg-panel-surface-sunken hover:text-panel-text lg:inline-flex">{{ __('store.breadcrumb_home') }}<x-ui.icon name="external" class="size-3.5" /></a>
    </x-slot:external>
    {{ $slot }}
</x-panel.shell>
