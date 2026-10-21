@props(['label', 'value', 'hint' => null, 'tone' => 'brand'])
@php
    $toneCls = ['brand' => 'text-brand-600', 'emerald' => 'text-emerald-600', 'slate' => 'text-slate-700'][$tone] ?? 'text-brand-600';
@endphp
<div {{ $attributes->merge(['class' => 'rounded-xl border border-slate-200 bg-white p-5 shadow-sm']) }}>
    <p class="text-sm text-slate-500">{{ $label }}</p>
    <p class="mt-1 text-3xl font-bold {{ $toneCls }}">{{ $value }}</p>
    @if ($hint)<p class="mt-1 text-xs text-slate-400">{{ $hint }}</p>@endif
</div>
