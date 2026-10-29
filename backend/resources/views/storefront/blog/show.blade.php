@php
    $postDesc = $post->meta_description ?: ($post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags((string) $post->content), 160, ''));
    $ld = ['@context' => 'https://schema.org', '@type' => 'BlogPosting', 'headline' => \Illuminate\Support\Str::limit($post->title, 110, ''), 'description' => $postDesc, 'mainEntityOfPage' => url()->current(),
        'datePublished' => ($post->published_at ?? $post->created_at)?->toIso8601String(), 'dateModified' => $post->updated_at?->toIso8601String(), 'inLanguage' => app()->getLocale(),
        'author' => ['@type' => 'Organization', 'name' => current_tenant()->name], 'publisher' => ['@type' => 'Organization', 'name' => current_tenant()->name, 'logo' => ['@type' => 'ImageObject', 'url' => asset('logo-512.png')]],
        'image' => [asset('logo-512.png')]];
    $bc = [[__('store.breadcrumb_home'), route('storefront.home')], [__('growth.blog'), route('storefront.blog.index')], [$post->title, url()->current()]];
@endphp
<x-layouts.storefront :title="$post->title" :description="$postDesc" :jsonld="$ld" :breadcrumbs="$bc" type="article">
    <article class="mx-auto max-w-3xl">
        <h1 class="mb-1 text-2xl font-bold">{{ $post->title }}</h1>
        <p class="mb-4 text-xs text-slate-400">{{ $post->published_at?->format('Y-m-d') }}</p>
        <div class="space-y-3 text-slate-700">{!! \App\Support\SafeHtml::render($post->content) !!}</div>
    </article>
</x-layouts.storefront>
