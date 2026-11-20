{{-- Kept for existing pages: the implementation is <x-ui.input>. --}}
@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'required' => false, 'hint' => null])
<x-ui.input :name="$name" :label="$label" :type="$type" :value="$value" :required="$required" :help="$hint" {{ $attributes }} />
