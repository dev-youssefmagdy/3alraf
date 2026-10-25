@php $cur = current_tenant()->currency_symbol; $img = $bundle['image'] ?: ($bundle['components'][0]['image'] ?? null); @endphp
<x-layouts.storefront :title="$bundle['name']" :description="\Illuminate\Support\Str::limit(strip_tags($bundle['description']), 160, '')" :image="$img">
    <nav class="mb-4 flex flex-wrap items-center gap-1 text-sm text-slate-500" aria-label="breadcrumb">
        <a href="{{ route('storefront.home') }}" class="hover:text-brand-600">{{ __('store.breadcrumb_home') }}</a><span>/</span>
        <a href="{{ route('storefront.bundles.index') }}" class="hover:text-brand-600">{{ __('store.bundles') }}</a><span>/</span><span class="font-semibold text-slate-800">{{ $bundle['name'] }}</span>
    </nav>
    <div class="grid gap-8 rounded-2xl bg-white p-4 ring-1 ring-slate-100 lg:grid-cols-2" data-bundle-page>
        <div class="overflow-hidden rounded-xl bg-slate-50">@if ($img)<img src="{{ $img }}" alt="{{ $bundle['name'] }}" class="aspect-square w-full object-contain">@else<div class="aspect-square bg-slate-100"></div>@endif</div>
        <div class="space-y-4">
            <span class="inline-block rounded bg-brand-50 px-2 py-0.5 text-xs font-bold text-brand-600">{{ __('store.bundle') }}</span>
            <h1 class="text-2xl font-extrabold">{{ $bundle['name'] }}</h1>
            <div class="flex flex-wrap items-baseline gap-3">
                <span class="text-3xl font-extrabold text-brand-600">{{ number_format((float) $bundle['price'], 2) }}</span><span class="font-semibold text-brand-600">{{ $cur }}</span>
                @if (bccomp($bundle['savings'], '0', 2) > 0)
                    <span class="text-base text-slate-400 line-through">{{ number_format((float) $bundle['regular_total'], 2) }}</span>
                    <span class="rounded bg-emerald-50 px-2 py-0.5 text-sm font-bold text-emerald-700">{{ __('store.bundle_save', ['amount' => number_format((float) $bundle['savings'], 2).' '.$cur]) }}</span>
                @endif
            </div>
            @if ($bundle['description'])<p class="whitespace-pre-line text-slate-600">{{ $bundle['description'] }}</p>@endif
            <div>
                <p class="mb-2 text-sm font-bold">{{ __('store.bundle_contains') }}</p>
                <ul class="space-y-2">
                    @foreach ($bundle['components'] as $c)
                        <li class="flex items-center gap-3 rounded-lg border border-slate-100 p-2">
                            @if ($c['image'])<img src="{{ $c['image'] }}" class="size-12 rounded object-cover" alt="" loading="lazy">@endif
                            <a href="{{ route('storefront.product.show', $c['slug']) }}" class="flex-1 font-semibold hover:text-brand-600">{{ $c['name'] }}</a>
                            <span class="text-sm text-slate-500">&times; {{ $c['quantity'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
            @if (! $bundle['in_stock'])
                <p class="font-semibold text-red-600">{{ __('store.sold_out') }}</p>
            @else
                @if ($bundle['available'] !== null && $bundle['available'] <= 5)<p class="text-sm font-semibold text-amber-600">{{ __('store.bundle_left', ['n' => $bundle['available']]) }}</p>@endif
                <button type="button" class="btn btn-primary !px-8 !py-3" data-add-bundle="{{ $bundle['id'] }}">{{ __('store.add_to_cart') }}</button>
            @endif
        </div>
    </div>
</x-layouts.storefront>
