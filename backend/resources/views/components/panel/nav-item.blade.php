{{-- NavItem (Figma NavItem): icon + label + optional count. Expanded in the sidebar / drawer, icon only in the tablet rail (label hidden, title = tooltip). Keeps the `side-link` hook. --}}
@props(['label', 'href', 'icon' => 'circle', 'active' => false, 'badge' => 0, 'sub' => false])
<a href="{{ $href }}" class="side-link {{ $active ? 'active' : '' }} {{ $sub ? '!min-h-10 !py-1.5 !text-[13px]' : 'md:max-xl:justify-center md:max-xl:px-0' }}" title="{{ $label }}" @if ($active) aria-current="page" @endif data-nav-link>
    @unless ($sub)<x-ui.icon :name="$icon" />@endunless
    <span class="min-w-0 flex-1 truncate {{ $sub ? '' : 'md:max-xl:sr-only' }}">{{ $label }}</span>
    @if ($badge > 0)<span class="side-count {{ $sub ? '' : 'md:max-xl:absolute md:max-xl:end-1 md:max-xl:top-1' }}">{{ $badge > 99 ? '99+' : $badge }}</span>@endif
</a>
