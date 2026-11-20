{{-- Kept for existing pages: the implementation is <x-ui.phone>. --}}
@props(['name' => 'phone', 'label' => null, 'value' => null, 'required' => false])
<x-ui.phone :name="$name" :label="$label" :value="$value" :required="$required" {{ $attributes }} />
