{{-- OrderCard (Figma OrderCard): the mobile replacement for an order table row. order = model or array: number, customer_name, total, placed_at, status (enum with label() or string). --}}
@props(['order', 'href' => null])
@php
    $st = data_get($order, 'status'); $stv = is_object($st) ? ($st->value ?? (string) $st) : (string) $st; $stl = is_object($st) && method_exists($st, 'label') ? $st->label() : $stv;
    $tone = match (true) { in_array($stv, ['delivered', 'completed', 'paid'], true) => 'success', in_array($stv, ['cancelled', 'failed', 'rejected'], true) => 'danger', in_array($stv, ['shipped', 'processing'], true) => 'info', default => 'warning' };
    $at = data_get($order, 'placed_at'); $at = $at instanceof \Carbon\CarbonInterface ? $at->translatedFormat('j M, H:i') : $at;
    $tag = $href ? 'a' : 'div';
@endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'block rounded-2xl border border-panel-border bg-panel-surface p-4 shadow-panel-xs '.($href ? 'hover:border-panel-accent' : '')]) }}>
    <div class="flex items-center justify-between gap-2"><span class="font-bold text-panel-text" dir="ltr">#{{ data_get($order, 'number') }}</span><x-ui.badge :tone="$tone" dot>{{ $stl }}</x-ui.badge></div>
    <p class="mt-1 truncate text-sm text-panel-text-muted">{{ data_get($order, 'customer_name') }}</p>
    <div class="mt-3 flex items-center justify-between gap-2"><span class="text-xs text-panel-text-subtle">{{ $at }}</span><span class="text-base font-bold tabular-nums text-panel-text" dir="ltr">{{ data_get($order, 'total') }}</span></div>
</{{ $tag }}>
