@props(['method'])
@php
    $icons = [
        'cod' => 'M3 7h18v10H3zM12 14a2 2 0 100-4 2 2 0 000 4zM6 10v.01M18 14v.01',
        'wallet' => 'M3 7a2 2 0 012-2h13v3M3 7v10a2 2 0 002 2h14V8H5a2 2 0 01-2-1zM16 13.500h.01',
        'online' => 'M3 6h18v12H3zM3 10h18M7 15h3',
    ];
@endphp
<span class="inline-flex items-center gap-1.5 rounded bg-white px-2 py-1 text-xs font-semibold text-slate-700" title="{{ $method['name'] }}">
    <svg class="size-4 text-brand-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icons[$method['type']] ?? $icons['online'] }}"/></svg>{{ $method['name'] }}
</span>
