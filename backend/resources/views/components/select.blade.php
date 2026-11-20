{{-- Kept for existing pages: the implementation is <x-ui.select> (`search` = `searchable`). --}}
@props(['name', 'label' => null, 'options' => [], 'value' => null, 'placeholder' => null, 'required' => false, 'search' => false])
<x-ui.select :name="$name" :label="$label" :options="$options" :value="$value" :placeholder="$placeholder" :required="$required" :searchable="$search" {{ $attributes }} />
