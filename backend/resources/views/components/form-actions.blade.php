{{-- Kept for existing pages: the implementation is <x-ui.save-bar> (sticky bar). --}}
@props(['cancel', 'draftable' => null])
<x-ui.save-bar :cancel="$cancel" :draftable="$draftable" />
