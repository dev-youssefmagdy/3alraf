{{-- TopBar (Figma TopBar): mobile 56 = menu, title, search, bell/avatar; tablet/desktop 64 = command-palette pill, locale, theme, bell, user menu. Slots: headerEnd (bell), external, userMenu. --}}
@props(['panelName', 'logout', 'locales' => [], 'user' => null, 'search' => null, 'home' => '#'])
<header class="sticky top-0 z-30 flex h-14 items-center gap-2 border-b border-panel-border bg-panel-surface/95 px-3 backdrop-blur md:h-16 md:gap-3 md:px-6" data-topbar>
    <button type="button" data-sidebar-toggle class="ibtn md:hidden" aria-label="{{ __('admin.menu') }}" aria-controls="sidebar"><x-ui.icon name="menu" class="size-6" /></button>
    <a href="{{ $home }}" class="truncate text-base font-bold text-panel-text md:hidden">{{ $panelName }}</a>

    <button type="button" data-cmdk-open class="hidden h-11 w-full max-w-sm items-center gap-2 rounded-full bg-panel-surface-sunken px-4 text-sm text-panel-text-subtle transition hover:ring-2 hover:ring-panel-accent/30 md:flex" aria-label="{{ $search ?? __('admin.search') }}" aria-haspopup="dialog">
        <x-ui.icon name="search" class="size-[18px]" /><span class="flex-1 truncate text-start">{{ $search ?? __('admin.search') }}</span><kbd class="hidden rounded-md border border-panel-border bg-panel-surface px-1.5 text-[10px] font-semibold text-panel-text-subtle lg:inline" dir="ltr">Ctrl K</kbd>
    </button>

    <div class="ms-auto flex items-center gap-0.5 sm:gap-1">
        <button type="button" data-cmdk-open class="ibtn md:hidden" aria-label="{{ $search ?? __('admin.search') }}"><x-ui.icon name="search" /></button>
        {{ $external ?? '' }}
        @foreach ($locales as $l)
            <a href="{{ $l['href'] }}" hreflang="{{ $l['code'] }}" class="hidden min-h-11 items-center gap-1.5 rounded-xl px-2.5 text-xs font-bold uppercase text-panel-text-muted hover:bg-panel-surface-sunken hover:text-panel-text sm:inline-flex" title="{{ $l['name'] }}"><x-ui.icon name="globe" class="size-[18px]" />{{ $l['code'] }}</a>
        @endforeach
        <button type="button" data-theme-toggle class="ibtn" aria-label="{{ __('ui.toggle_theme') }}" title="{{ __('ui.toggle_theme') }}"><x-ui.icon name="moon" class="dark-hide" /><x-ui.icon name="sun" class="hidden dark-show" /></button>
        {{ $headerEnd ?? '' }}
        @if ($user)
            <div class="relative">
                <button type="button" data-dropdown-toggle="user-menu" class="flex min-h-11 items-center gap-2 rounded-full p-1 text-sm hover:bg-panel-surface-sunken sm:pe-3" aria-haspopup="menu" aria-label="{{ $user->name }}">
                    <x-ui.avatar :name="$user->name" size="sm" /><span class="hidden max-w-32 truncate font-semibold text-panel-text lg:inline">{{ $user->name }}</span><x-ui.icon name="chevron-down" class="hidden size-4 text-panel-text-subtle sm:block" />
                </button>
                <div id="user-menu" data-dropdown role="menu" class="absolute end-0 z-50 mt-2 hidden w-60 rounded-2xl border border-panel-border bg-panel-surface-raised p-1.5 shadow-panel-lg">
                    <p class="truncate px-3 pb-1 pt-1.5 text-sm font-semibold text-panel-text">{{ $user->name }}</p><p class="truncate px-3 pb-2 text-xs text-panel-text-subtle">{{ $panelName }}</p>
                    @foreach ($locales as $l)<a href="{{ $l['href'] }}" role="menuitem" class="ui-menu-item sm:hidden"><x-ui.icon name="globe" class="size-4" />{{ $l['name'] }}</a>@endforeach
                    {{ $userMenu ?? '' }}
                    <form method="POST" action="{{ $logout }}" class="mt-1 border-t border-panel-border pt-1">@csrf<button class="ui-menu-item danger" role="menuitem"><x-ui.icon name="logout" class="size-4" />{{ __('admin.logout') }}</button></form>
                </div>
            </div>
        @endif
    </div>
</header>
