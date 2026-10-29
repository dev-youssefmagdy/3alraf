@php($bc = [[__('store.breadcrumb_home'), route('storefront.home')], [$page->title, url()->current()]])
<x-layouts.storefront :title="$page->title" :description="\Illuminate\Support\Str::limit(strip_tags((string) $page->content), 160, '')" :breadcrumbs="$bc">
    <article class="mx-auto max-w-3xl">
        <h1 class="mb-4 text-2xl font-bold">{{ $page->title }}</h1>
        <div class="space-y-3 text-slate-700">{!! nl2br(e($page->content)) !!}</div>
    </article>
</x-layouts.storefront>
