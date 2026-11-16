{{--
    Panel app shell (Figma "Panel/App shell", "Affiliate/App shell"): sidebar (right in RTL) + top bar + content, one markup for the three
    panels. Colours come from <html data-panel> (resources/css/panel.css). No link here crosses panels: every URL is passed in by the layout.

    nav: list of ['label', 'href', 'icon' (svg path), 'active' (bool), 'badge' (int)]
         or groups ['group' => true, 'label', 'icon', 'items' => [links...]]
    locales: [['code', 'name', 'href']]
--}}
@props([
    'panel', 'title' => '', 'panelName', 'home', 'logout', 'nav' => [], 'locales' => [], 'user' => null,
    'search' => null, 'lite' => false, 'external' => null, 'header' => null,
])
@php
    $chev = '<svg class="size-4 shrink-0 transition-transform group-open:rotate-90 rtl:group-open:-rotate-90 rtl:-scale-x-100" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 6l6 6-6 6"/></svg>';
    $ico = fn ($d, $cls = 'size-5') => '<svg class="'.$cls.' shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="'.e($d).'"/></svg>';
    $count = fn ($n) => $n > 0 ? '<span class="side-count">'.($n > 99 ? '99+' : $n).'</span>' : '';
    $initial = $user ? mb_substr(trim((string) $user->name), 0, 1) : '';
