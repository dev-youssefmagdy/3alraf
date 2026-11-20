{{-- Kept for existing pages: the implementation is <x-ui.textarea>. --}}
@props(['name', 'label' => null, 'value' => null, 'rows' => 3, 'required' => false])
<x-ui.textarea :name="$name" :label="$label" :value="$value" :rows="$rows" :required="$required" {{ $attributes }} />
