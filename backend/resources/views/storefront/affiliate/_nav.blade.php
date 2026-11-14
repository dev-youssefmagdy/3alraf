{{-- Affiliate area navigation (design 4.15 pill tabs, violet): Dashboard / My links + logout, on a violet-soft band. --}}
@php
    $affTabs = [
        ['storefront.affiliate.dashboard', __('growth.dashboard'), 'M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z'],
        ['storefront.affiliate.products', __('growth.my_links'), 'M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8zM7.5 7.5h.01'],
    ];
@endphp
<div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-3xl bg-violet-100 p-3 lg:mb-6 lg:rounded-full lg:px-3 lg:py-2.5">
    <nav class="flex min-w-0 flex-wrap items-center gap-1.5" aria-label="{{ __('store.affiliate_program') }}">
        @foreach ($affTabs as [$route, $label, $icon])
            @php($on = request()->routeIs($route))
            <a href="{{ route($route) }}" @if ($on) aria-current="page" @endif
                class="inline-flex min-h-11 items-center gap-2 rounded-full px-4 text-sm font-bold transition {{ $on ? 'bg-violet-500 text-white' : 'text-violet-700 hover:bg-white/70' }}">
                <svg class="size-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="{{ $icon }}"/></svg>{{ $label }}
            </a>
        @endforeach
    </nav>
    <form method="POST" action="{{ route('storefront.affiliate.logout') }}" class="max-sm:ms-auto">@csrf
        <button class="inline-flex min-h-11 max-w-full items-center gap-2 rounded-full border border-slate-300 bg-white px-4 text-xs font-bold text-slate-950 hover:border-violet-500 hover:text-violet-700">
            <svg class="size-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 17l-5-5 5-5M5 12h11"/></svg>
            <span class="truncate">{{ __('admin.logout') }} (<bdi>{{ auth('affiliate')->user()->name }}</bdi>)</span>
        </button>
    </form>
</div>
