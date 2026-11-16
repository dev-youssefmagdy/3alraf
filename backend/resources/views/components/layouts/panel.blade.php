{{-- Picks the shell of the panel serving the request: shared marketing modules (banners, coupons, flash sales, bundles, blog, pages)
     render inside /admin or /marketer with that panel's own layout, nav, logout and locale. No link crosses panels. --}}
@props(['title' => '', 'header' => null])
@if (request()->routeIs('marketer.*'))
    <x-layouts.marketer :title="$title" :header="$header">{{ $slot }}</x-layouts.marketer>
@else
    <x-layouts.admin :title="$title" :header="$header">{{ $slot }}</x-layouts.admin>
@endif
