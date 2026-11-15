@php
    $postDesc = $post->meta_description ?: ($post->excerpt ?: \Illuminate\Support\Str::limit(\App\Support\SafeHtml::text($post->content), 160, ''));
    $ld = ['@context' => 'https://schema.org', '@type' => 'BlogPosting', 'headline' => \Illuminate\Support\Str::limit($post->title, 110, ''), 'description' => $postDesc, 'mainEntityOfPage' => url()->current(),
        'datePublished' => ($post->published_at ?? $post->created_at)?->toIso8601String(), 'dateModified' => $post->updated_at?->toIso8601String(), 'inLanguage' => app()->getLocale(),
        'author' => ['@type' => 'Organization', 'name' => setting('name')], 'publisher' => ['@type' => 'Organization', 'name' => setting('name'), 'logo' => ['@type' => 'ImageObject', 'url' => asset('logo-512.png')]],
        'image' => [asset('logo-512.png')]];
    $bc = [[__('store.breadcrumb_home'), route('storefront.home')], [__('growth.blog'), route('storefront.blog.index')], [$post->title, url()->current()]];
@endphp
<x-layouts.storefront :title="$post->title" :description="$postDesc" :jsonld="$ld" :breadcrumbs="$bc" type="article">
    {{-- Blog post (design 28:10973 / 28:11184): breadcrumbs → pop cover band → H1, date → rich text → back to blog + share → more posts (Swiper "posts"). Centred 760 column. --}}
    @php($date = $post->published_at ?? $post->created_at)
    <article class="mx-auto max-w-[760px]">
        <x-breadcrumbs :items="$bc" class="lg:[&_ol]:mx-auto" />
        <div class="relative isolate grid h-[180px] place-items-center overflow-hidden rounded-3xl bg-pop-600 text-sun-400 lg:h-[300px] lg:rounded-[32px]" aria-hidden="true">
            <span class="absolute -start-10 -top-10 -z-10 size-36 rounded-full bg-pop-500 lg:size-56"></span>
            <span class="absolute -bottom-8 end-8 -z-10 size-20 rounded-full bg-brand-800/40 lg:size-32"></span>
            <svg class="size-16 lg:size-24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M20 12v9H4v-9M2 7h20v5H2zM12 21V7M12 7H7.5a2.5 2.5 0 1 1 0-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 1 0 0-5C13 2 12 7 12 7z"/></svg>
        </div>
        <h1 class="mt-5 font-display text-[28px]/9 font-extrabold text-slate-950 [overflow-wrap:anywhere] lg:mt-6 lg:text-[44px]/[52px]">{{ $post->title }}</h1>
        @if ($date)
            <p class="mt-3 flex items-center gap-1.5 text-sm text-slate-600">
                <svg class="size-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM12 7v5l3 2"/></svg>
                <time datetime="{{ $date->toIso8601String() }}">{{ $date->format('Y-m-d') }}</time>
            </p>
        @endif
        <div class="sf-prose mt-5">{!! \App\Support\Marketing\RichText::render($post->content) !!}</div>
        <div class="mt-8 flex items-center justify-between gap-3 border-t border-slate-200 pt-5">
            <a href="{{ route('storefront.blog.index') }}" class="btn btn-secondary inline-flex items-center gap-1.5">
                <svg class="size-4 shrink-0 ltr:-scale-x-100" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>{{ __('store.blog_back') }}
            </a>
            <button type="button" data-share-url="{{ url()->current() }}" data-share-title="{{ $post->title }}" class="icon-btn icon-btn-outline" aria-label="{{ __('store.share') }}">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M18 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM6 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM18 22a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM8.6 13.5l6.8 4M15.4 6.5l-6.8 4"/></svg>
            </button>
        </div>
    </article>
    @if (($related ?? collect())->isNotEmpty())
        <section data-swiper="posts" data-swiper-bleed class="mt-10 lg:mt-14" aria-label="{{ __('store.blog_related') }}">
            <x-slider-head :title="__('store.blog_related')" :href="route('storefront.blog.index')" />
            <div class="swiper !py-1 max-lg:-mx-4">
                <div class="swiper-wrapper">
                    @foreach ($related as $rp)<div class="swiper-slide">@include('storefront.blog._card', ['post' => $rp, 'heading' => 'h3'])</div>@endforeach
                </div>
            </div>
            <div data-swiper-pagination class="mx-auto mt-4 w-32"></div>
        </section>
    @endif
</x-layouts.storefront>
