@php
    $cur = setting('currency_symbol');
    $img = $bundle['image'] ?: ($bundle['components'][0]['image'] ?? null);
    $collage = array_slice(array_values(array_filter($bundle['components'], fn ($c) => ! empty($c['image']))), 0, 4);
    $saves = bccomp($bundle['savings'], '0', 2) > 0;
    $bc = [[__('store.breadcrumb_home'), route('storefront.home')], [__('store.bundles'), route('storefront.bundles.index')], [$bundle['name'], url()->current()]];
@endphp
<x-layouts.storefront :title="$bundle['name']" :description="\Illuminate\Support\Str::limit(strip_tags($bundle['description']), 160, '')" :image="$img" :preload-image="$bundle['image'] ?: null">
    {{-- design 27:9176 / 27:9415: gold collage media + info --}}
    <x-breadcrumbs :items="$bc" />
    <div class="grid gap-6 lg:grid-cols-2 lg:gap-10" data-bundle-page>
        <div class="lg:sticky lg:top-40 lg:self-start">
            <div class="relative overflow-hidden rounded-3xl bg-accent-light lg:rounded-[32px]">
                @if ($bundle['image'])
                    <img src="{{ $bundle['image'] }}" alt="{{ $bundle['name'] }}" class="aspect-square w-full object-cover {{ $bundle['in_stock'] ? '' : 'opacity-60 grayscale' }}" width="800" height="800" fetchpriority="high" decoding="async">
                @elseif (count($collage) > 1)
                    <div class="grid aspect-square grid-cols-2 gap-3 p-5 lg:gap-4 lg:p-8 {{ $bundle['in_stock'] ? '' : 'opacity-60 grayscale' }}">
                        @foreach ($collage as $c)<img src="{{ $c['image'] }}" alt="{{ $c['name'] }}" class="size-full rounded-2xl bg-white object-cover shadow-card {{ count($collage) === 3 && $loop->first ? 'col-span-2 aspect-[2/1]' : 'aspect-square' }}" width="400" height="400" decoding="async" @if ($loop->first) fetchpriority="high" @else loading="lazy" @endif>@endforeach
                    </div>
                @elseif ($img)
                    <img src="{{ $img }}" alt="{{ $bundle['name'] }}" class="aspect-square w-full object-cover" width="800" height="800" fetchpriority="high" decoding="async">
                @else
                    <div class="aspect-square"></div>
                @endif
                <span class="badge badge-bundle absolute start-4 top-4">{{ __('store.bundle') }}</span>
            </div>
        </div>

        <div class="min-w-0 space-y-5">
            <div>
                <span class="badge badge-bundle">{{ __('store.bundle_items', ['n' => count($bundle['components'])]) }}</span>
                <h1 class="mt-2 font-display text-[28px]/9 font-bold text-slate-950 lg:text-[32px]/10">{{ $bundle['name'] }}</h1>
            </div>
            <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                <span class="price text-[30px]/9 text-brand-600">{{ number_format((float) $bundle['price'], 2) }} <small class="font-sans text-base font-bold">{{ $cur }}</small></span>
                @if ($saves)
                    <span class="price-old !text-sm">{{ number_format((float) $bundle['regular_total'], 2) }} {{ $cur }}</span>
                    <span class="badge bg-sun-400 !text-xs text-slate-950">{{ __('store.bundle_save', ['amount' => number_format((float) $bundle['savings'], 2).' '.$cur]) }}</span>
                @endif
            </div>
            @if ($bundle['description'])<p class="whitespace-pre-line text-base/[26px] text-slate-600">{{ $bundle['description'] }}</p>@endif

            <section class="panel">
                <h2 class="mb-3 text-base font-bold text-slate-950">{{ __('store.bundle_contains') }}</h2>
                <ul class="divide-y divide-slate-100">
                    @foreach ($bundle['components'] as $c)
                        <li class="flex items-center gap-3 py-2.5 first:pt-0 last:pb-0">
                            @if ($c['image'])<img src="{{ $c['image'] }}" class="size-14 shrink-0 rounded-xl bg-accent-light object-cover" alt="" loading="lazy" decoding="async" width="56" height="56">@else<span class="size-14 shrink-0 rounded-xl bg-accent-light"></span>@endif
                            <a href="{{ route('storefront.product.show', $c['slug']) }}" class="min-w-0 flex-1 text-sm/[22px] font-semibold text-slate-950 hover:text-brand-600">{{ $c['name'] }}</a>
                            <span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-700">&times; {{ $c['quantity'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>

            @if (! $bundle['in_stock'])
                <p class="flex items-center gap-2"><span class="badge badge-out !text-sm">{{ __('store.sold_out') }}</span></p>
            @else
                @if ($bundle['available'] !== null && $bundle['available'] <= 5)<p class="text-sm font-bold text-amber-700">{{ __('store.bundle_left', ['n' => $bundle['available']]) }}</p>@endif
                <button type="button" class="btn btn-primary btn-lg w-full sm:w-auto sm:!px-12" data-add-bundle="{{ $bundle['id'] }}">
                    <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.4 11.2a1 1 0 0 0 1 .8h8.8a1 1 0 0 0 1-.8L20 8H6M9 20h.01M17 20h.01"/></svg>
                    {{ __('store.add_to_cart') }}</button>
            @endif
            @include('storefront._trust-trio')
        </div>
    </div>
</x-layouts.storefront>
