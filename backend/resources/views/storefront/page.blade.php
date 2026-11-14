@php($bc = [[__('store.breadcrumb_home'), route('storefront.home')], [$page->title, url()->current()]])
<x-layouts.storefront :title="$page->title" :description="\Illuminate\Support\Str::limit(strip_tags((string) $page->content), 160, '')" :breadcrumbs="$bc">
    {{-- Content page (design 28:9674 / 28:10039): breadcrumbs → article panel (sun sparkle + H1 + text), 880 wide on desktop.
         The content is admin text: it stays escaped (e() + nl2br); any markup in it shows as text and long words wrap. --}}
    <x-breadcrumbs :items="$bc" />
    <article class="max-w-[880px] rounded-3xl border border-slate-200 bg-white p-5 sm:p-6 lg:rounded-[32px] lg:p-10">
        <header class="flex items-start gap-3 lg:gap-4">
            <span class="grid size-11 shrink-0 place-items-center rounded-full bg-sun-400 text-slate-950 lg:size-12" aria-hidden="true">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9zM19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z"/></svg>
            </span>
            <h1 class="min-w-0 font-display text-[28px]/9 font-extrabold text-slate-950 [overflow-wrap:anywhere] lg:text-[32px]/[40px]">{{ $page->title }}</h1>
        </header>
        <div class="sf-prose mt-4 lg:mt-5">{!! nl2br(e($page->content)) !!}</div>
    </article>
</x-layouts.storefront>
