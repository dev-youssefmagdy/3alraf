{{-- Kept for existing pages: the implementation is <x-ui.checkbox>. --}}
@props(['name', 'label', 'checked' => false, 'value' => 1, 'bool' => true])
<x-ui.checkbox :name="$name" :label="$label" :checked="$checked" :value="$value" :bool="$bool" {{ $attributes }} />
