{{-- Blog post card (design 28:10480), @include with ['post' => …, 'heading' => 'h2'|'h3']: tinted cover band with an icon (tone picked from the post id, so a post keeps its colour everywhere), date, title, excerpt, "read more". --}}
@php
    $heading ??= 'h2';
    $tones = [
        ['bg-pop-50 text-pop-600', 'gift'], ['bg-sun-100 text-brand-600', 'heart'], ['bg-violet-100 text-violet-700', 'sparkle'],
        ['bg-brand-50 text-pop-600', 'gift'], ['bg-mint-100 text-mint-700', 'heart'], ['bg-accent-light text-violet-700', 'sparkle'],
    ];
    $icons = [
        'gift' => 'M20 12v9H4v-9M2 7h20v5H2zM12 21V7M12 7H7.5a2.5 2.5 0 1 1 0-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 1 0 0-5C13 2 12 7 12 7z',
        'heart' => 'M12 20s-7-4.4-9-9a5 5 0 0 1 9-3 5 5 0 0 1 9 3c-2 4.6-9 9-9 9z',
        'sparkle' => 'M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9zM19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z',
    ];
    [$tone, $icon] = $tones[$post->id % count($tones)];
    $url = route('storefront.blog.show', ['locale' => app()->getLocale(), 'slug' => $post->slug]);
    $excerpt = $post->excerpt ?: \Illuminate\Support\Str::limit(\App\Support\SafeHtml::text(str_replace('<', ' <', (string) $post->content)), 140);
    $date = $post->published_at ?? $post->created_at;
@endphp
<article class="group relative flex h-full flex-col gap-2 rounded-3xl border border-slate-200 bg-white p-4 transition duration-150 hover:shadow-raised motion-reduce:transition-none">
    <div class="grid h-[120px] place-items-center rounded-2xl lg:h-[144px] {{ $tone }}" aria-hidden="true">
        <svg class="size-11" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ $icons[$icon] }}"/></svg>
    </div>
    @if ($date)
        <p class="mt-1 flex items-center gap-1.5 text-xs/[18px] text-slate-600">
            <svg class="size-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM12 7v5l3 2"/></svg>
            <time datetime="{{ $date->toDateString() }}">{{ $date->format('Y-m-d') }}</time>
        </p>
    @endif
    <{{ $heading }} class="text-lg/7 font-bold text-slate-950 [overflow-wrap:anywhere]">
        <a href="{{ $url }}" class="rounded-md after:absolute after:inset-0 after:rounded-3xl focus-visible:outline-none focus-visible:after:ring-[3px] focus-visible:after:ring-pop-500/45 group-hover:text-brand-600">{{ $post->title }}</a>
    </{{ $heading }}>
    @if ($excerpt !== '')<p class="line-clamp-3 text-sm/[22px] text-slate-600 [overflow-wrap:anywhere]">{{ $excerpt }}</p>@endif
    <span class="mt-auto inline-flex items-center gap-1 pt-1 text-sm font-bold text-brand-600" aria-hidden="true">{{ __('growth.read_more') }}<svg class="size-4 shrink-0 rtl:-scale-x-100" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="m9 6 6 6-6 6"/></svg></span>
</article>
