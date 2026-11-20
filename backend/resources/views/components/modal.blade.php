{{-- Kept for existing pages: the implementation is <x-ui.modal> (dialog on desktop, bottom sheet on mobile). --}}
@props(['id', 'title' => null])
<x-ui.modal :id="$id" :title="$title" {{ $attributes }}>
    {{ $slot }}
    <x-slot:footer>{{ $footer ?? '' }}</x-slot:footer>
</x-ui.modal>
