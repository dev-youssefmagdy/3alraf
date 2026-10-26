<x-layouts.storefront :title="$post->title">
    <article class="mx-auto max-w-3xl">
        <h1 class="mb-1 text-2xl font-bold">{{ $post->title }}</h1>
        <p class="mb-4 text-xs text-slate-400">{{ $post->published_at?->format('Y-m-d') }}</p>
        <div class="space-y-3 text-slate-700">{!! \App\Support\SafeHtml::render($post->content) !!}</div>
    </article>
</x-layouts.storefront>
