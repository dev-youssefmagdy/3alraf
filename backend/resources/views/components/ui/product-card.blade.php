{{-- ProductCard, panel (Figma ProductCard): size tile (POS grid) | row (lists, mobile). product = model or array: name, sku, price, stock, image_url. href optional.
     Not the storefront <x-product-card>. --}}
@props(['product', 'size' => 'tile', 'href' => null, 'currency' => null])
@php
    $name = data_get($product, 'name'); $name = is_array($name) ? ($name[app()->getLocale()] ?? collect($name)->filter()->first()) : $name;
    $sku = data_get($product, 'sku'); $price = data_get($product, 'price'); $stock = data_get($product, 'stock'); $img = data_get($product, 'image_url');
    $tag = $href ? 'a' : 'div';
@endphp
@if ($size === 'row')
<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'flex min-h-16 items-center gap-3 rounded-xl border border-panel-border bg-panel-surface p-2.5 '.($href ? 'hover:border-panel-accent' : '')]) }}>
    <span class="grid size-12 shrink-0 place-items-center overflow-hidden rounded-lg bg-panel-surface-sunken text-panel-text-subtle">@if ($img)<img src="{{ $img }}" alt="" class="size-full object-cover" loading="lazy">@else<x-ui.icon name="box" />@endif</span>
    <span class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold text-panel-text">{{ $name }}</span>@if ($sku)<span class="block text-xs text-panel-text-subtle" dir="ltr">{{ $sku }}</span>@endif</span>
    <span class="text-end">@if ($price !== null)<span class="block text-sm font-bold tabular-nums text-panel-text" dir="ltr">{{ $price }}</span>@endif @if ($stock !== null)<x-ui.badge :tone="(int) $stock > 0 ? 'success' : 'danger'" size="sm" dot>{{ $stock }}</x-ui.badge>@endif</span>
</{{ $tag }}>
@else
<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'flex flex-col overflow-hidden rounded-2xl border border-panel-border bg-panel-surface shadow-panel-xs '.($href ? 'transition hover:border-panel-accent hover:shadow-panel-md' : '')]) }}>
    <span class="grid aspect-square place-items-center bg-panel-surface-sunken text-panel-text-subtle">@if ($img)<img src="{{ $img }}" alt="" class="size-full object-cover" loading="lazy">@else<x-ui.icon name="image" class="size-8" />@endif</span>
    <span class="flex flex-1 flex-col gap-1 p-3"><span class="line-clamp-2 text-sm font-semibold text-panel-text">{{ $name }}</span>
        <span class="mt-auto flex items-center justify-between gap-2">@if ($price !== null)<span class="text-sm font-bold tabular-nums text-panel-accent-text" dir="ltr">{{ $price }}</span>@endif @if ($stock !== null)<x-ui.badge :tone="(int) $stock > 0 ? 'success' : 'danger'" size="sm" dot>{{ $stock }}</x-ui.badge>@endif</span></span>
</{{ $tag }}>
@endif
