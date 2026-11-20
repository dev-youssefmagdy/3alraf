{{-- NavGroup (Figma NavGroup): collapsible group (native <details>). One group is open at a time and the active one starts open (ui.js). In the tablet rail the items open as a flyout beside the rail. --}}
@props(['label', 'icon' => 'circle', 'items' => []])
@php
    $open = collect($items)->contains('active', true);
    $badge = collect($items)->sum('badge');
@endphp
<details class="group/nav relative" @if ($open) open @endif data-nav-group>
    <summary class="side-link cursor-pointer select-none list-none md:max-xl:justify-center md:max-xl:px-0 [&::-webkit-details-marker]:hidden {{ $open ? '!text-white font-bold' : '' }}" title="{{ $label }}" data-nav-group-summary>
        <x-ui.icon :name="$icon" />
        <span class="min-w-0 flex-1 truncate md:max-xl:sr-only">{{ $label }}</span>
        @if ($badge > 0)<span class="side-count !ms-0 md:max-xl:absolute md:max-xl:end-1 md:max-xl:top-1">{{ $badge > 99 ? '99+' : $badge }}</span>@endif
        <x-ui.icon name="chevron-end" class="size-4 transition group-open/nav:rotate-90 rtl:group-open/nav:-rotate-90 md:max-xl:hidden" />
    </summary>
    <div class="ms-5 mt-1 space-y-0.5 border-s border-white/15 ps-2 md:max-xl:fixed md:max-xl:z-[60] md:max-xl:m-0 md:max-xl:w-60 md:max-xl:rounded-2xl md:max-xl:border md:max-xl:border-white/10 md:max-xl:bg-panel-sidebar-bg md:max-xl:p-2 md:max-xl:shadow-panel-lg" data-nav-flyout>
        <p class="hidden px-3 py-1 text-xs font-bold text-panel-sidebar-muted md:max-xl:block">{{ $label }}</p>
        @foreach ($items as $i)<x-panel.nav-item :label="$i['label']" :href="$i['href']" :active="$i['active']" :badge="$i['badge'] ?? 0" :sub="true" />@endforeach
    </div>
</details>
