{{-- Sidebar (Figma Sidebar): one markup, three looks: expanded 264 (>=1280), icon rail 72 (768-1279), drawer 300 from the start edge (<768, opened by [data-sidebar-toggle]).
     The colour stays the panel's own in dark mode. nav = list of links / groups (see panel/shell). --}}
@props(['nav' => [], 'panelName', 'home', 'logout', 'user' => null])
<aside id="sidebar" data-sidebar aria-label="{{ $panelName }}" class="fixed inset-y-0 start-0 z-50 flex w-[300px] shrink-0 flex-col bg-panel-sidebar-bg p-4 text-panel-sidebar-text shadow-panel-overlay transition-transform duration-[320ms] ease-panel
    max-md:invisible max-md:-translate-x-full max-md:data-[open]:visible max-md:data-[open]:translate-x-0 rtl:max-md:translate-x-full rtl:max-md:data-[open]:translate-x-0
    md:sticky md:top-0 md:z-30 md:h-screen md:w-[72px] md:px-3 md:shadow-none xl:w-[264px] xl:px-4">
    <div class="mb-1 flex items-center justify-between gap-2 px-1 md:max-xl:justify-center">
        <a href="{{ $home }}" class="flex min-w-0 items-center gap-2 rounded-xl bg-white p-1.5 md:max-xl:p-1" aria-label="{{ $panelName }}"><x-logo :width="48" :height="40" class="h-9 w-auto max-w-full object-contain md:max-xl:w-9" /></a>
        <button type="button" data-sidebar-toggle class="ibtn !text-panel-sidebar-text hover:!bg-white/10 md:hidden" aria-label="{{ __('ui.close') }}"><x-ui.icon name="x" /></button>
    </div>
    <p class="mb-4 px-2 text-xs font-bold tracking-wide text-panel-stripe md:max-xl:hidden">{{ $panelName }}</p>
    <nav class="-me-2 mt-2 flex-1 space-y-1 overflow-y-auto pe-2 md:max-xl:-me-3 md:max-xl:pe-3 md:max-xl:overflow-visible xl:mt-0" data-nav aria-label="{{ $panelName }}">
        @foreach ($nav as $n)
            @if ($n['group'] ?? false)
                <x-panel.nav-group :label="$n['label']" :icon="$n['icon']" :items="$n['items']" />
            @else
                <x-panel.nav-item :label="$n['label']" :href="$n['href']" :icon="$n['icon']" :active="$n['active']" :badge="$n['badge'] ?? 0" />
            @endif
        @endforeach
    </nav>
    {{ $sidebarFooter ?? '' }}
    @if ($user)
        <div class="mt-3 flex items-center gap-3 rounded-xl p-2 md:max-xl:justify-center md:max-xl:p-0">
            <x-ui.avatar :name="$user->name" />
            <div class="min-w-0 flex-1 md:max-xl:hidden"><p class="truncate text-sm font-semibold text-white">{{ $user->name }}</p><p class="truncate text-xs text-panel-sidebar-muted">{{ $panelName }}</p></div>
            <form method="POST" action="{{ $logout }}" class="md:max-xl:hidden">@csrf<button class="ibtn !text-panel-sidebar-muted hover:!bg-white/10 hover:!text-white" aria-label="{{ __('admin.logout') }}" title="{{ __('admin.logout') }}"><x-ui.icon name="logout" /></button></form>
        </div>
    @endif
</aside>
