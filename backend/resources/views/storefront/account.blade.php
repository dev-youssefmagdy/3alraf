<x-layouts.storefront :title="__('store.my_account')" :noindex="true">
    <h1 class="mb-4 text-2xl font-extrabold">{{ __('store.my_account') }}</h1>
    <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
        <div class="space-y-6">
            <section class="rounded-2xl bg-white p-4 ring-1 ring-slate-100">
                <h2 class="mb-3 font-extrabold">{{ __('store.my_orders') }}</h2>
                @forelse ($orders as $o)
                    <a href="{{ route('storefront.orders.show', $o->public_token) }}" class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 py-3 text-sm hover:bg-slate-50">
                        <span><b>{{ $o->number }}</b> <span class="text-slate-500">{{ $o->placed_at?->format('Y-m-d') }}</span></span>
                        <span class="badge {{ $o->status->color() }}">{{ $o->status->label() }}</span>
                        <span class="font-bold">{{ number_format((float) $o->total, 2) }} {{ current_tenant()->currency_symbol }}</span>
                    </a>
                @empty
                    <p class="text-sm text-slate-500">{{ __('store.no_orders') }}</p>
                @endforelse
                <div class="mt-3">{{ $orders->links('storefront._pagination') }}</div>
            </section>

            <section class="rounded-2xl bg-white p-4 ring-1 ring-slate-100">
                <h2 class="mb-3 font-extrabold">{{ __('store.addresses') }}</h2>
                @foreach ($addresses as $a)
                    <div class="flex items-start justify-between gap-2 border-t border-slate-100 py-3 text-sm">
                        <span><b>{{ $a->label ?: $a->name }}</b> @if ($a->is_default)<span class="badge badge-success">{{ __('store.default') }}</span>@endif<br>{{ $a->name }} - {{ $a->phone }}<br>{{ $a->city?->name }} - {{ $a->address }}</span>
                        <button type="button" class="btn-sm btn-danger" data-delete="{{ route('storefront.account.addresses.destroy', $a->id) }}">{{ __('store.delete') }}</button>
                    </div>
                @endforeach
                <form data-ajax-form data-validate-url="{{ route('storefront.account.addresses.validate') }}" action="{{ route('storefront.account.addresses.store') }}" method="POST" class="mt-3 grid gap-3 border-t border-slate-100 pt-4 sm:grid-cols-2" novalidate>
                    @csrf
                    <x-input name="label" :label="__('store.label')" />
                    <x-input name="name" :label="__('store.name')" :value="$customer->name" required />
                    <x-input name="phone" :label="__('store.phone')" :value="$customer->phone" required />
                    <x-select name="city_id" :label="__('store.city')" :options="$cities->mapWithKeys(fn ($c) => [$c->id => $c->name])->all()" :placeholder="__('store.select_city')" required />
                    <x-input name="address" :label="__('store.address')" required class="sm:col-span-2" />
                    <button class="btn btn-primary sm:col-span-2">{{ __('store.add_address') }}</button>
                </form>
            </section>
        </div>

        <aside class="h-fit space-y-4 rounded-2xl bg-white p-4 ring-1 ring-slate-100">
            <h2 class="font-extrabold">{{ __('store.profile') }}</h2>
            <form data-ajax-form data-method="PUT" data-validate-url="{{ route('storefront.account.profile.validate') }}" action="{{ route('storefront.account.profile.update') }}" method="POST" class="space-y-3" novalidate>
                @csrf
                <x-input name="name" :label="__('store.name')" :value="$customer->name" required />
                <x-input name="email" type="email" :label="__('store.email')" :value="$customer->email" required />
                <x-input name="phone" :label="__('store.phone')" :value="$customer->phone" />
                <x-input name="password" type="password" :label="__('store.password')" :hint="__('store.password_keep')" />
                <x-input name="password_confirmation" type="password" :label="__('store.password_confirmation')" />
                <x-input name="current_password" type="password" :label="__('store.current_password')" :hint="__('store.current_password_hint')" />
                <button class="btn btn-primary w-full">{{ __('store.save') }}</button>
            </form>
            <a href="{{ route('storefront.wishlist.index') }}" class="btn btn-secondary w-full">{{ __('store.wishlist') }}</a>
        </aside>
    </div>
</x-layouts.storefront>
