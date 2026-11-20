{{--
    Panel app shell (Figma AppShell): sidebar (expanded 264 / rail 72 / drawer 300) + top bar + content + bottom nav + command palette + toasts, one markup for the three panels.
    Colours come from <html data-panel> (resources/css/panel.css). No link here crosses panels: every URL is passed in by the layout.

    nav: list of ['label', 'href', 'icon' (Icons name or svg path), 'active', 'badge', 'route'?] or groups ['group' => true, 'label', 'icon', 'items' => [links...]]
    bottom: bottom-nav items (see panel/bottom-nav); quick: command-palette quick actions [['label','href','icon']]; locales: [['code','name','href']]
--}}
@props([
    'panel', 'title' => '', 'panelName', 'home', 'logout', 'nav' => [], 'locales' => [], 'user' => null,
    'search' => null, 'lite' => false, 'header' => null, 'bottom' => [], 'quick' => [],
])
@php
    $flat = collect($nav)->flatMap(fn ($n) => ($n['group'] ?? false) ? collect($n['items'])->map(fn ($i) => $i + ['group' => $n['label']]) : [$n + ['group' => null]])->values();
    $svg = fn ($i) => \App\Support\Ui\Icons::markup($i);
    $pages = $flat->map(fn ($i) => ['label' => $i['label'], 'href' => $i['href'], 'svg' => $svg($i['icon']), 'hint' => $i['group']])->all();
    $quick = array_map(fn ($q) => ['label' => $q['label'], 'href' => $q['href'], 'svg' => $svg($q['icon'])], $quick);
    $groups = array_values(array_filter([
        $quick ? ['label' => __('ui.cmdk.quick'), 'items' => $quick] : null,
        ['label' => __('ui.cmdk.pages'), 'items' => $pages],
    ]));
@endphp
<x-layouts.base :title="$title ?: $panelName" :siteName="$panelName" :panel="$panel" :lite="$lite" bodyClass="bg-panel-bg">
    <a href="#main" class="sr-only z-[90] rounded-lg bg-panel-surface px-4 py-2 font-bold text-panel-accent-text focus:not-sr-only focus:fixed focus:start-3 focus:top-3">{{ __('ui.skip') }}</a>
    <div class="flex min-h-screen" data-shell>
        <x-panel.sidebar :nav="$nav" :panelName="$panelName" :home="$home" :logout="$logout" :user="$user">
            <x-slot:sidebarFooter>{{ $sidebarFooter ?? '' }}</x-slot:sidebarFooter>
        </x-panel.sidebar>
        <div class="fixed inset-0 z-40 hidden bg-panel-overlay/50 md:hidden" data-sidebar-backdrop></div>

        <div class="flex min-w-0 flex-1 flex-col">
            <x-panel.topbar :panelName="$panelName" :home="$home" :logout="$logout" :locales="$locales" :user="$user" :search="$search">
                <x-slot:external>{{ $external ?? '' }}</x-slot:external>
                <x-slot:headerEnd>{{ $headerEnd ?? '' }}</x-slot:headerEnd>
                <x-slot:userMenu>{{ $userMenu ?? '' }}</x-slot:userMenu>
            </x-panel.topbar>

            <main id="main" class="flex-1 px-4 py-4 md:p-6 {{ $bottom ? 'pb-[calc(var(--size-bottom-nav)+2rem)] md:pb-6' : '' }}" tabindex="-1">
                <div class="mx-auto w-full max-w-[var(--size-content-max)]">
                    @if ($header && trim((string) $header) !== '')
                        {{ $header }}
                    @elseif ($title)
                        <h1 class="mb-5 text-xl font-bold text-panel-text md:text-panel-h1">{{ $title }}</h1>
                    @endif
                    {{ $slot }}
                </div>
            </main>
            @if ($bottom)<x-panel.bottom-nav :items="$bottom" :label="$panelName" />@endif
        </div>
    </div>
    <x-ui.command-palette :groups="$groups" :placeholder="$search" />
    <x-ui.toast-region />
</x-layouts.base>