@endphp
<x-layouts.base :title="$title ?: $panelName" :siteName="$panelName" :panel="$panel" :lite="$lite" bodyClass="bg-slate-50">
    <div class="flex min-h-screen">
        <aside id="sidebar" class="fixed inset-y-0 start-0 z-50 hidden w-64 shrink-0 flex-col bg-panel-sidebar p-4 md:sticky md:top-0 md:z-auto md:h-screen md:flex" aria-label="{{ $panelName }}">
            <a href="{{ $home }}" class="mb-1 flex items-center gap-2 px-2">
                <img src="{{ asset('logo-sm.webp') }}" alt="{{ setting('name') ?: config('app.name') }}" class="h-10 w-auto max-w-full object-contain">
            </a>
            <p class="mb-5 px-2 text-xs font-bold tracking-wide text-panel-stripe">{{ $panelName }}</p>
            <nav class="-me-2 flex-1 space-y-1 overflow-y-auto pe-2" data-nav>
                @foreach ($nav as $n)
                    @if ($n['group'] ?? false)
                        @php($open = collect($n['items'])->contains('active', true))
                        <details class="group" @if ($open) open @endif>
                            <summary class="side-link cursor-pointer select-none list-none justify-between [&::-webkit-details-marker]:hidden {{ $open ? '!text-white font-bold' : '' }}">
                                <span class="flex items-center gap-3">{!! $ico($n['icon']) !!}{{ $n['label'] }}</span>
                                <span class="flex items-center gap-2">{!! $count(collect($n['items'])->sum('badge')) !!}{!! $chev !!}</span>
                            </summary>
                            <div class="ms-5 mt-1 space-y-0.5 border-s border-white/15 ps-2">
                                @foreach ($n['items'] as $i)
                                    <a href="{{ $i['href'] }}" class="side-link !py-1.5 !text-[13px] {{ $i['active'] ? 'active' : '' }}" @if ($i['active']) aria-current="page" @endif data-nav-link>{{ $i['label'] }} {!! $count($i['badge'] ?? 0) !!}</a>
                                @endforeach
                            </div>
                        </details>
                    @else
                        <a href="{{ $n['href'] }}" class="side-link {{ $n['active'] ? 'active' : '' }}" @if ($n['active']) aria-current="page" @endif data-nav-link>
                            {!! $ico($n['icon']) !!}<span>{{ $n['label'] }}</span>{!! $count($n['badge'] ?? 0) !!}
                        </a>
                    @endif
                @endforeach
            </nav>
            {{ $sidebarFooter ?? '' }}
        </aside>
        <div class="fixed inset-0 z-40 hidden bg-slate-950/40 md:hidden" data-sidebar-backdrop></div>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-slate-200 bg-white px-4 md:px-6">
                <button type="button" data-sidebar-toggle class="rounded-lg p-1.5 text-slate-700 hover:bg-slate-100 md:hidden" aria-label="{{ __('admin.menu') }}">
                    {!! $ico('M4 6h16M4 12h16M4 18h16', 'size-6') !!}
                </button>
                <span class="hidden rounded-full bg-panel-soft px-3 py-1 text-xs font-bold text-panel-text sm:inline">{{ $panelName }}</span>

                {{-- ⌘K: jump to any page of the sidebar (filters the nav links client-side, resources/js/lib/ui.js) --}}
                <div class="relative hidden w-full max-w-sm md:block" data-cmdk>
                    <label class="flex items-center gap-2 rounded-full bg-slate-100 px-4 py-2 text-sm text-slate-500 focus-within:ring-2 focus-within:ring-panel/30">
                        {!! $ico('M21 21l-4.3-4.3M11 18a7 7 0 100-14 7 7 0 000 14z', 'size-4') !!}
                        <input type="search" data-cmdk-input class="w-full border-0 bg-transparent p-0 text-sm text-slate-900 placeholder-slate-500 focus:ring-0" placeholder="{{ $search ?? __('admin.search') }}" autocomplete="off" aria-label="{{ $search ?? __('admin.search') }}">
                        <kbd class="hidden rounded-md border border-slate-200 bg-white px-1.5 text-[10px] font-semibold text-slate-400 lg:inline" dir="ltr">⌘K</kbd>
                    </label>
                    <div class="absolute inset-x-0 top-full z-50 mt-2 hidden max-h-80 overflow-y-auto rounded-2xl border border-slate-200 bg-white p-1.5 shadow-lg" data-cmdk-results role="listbox"></div>
                </div>

                <div class="ms-auto flex items-center gap-2 sm:gap-3">
                    {{ $external ?? '' }}
                    @foreach ($locales as $l)
                        <a href="{{ $l['href'] }}" class="rounded-full px-2 py-1 text-xs font-bold uppercase text-slate-500 hover:bg-slate-100 hover:text-panel-text">{{ $l['name'] }}</a>
                    @endforeach
                    {{ $headerEnd ?? '' }}
                    @if ($user)
                        <div class="relative">
                            <button type="button" data-dropdown-toggle="user-menu" class="flex items-center gap-2 rounded-full p-1 pe-2 text-sm hover:bg-slate-100" aria-haspopup="menu">
                                <span class="grid size-8 place-items-center rounded-full bg-panel-soft text-sm font-bold text-panel-text">{{ $initial }}</span>
                                <span class="hidden max-w-32 truncate font-semibold text-slate-800 sm:inline">{{ $user->name }}</span>
                                {!! $ico('M6 9l6 6 6-6', 'size-4 text-slate-400') !!}
                            </button>
                            <div id="user-menu" data-dropdown role="menu" class="absolute end-0 z-50 mt-2 hidden w-56 rounded-2xl border border-slate-200 bg-white p-1.5 shadow-lg">
                                <p class="truncate px-3 pb-2 pt-1 text-xs text-slate-500">{{ $user->name }}</p>
                                {{ $userMenu ?? '' }}
                                <form method="POST" action="{{ $logout }}">@csrf
                                    <button class="flex w-full items-center gap-2 rounded-xl px-3 py-2 text-start text-sm font-semibold text-red-700 hover:bg-red-50" role="menuitem">
                                        {!! $ico('M15 4h3a2 2 0 012 2v12a2 2 0 01-2 2h-3M10 17l-5-5 5-5M5 12h11', 'size-4 rtl:-scale-x-100') !!}{{ __('admin.logout') }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endif
                </div>
            </header>

            <main class="flex-1 p-4 md:p-6 {{ isset($mobileNav) ? 'pb-24 md:pb-6' : '' }}">
                @if ($header && trim((string) $header) !== '')
                    {{ $header }}
                @elseif ($title)
                    <h1 class="mb-5 text-xl font-bold text-slate-900">{{ $title }}</h1>
                @endif
                {{ $slot }}
            </main>
            {{ $mobileNav ?? '' }}
        </div>
    </div>
</x-layouts.base>
