{{-- Kept for existing pages: the implementation is <x-ui.card> (same props, `actions` slot). --}}
@props(['title' => null, 'subtitle' => null, 'flush' => false])
<x-ui.card :title="$title" :subtitle="$subtitle" :flush="$flush" {{ $attributes }}>
    <x-slot:actions>{{ $actions ?? '' }}</x-slot:actions>
    <x-slot:footer>{{ $footer ?? '' }}</x-slot:footer>
    {{ $slot }}
</x-ui.card>
