<x-layouts.storefront :title="$tenant->name" :description="__('store.seo_default_desc')" :jsonld="['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => $tenant->name, 'url' => url('/'.app()->getLocale()),
    'potentialAction' => ['@type' => 'SearchAction', 'target' => route('storefront.search').'?q={q}', 'query-input' => 'required name=q']]">
    {{-- hero slider --}}
    @if ($hero->isNotEmpty())
        <section class="relative overflow-hidden rounded-2xl bg-slate-200" data-slider>
            <div class="flex transition-transform duration-500" data-slides>
                @foreach ($hero as $b)
                    <a href="{{ $b->link_url ?: '#' }}" class="block w-full shrink-0" data-slide>
                        @if ($b->imageUrl())<img src="{{ $b->imageUrl() }}" alt="{{ $b->title }}" class="aspect-[16/6] w-full object-cover" @if (! $loop->first) loading="lazy" @endif>@endif
                    </a>
                @endforeach
            </div>
            @if ($hero->count() > 1)
                <button type="button" data-slide-prev class="absolute start-3 top-1/2 -translate-y-1/2 rounded-full bg-white/80 px-3 py-1 text-xl shadow" aria-label="prev">&#8249;</button>
                <button type="button" data-slide-next class="absolute end-3 top-1/2 -translate-y-1/2 rounded-full bg-white/80 px-3 py-1 text-xl shadow" aria-label="next">&#8250;</button>
                <div class="absolute inset-x-0 bottom-3 flex justify-center gap-1.5" data-dots>@foreach ($hero as $b)<span class="size-2 rounded-full bg-white/60"></span>@endforeach</div>
            @endif
        </section>
    @endif

    {{-- category icon row --}}
    @if (count($categories))
        <section class="mt-6">
            <div class="flex gap-4 overflow-x-auto pb-2">
                @foreach ($categories as $c)
                    <a href="{{ route('storefront.category.show', $c['slug']) }}" class="group w-24 shrink-0 text-center">
                        <span class="mx-auto grid size-20 place-items-center overflow-hidden rounded-full bg-white ring-1 ring-slate-200 transition group-hover:ring-brand-500">
                            @if ($c['image'])<img src="{{ $c['image'] }}" alt="" class="size-full object-cover" loading="lazy">@else<span class="text-2xl font-bold text-brand-600">{{ mb_substr($c['name'], 0, 1) }}</span>@endif
                        </span>
                        <span class="mt-2 block text-xs font-semibold text-slate-700 group-hover:text-brand-600">{{ $c['name'] }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- promo banners --}}
    @if ($strips->isNotEmpty() || $tiles->isNotEmpty())
        <section class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-{{ min(4, max(2, $strips->count() + $tiles->count())) }}">
            @foreach ($strips->concat($tiles) as $b)
                <a href="{{ $b->link_url ?: '#' }}" class="block overflow-hidden rounded-xl bg-slate-100">@if ($b->imageUrl())<img src="{{ $b->imageUrl() }}" alt="{{ $b->title }}" class="aspect-[16/7] w-full object-cover transition hover:scale-105" loading="lazy">@endif</a>
            @endforeach
        </section>
    @endif

    {{-- flash sale --}}
    @if ($flash)
        <x-rail :title="$flash['sale']->name" :items="$flash['items']" :countdown="$flash['sale']->ends_at->toIso8601String()" :accent="true" />
    @endif

    <x-rail :title="__('store.recommended')" :items="$rails['recommended']" />
    <x-rail :title="__('store.most_sold')" :items="$rails['most_sold']" />
    <x-rail :title="__('store.most_reviewed')" :items="$rails['most_reviewed']" />

    @foreach ($categoryRails as $r)
        <x-rail :title="$r['category']->name" :items="$r['items']" :href="route('storefront.category.show', $r['category']->slug)" />
    @endforeach

    {{-- brand strip --}}
    @if ($brands->isNotEmpty())
        <section class="mt-8 rounded-2xl bg-white p-4 ring-1 ring-slate-100">
            <h2 class="mb-3 text-lg font-extrabold">{{ __('store.brands') }}</h2>
            <div class="flex flex-wrap items-center gap-4">
                @foreach ($brands as $br)
                    <a href="{{ route('storefront.search', ['brands' => [$br->id]]) }}" class="grid h-16 w-32 place-items-center rounded-lg border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-600 hover:border-brand-500">
                        @if ($br->logo_url ?? null)<img src="{{ $br->logo_url }}" alt="{{ $br->name }}" class="max-h-12 object-contain" loading="lazy">@else{{ $br->name }}@endif
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- popup banner --}}
    @if ($popup && $popup->imageUrl())
        <div class="fixed inset-0 z-50 hidden place-items-center bg-black/50 p-4" data-popup="{{ $popup->id }}">
            <div class="relative max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl">
                <button type="button" data-popup-close class="absolute end-2 top-2 grid size-8 place-items-center rounded-full bg-white/90 text-xl leading-none shadow" aria-label="{{ __('store.close') }}">&times;</button>
                <a href="{{ $popup->link_url ?: '#' }}"><img src="{{ $popup->imageUrl() }}" alt="{{ $popup->title }}" class="w-full"></a>
            </div>
        </div>
    @endif
</x-layouts.storefront>
