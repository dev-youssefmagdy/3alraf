{{-- Kept for existing pages: the implementation is <x-ui.button>. --}}
@props(['variant' => 'primary', 'type' => 'button', 'href' => null])
<x-ui.button :variant="$variant" :type="$type" :href="$href" {{ $attributes }}>{{ $slot }}</x-ui.button>
