{{-- Marketer panel shell (/marketer, guard `admin`). Same base, components and styles as <x-layouts.admin>; own nav (App\Support\Marketing\MarketerNav) and accent. --}}
@props(['title' => ''])
@php
    $user = auth('admin')->user();
    $groups = \App\Support\Marketing\MarketerNav::visible($user);
    $m = fn ($k) => __('marketing.'.$k);
    $isActive = fn ($i) => $i[4] ? request()->routeIs(...(array) $i[4]) : request()->routeIs($i[0]);
    $chev = '<svg class="size-4 shrink-0 transition-transform group-open:rotate-90 rtl:group-open:-rotate-90 rtl:-scale-x-100" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 6l6 6-6 6"/></svg>';
@endphp
<x-layouts.base :title="$title ?: $m('panel')" :siteName="$m('panel')">
    <div class="flex min-h-screen" data-panel="marketer">
        <aside id="sidebar" class="fixed inset-y-0 start-0 z-40 hidden w-64 shrink-0 flex-col bg-brand-900 p-4 md:sticky md:top-0 md:h-screen md:flex">
            <a href="{{ route('marketer.dashboard') }}" class="mb-1 flex items-center gap-2 px-2 text-lg font-bold text-white">
                <img src="{{ asset('logo-sm.webp') }}" alt="{{ setting('name') ?: config('app.name') }}" class="h-10 w-auto max-w-full object-contain">
            </a>
            <p class="mb-5 px-2 text-xs font-semibold uppercase tracking-wide text-accent">{{ $m('panel') }}</p>
            <nav class="-me-2 flex-1 space-y-1 overflow-y-auto pe-2" aria-label="{{ $m('panel') }}">
                @foreach ($groups as $g)
                    @php $open = collect($g['items'])->contains(fn ($i) => $isActive($i)); @endphp
                    @if (count($g['items']) === 1 && ! ($g['external'] ?? false))
                        @php $i = $g['items'][0]; @endphp
                        <a href="{{ route($i[0]) }}" class="side-link {{ $isActive($i) ? 'active' : '' }}">
                            <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $i[3] ?? $g['icon'] }}"/></svg>
                            {{ $m($i[1]) }}
                        </a>
                    @else
                        <details class="group" @if ($open || $loop->first) open @endif>
                            <summary class="side-link cursor-pointer select-none list-none justify-between [&::-webkit-details-marker]:hidden {{ $open ? '!text-white' : '' }}">
                                <span class="flex items-center gap-3">
                                    <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $g['icon'] }}"/></svg>
                                    {{ $m('groups.'.$g['key']) }}
                                </span>
                                {!! $chev !!}
                            </summary>
                            <div class="ms-5 mt-1 space-y-0.5 border-s border-white/10 ps-2">
                                @foreach ($g['items'] as $i)
                                    <a href="{{ route($i[0]) }}" class="side-link !py-1.5 !text-[13px] {{ $isActive($i) ? 'active' : '' }}" @if ($g['external'] ?? false) data-admin-link @endif>
                                        {{ $m($i[1]) }}
                                        @if ($g['external'] ?? false)<svg class="ms-auto size-3.5 opacity-60 rtl:-scale-x-100" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 01-1 1H5a1 1 0 01-1-1V7a1 1 0 011-1h5"/></svg>@endif
                                    </a>
                                @endforeach
                            </div>
                        </details>
                    @endif
                @endforeach
            </nav>
            @if (\App\Support\Marketing\MarketerNav::hasAdminAccess($user))
                <a href="{{ route('admin.welcome') }}" class="side-link mt-3 border-t border-white/10 pt-3" data-admin-panel-link>
                    <svg class="size-5 rtl:-scale-x-100" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7 7-7M3 12h18"/></svg>
                    {{ $m('admin_panel') }}
                </a>
            @endif
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-30 flex items-center gap-3 border-b border-slate-200 border-t-4 border-t-accent bg-white px-4 py-3">
                <button type="button" data-sidebar-toggle class="md:hidden" aria-label="Menu">
                    <svg class="size-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <span class="rounded-full bg-brand-50 px-3 py-1 text-sm font-bold text-brand-700">{{ $m('panel') }}</span>

                <div class="ms-auto flex items-center gap-3">
                    @if (\App\Support\Marketing\MarketerNav::hasAdminAccess($user))
                        <a href="{{ route('admin.welcome') }}" class="hidden text-sm font-medium text-slate-600 hover:text-brand-600 sm:inline">{{ $m('admin_panel') }}</a>
                    @endif
                    @foreach (\App\Models\Language::activeList() as $l)
                        @continue($l->code === app()->getLocale())
                        <a href="{{ route('admin.locale', $l->code) }}" class="text-xs font-semibold uppercase text-slate-500 hover:text-brand-600">{{ $l->native_name }}</a>
                    @endforeach
                    <div class="flex items-center gap-2 text-sm">
                        <span class="hidden text-slate-600 sm:inline">{{ $user?->name }}</span>
                        <form method="POST" action="{{ route('admin.logout') }}">@csrf
                            <button class="btn-sm btn-secondary">{{ __('admin.logout') }}</button>
                        </form>
                    </div>
                </div>
            </header>

            <main class="flex-1 p-4 md:p-6">
                @if ($title)<h1 class="mb-5 text-xl font-bold text-slate-800">{{ $title }}</h1>@endif
                {{ $slot }}
            </main>
        </div>
    </div>
</x-layouts.base>
