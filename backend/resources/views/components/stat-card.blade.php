{{-- Kept for existing pages: the implementation is <x-ui.stat-card> (same props + icon, trend, delta, spark, size). --}}
@props(['label', 'value', 'hint' => null, 'tone' => 'panel'])
<x-ui.stat-card :label="$label" :value="$value" :hint="$hint" :tone="$tone" {{ $attributes }} />
