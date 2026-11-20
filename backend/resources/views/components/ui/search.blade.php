{{-- Search input (Figma Field › search): leading magnifier, type=search. --}}
@props(['name' => 'q', 'label' => null, 'value' => null, 'placeholder' => null])
<x-ui.input :name="$name" :label="$label" type="search" :value="$value" icon="search" :placeholder="$placeholder ?? __('admin.search')" autocomplete="off" {{ $attributes }} />
