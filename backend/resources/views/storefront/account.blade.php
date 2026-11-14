@php
    $svg = fn (string $d, string $cls = 'size-5') => '<svg class="'.$cls.' shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="'.$d.'"/></svg>';
    $h2 = 'font-display text-[22px]/[30px] font-bold text-slate-950';
    $initial = mb_strtoupper(mb_substr(trim((string) $customer->name), 0, 1)) ?: '؟';
    $chev = '<svg class="size-4 shrink-0 text-slate-600 ltr:-scale-x-100" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="m15 6-6 6 6 6"/></svg>';
    $tab = 'chip min-h-11 shrink-0 aria-selected:border-brand-600 aria-selected:bg-brand-600 aria-selected:text-white';
@endphp
<x-layouts.storefront :title="__('store.my_account')" :noindex="true">
    {{-- design 26:6992 / 26:7331 (no orders: 32:3111): brand header, pill tabs below 1024, orders + addresses / profile aside 420 --}}
    <header class="relative mb-5 flex flex-wrap items-center gap-4 overflow-hidden rounded-3xl bg-brand-600 px-5 py-5 text-white lg:mb-6 lg:px-8 lg:py-6">
        <span class="pointer-events-none absolute -top-12 start-1/2 size-28 rounded-full bg-brand-500 max-sm:hidden" aria-hidden="true"></span>
        <span class="relative grid size-14 shrink-0 place-items-center rounded-full bg-sun-400 font-display text-2xl font-extrabold text-slate-950" aria-hidden="true">{{ $initial }}</span>
        <div class="relative min-w-0 flex-1">
            <h1 class="font-display text-[28px]/9 font-extrabold lg:text-[32px]/[40px]">{{ __('store.my_account') }}</h1>
            <p class="text-sm font-semibold text-white">{{ __('store.account_hello', ['name' => $customer->name]) }}</p>
        </div>
        <form method="POST" action="{{ route('storefront.logout') }}" class="relative">@csrf
            <button class="btn btn-sm inline-flex items-center gap-1.5 bg-white !text-slate-950 hover:bg-brand-50">{!! $svg('M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 17l-5-5 5-5M5 12h11', 'size-4') !!}{{ __('store.logout') }}</button>
        </form>
    </header>

    <div class="-mx-4 mb-4 flex gap-2 overflow-x-auto px-4 pb-1 lg:hidden">
    <div class="flex shrink-0 gap-2" role="tablist" aria-label="{{ __('store.my_account') }}" data-account-tabs>
        <button type="button" role="tab" id="tab-orders" aria-controls="acc-orders" aria-selected="true" class="{{ $tab }}" data-account-tab="orders">{{ __('store.my_orders') }}</button>
        <button type="button" role="tab" id="tab-addresses" aria-controls="acc-addresses" aria-selected="false" tabindex="-1" class="{{ $tab }}" data-account-tab="addresses">{{ __('store.addresses') }}</button>
        <button type="button" role="tab" id="tab-profile" aria-controls="acc-profile" aria-selected="false" tabindex="-1" class="{{ $tab }}" data-account-tab="profile">{{ __('store.profile') }}</button>
    </div>
        <a href="{{ route('storefront.wishlist.index') }}" class="chip inline-flex min-h-11 shrink-0 items-center gap-1.5 border-pop-100 bg-pop-50 font-bold text-pop-600">{!! $svg('M12 20s-7-4.4-9-9a5 5 0 0 1 9-3 5 5 0 0 1 9 3c-2 4.6-9 9-9 9z', 'size-4') !!}{{ __('store.wishlist') }}</a>
    </div>

    <div class="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_420px] lg:gap-6">
        <div class="min-w-0 space-y-5 lg:space-y-6">
            <section id="acc-orders" class="panel lg:p-6" role="tabpanel" aria-labelledby="tab-orders" data-account-panel="orders">
                <h2 class="{{ $h2 }} mb-3">{{ __('store.my_orders') }}</h2>
                @forelse ($orders as $o)
                    <a href="{{ route('storefront.orders.show', $o->public_token) }}" class="mb-2 flex items-center gap-3 rounded-2xl bg-slate-100 px-4 py-3 text-sm transition hover:bg-brand-50">
                        <span class="min-w-0 flex-1">
                            <b class="block whitespace-nowrap font-display text-base text-slate-950"><bdi dir="ltr">{{ $o->number }}</bdi></b>
                            <span class="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-slate-600"><bdi dir="ltr">{{ $o->placed_at?->format('Y-m-d') }}</bdi><span class="badge {{ $o->status->color() === 'badge-muted' ? 'badge-warn' : $o->status->color() }}">{{ $o->status->label() }}</span></span>
                        </span>
                        <span class="price whitespace-nowrap text-base">{{ number_format((float) $o->total, 2) }} {{ setting('currency_symbol') }}</span>
                        {!! $chev !!}
                    </a>
                @empty
                    <x-empty-state type="orders" :title="__('store.no_orders')" :text="__('store.orders_empty_text')" :href="route('storefront.home')" :action="__('store.continue_shopping')" class="!py-6" />
                @endforelse
                <div class="mt-3">{{ $orders->links('storefront._pagination') }}</div>
            </section>

            <section id="acc-addresses" class="panel max-lg:hidden lg:p-6" role="tabpanel" aria-labelledby="tab-addresses" data-account-panel="addresses">
                <h2 class="{{ $h2 }} mb-3">{{ __('store.addresses') }}</h2>
                <div class="space-y-2">
                    @forelse ($addresses as $a)
                        <div class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-white p-3.5 text-sm">
                            <svg class="mt-0.5 size-4 shrink-0 text-pop-600" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s-7-6.2-7-11a7 7 0 1 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
                            <span class="min-w-0 flex-1 text-slate-600">
                                <span class="flex flex-wrap items-center gap-2"><b class="text-base text-slate-950">{{ $a->label ?: $a->name }}</b>@if ($a->is_default)<span class="badge bg-brand-600 text-white">{{ __('store.default') }}</span>@endif</span>
                                <span class="mt-0.5 block">{{ $a->name }} · <bdi dir="ltr">{{ $a->phone_display ?: $a->phone }}</bdi></span>
                                <span class="block">{{ $a->city?->name }} - {{ $a->address }}</span>
                            </span>
                            <button type="button" class="btn-sm btn-danger inline-flex shrink-0 items-center gap-1" data-delete="{{ route('storefront.account.addresses.destroy', $a->id) }}">{!! $svg('M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3', 'size-3.5') !!}{{ __('store.delete') }}</button>
                        </div>
                    @empty
                        <p class="rounded-2xl bg-slate-100 px-4 py-4 text-center text-sm text-slate-600">{{ __('store.no_addresses_hint') }}</p>
                    @endforelse
                </div>
                <form data-ajax-form data-validate-url="{{ route('storefront.account.addresses.validate') }}" action="{{ route('storefront.account.addresses.store') }}" method="POST" class="mt-4 grid gap-4 rounded-2xl bg-brand-50 p-4 sm:grid-cols-2 lg:p-5" novalidate>
                    @csrf
                    <h3 class="text-lg font-bold text-slate-950 sm:col-span-2">{{ __('store.add_address') }}</h3>
                    <div><x-input name="label" :label="__('store.label')" /></div>
                    <div><x-input name="name" :label="__('store.name')" :value="$customer->name" required /></div>
                    <div><x-phone-input name="phone" :label="__('store.phone')" :value="$customer->phone" required /></div>
                    <div><x-select name="city_id" :label="__('store.city')" :options="$cities->mapWithKeys(fn ($c) => [$c->id => $c->name])->all()" :placeholder="__('store.select_city')" required /></div>
                    <div class="sm:col-span-2"><x-input name="address" :label="__('store.address')" required /></div>
                    <div class="sm:col-span-2"><button class="btn btn-primary max-sm:w-full">{{ __('store.add_address') }}</button></div>
                </form>
            </section>
        </div>

        <aside id="acc-profile" class="panel space-y-4 max-lg:hidden lg:sticky lg:top-40 lg:p-6" role="tabpanel" aria-labelledby="tab-profile" data-account-panel="profile">
            <h2 class="{{ $h2 }}">{{ __('store.profile') }}</h2>
            <form data-ajax-form data-method="PUT" data-validate-url="{{ route('storefront.account.profile.validate') }}" action="{{ route('storefront.account.profile.update') }}" method="POST" class="space-y-4" novalidate>
                @csrf
                <div><x-input name="name" :label="__('store.name')" :value="$customer->name" required /></div>
                <x-input name="email" type="email" :label="__('store.email')" :value="$customer->email" required class="space-y-1" />
                <div><x-phone-input name="phone" :label="__('store.phone')" :value="$customer->phone" /></div>
                <x-input name="password" type="password" :label="__('store.password')" :hint="__('store.password_keep')" autocomplete="new-password" class="space-y-1" />
                <x-input name="password_confirmation" type="password" :label="__('store.password_confirmation')" autocomplete="new-password" class="space-y-1" />
                <x-input name="current_password" type="password" :label="__('store.current_password')" :hint="__('store.current_password_hint')" autocomplete="current-password" class="space-y-1" />
                <button class="btn btn-primary btn-lg w-full">{{ __('store.save') }}</button>
            </form>
            <a href="{{ route('storefront.wishlist.index') }}" class="flex min-h-12 items-center gap-2 rounded-2xl bg-pop-50 px-4 text-sm font-bold text-pop-600 transition hover:bg-pop-100">
                {!! $svg('M12 20s-7-4.4-9-9a5 5 0 0 1 9-3 5 5 0 0 1 9 3c-2 4.6-9 9-9 9z', 'size-5') !!}<span class="flex-1">{{ __('store.wishlist') }}</span>{!! $chev !!}
            </a>
        </aside>
    </div>
</x-layouts.storefront>
