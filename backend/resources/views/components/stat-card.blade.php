{{-- KPI tile (Figma Stat card: soft tinted tones). tone: brand | mint | sun | violet | emerald | slate | panel (follows the panel accent). --}}
@props(['label', 'value', 'hint' => null, 'tone' => 'brand'])
@php
    [$bg, $fg] = [
        'brand' => ['bg-brand-50', 'text-brand-700'], 'mint' => ['bg-mint-100', 'text-mint-700'], 'emerald' => ['bg-mint-100', 'text-mint-700'],
        'sun' => ['bg-sun-100', 'text-slate-950'], 'violet' => ['bg-violet-100', 'text-violet-700'], 'slate' => ['bg-slate-100', 'text-slate-900'],
        'panel' => ['bg-panel-soft', 'text-panel-text'],
    ][$tone] ?? ['bg-brand-50', 'text-brand-700'];
@endphp
<div {{ $attributes->merge(['class' => "min-w-0 rounded-3xl p-5 $bg"]) }}>
    <p class="text-sm font-bold {{ $fg }}">{{ $label }}</p>
    <p class="mt-1 font-display text-[28px]/9 font-extrabold tabular-nums [overflow-wrap:anywhere] {{ $fg }}">{{ $value }}</p>
    @if ($hint)<p class="mt-1 text-xs text-slate-600">{{ $hint }}</p>@endif
</div>
