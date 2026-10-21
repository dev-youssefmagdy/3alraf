<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <nav class="flex gap-4 text-sm font-semibold">
        <a class="{{ request()->routeIs('storefront.affiliate.dashboard') ? 'text-brand-600' : 'text-slate-600' }}" href="{{ route('storefront.affiliate.dashboard') }}">{{ __('growth.dashboard') }}</a>
        <a class="{{ request()->routeIs('storefront.affiliate.products') ? 'text-brand-600' : 'text-slate-600' }}" href="{{ route('storefront.affiliate.products') }}">{{ __('growth.my_links') }}</a>
    </nav>
    <form method="POST" action="{{ route('storefront.affiliate.logout') }}">@csrf<button class="btn-sm btn-secondary">{{ __('admin.logout') }} ({{ auth('affiliate')->user()->name }})</button></form>
</div>
