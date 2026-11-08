{{-- Rich preview per type. Every text is escaped; HTML only through RichText (allowlist, internal links only); no external resource is loaded. --}}
@php
    $type = $s->type->value;
    $locales = \App\Support\Marketing\StoreText::locales();
@endphp
<div data-preview="{{ $type }}">
@switch($type)
    @case('blog')
        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($locales as $l)
                @if (! empty($p['title'][$l]) || ! empty($p['content'][$l]))
                    <article class="rounded-lg border border-slate-200 p-4" dir="{{ $l === 'ar' ? 'rtl' : 'ltr' }}" lang="{{ $l }}" data-article="{{ $l }}">
                        <h2 class="text-lg font-bold text-slate-800">{{ $p['title'][$l] ?? '' }}</h2>
                        @if (! empty($p['excerpt'][$l]))<p class="mt-1 text-sm text-slate-500">{{ $p['excerpt'][$l] }}</p>@endif
                        <div class="mt-3 space-y-2 text-sm leading-7 text-slate-700 [&_a]:text-brand-700 [&_a]:underline [&_h2]:font-bold [&_h3]:font-semibold [&_li]:ms-5 [&_ul]:list-disc">{!! \App\Support\Marketing\RichText::render($p['content'][$l] ?? '') !!}</div>
                    </article>
                @endif
            @endforeach
        </div>
        <div class="mt-3 grid gap-3 text-sm md:grid-cols-3">
            @if (! empty($p['outline']))<div><b>{{ $t('blog.outline') }}</b><ol class="ms-5 list-decimal">@foreach ($p['outline'] as $o)<li>{{ $o }}</li>@endforeach</ol></div>@endif
            @if (! empty($p['keywords']))<div><b>{{ $t('blog.keywords') }}</b><p>{{ implode('، ', $p['keywords']) }}</p></div>@endif
            @if (! empty($p['internal_links']))<div><b>{{ $t('blog.links') }}</b><ul>@foreach ($p['internal_links'] as $lnk)<li><code dir="ltr">{{ $lnk['url'] }}</code> {{ $lnk['anchor'] ?? '' }}</li>@endforeach</ul></div>@endif
        </div>
        @break
    @case('seasonal_theme')
        <div class="grid gap-4 lg:grid-cols-2">
            <div>
                <p class="mb-2 text-sm font-semibold text-slate-700">{{ $t('themes.swatches') }}</p>
                <div class="grid grid-cols-4 gap-2 sm:grid-cols-6" data-swatches>
                    @foreach ($palette as $k => $hex)
                        <div class="text-center text-[10px] {{ isset($p['tokens'][$k]) ? 'font-bold' : 'opacity-70' }}">
                            <div class="h-8 rounded border border-slate-200" style="background: {{ $hex }}"></div>
                            <span dir="ltr" class="block truncate">{{ str_replace('color-', '', $k) }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-2 rounded-lg p-3" style="background: {{ $palette['color-slate-50'] ?? '#fff' }}">
                    <span class="rounded-lg px-4 py-2 text-sm font-semibold text-white" style="background: {{ $palette['color-brand-600'] ?? '#000' }}">{{ $t('btn.apply') }}</span>
                    <span class="rounded-full px-2 py-0.5 text-xs" style="background: {{ $palette['color-brand-50'] ?? '#eee' }}; color: {{ $palette['color-brand-700'] ?? '#000' }}">badge</span>
                    <span class="text-sm" style="color: {{ $palette['color-slate-800'] ?? '#000' }}">Aa نص</span>
                    <span class="rounded px-2 py-1 text-xs text-white" style="background: {{ $palette['color-brand-800'] ?? '#000' }}">promo</span>
                </div>
                @foreach (['design_direction', 'decorations', 'typography', 'header_footer', 'product_cards', 'slider'] as $k)
                    @if (! empty($p[$k]))<p class="mt-2 text-sm"><b>{{ $t('fields.'.$k) }}:</b> {{ $p[$k] }}</p>@endif
                @endforeach
            </div>
            <div>
                <p class="mb-2 text-sm font-semibold text-slate-700">{{ $t('themes.contrast') }}</p>
                <table class="w-full text-sm" data-contrast>
                    <thead><tr class="text-slate-500"><th class="text-start">{{ $t('themes.pair') }}</th><th class="text-start">{{ $t('themes.ratio') }}</th><th class="text-start">{{ $t('themes.min') }}</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($contrast as $c)
                        <tr class="border-t border-slate-100" data-pair="{{ $c['pair'] }}" data-ok="{{ $c['ok'] ? 1 : 0 }}">
                            <td class="py-1"><span class="me-1 inline-block rounded px-1.5 text-xs" style="background: {{ $c['background'] }}; color: {{ $c['foreground'] }}">Aa</span>{{ $t('contrast_pairs.'.$c['pair']) }}</td>
                            <td>{{ $c['ratio'] }}</td><td>{{ $c['min'] }}</td>
                            <td><span class="badge {{ $c['ok'] ? 'badge-success' : 'badge-danger' }}">{{ $c['ok'] ? $t('themes.ok') : $t('themes.low') }}</span></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @break
    @case('seo')
        <div class="grid gap-3 md:grid-cols-2">
            @foreach ($locales as $l)
                @if (! empty($p['meta_title'][$l]) || ! empty($p['meta_description'][$l]))
                    <div class="rounded-lg border border-slate-200 p-3" dir="{{ $l === 'ar' ? 'rtl' : 'ltr' }}" data-serp="{{ $l }}">
                        <p class="truncate text-xs text-emerald-700" dir="ltr">{{ url('/'.$l) }}/… {{ $p['slug'] ?? '' }}</p>
                        <p class="text-base text-blue-800">{{ $p['meta_title'][$l] ?? '—' }}</p>
                        <p class="text-sm text-slate-600">{{ $p['meta_description'][$l] ?? '—' }}</p>
                    </div>
                @endif
            @endforeach
        </div>
        @if (! empty($p['notes']))
            <dl class="mt-3 grid gap-2 text-sm md:grid-cols-2">
                @foreach ($p['notes'] as $k => $v)
                    <div><dt class="font-semibold text-slate-600">{{ $t('fields.'.$k) }}</dt><dd>@include('marketer.suggestions._value', ['value' => $v])</dd></div>
                @endforeach
            </dl>
        @endif
        @break
    @case('slider')
        <div class="rounded-xl bg-gradient-to-l from-brand-700 to-brand-500 p-6 text-white" data-banner>
            <p class="text-lg font-bold">{{ $loc($p['title'] ?? null) ?: '—' }}</p>
            <p class="mt-1 text-xs opacity-80" dir="ltr">{{ $p['link_url'] ?? '' }}</p>
            @if (! empty($p['design_notes']))<p class="mt-2 text-sm opacity-90">{{ $p['design_notes'] }}</p>@endif
        </div>
        @break
    @case('product')
        <div class="rounded-lg border border-slate-200 p-3">
            <p class="font-semibold">{{ $loc($p['name'] ?? null) ?: ($target?->name ?? '') }}</p>
            @if (! empty($p['description']))<div class="mt-2 text-sm text-slate-700">{!! \App\Support\Marketing\RichText::render($loc($p['description'])) !!}</div>@endif
        </div>
        @break
    @case('social_content')
        <div class="max-w-md rounded-xl border border-slate-200 p-3" data-social>
            <p class="text-xs text-slate-500">{{ $p['platform'] ?? '' }} · {{ $p['format'] ?? '' }}</p>
            <p class="mt-1 whitespace-pre-line text-sm">{{ $loc($p['caption'] ?? null) }}</p>
            <p class="mt-1 text-sm text-brand-700">{{ implode(' ', $p['hashtags'] ?? []) }}</p>
        </div>
        @break
    @default
        @include('marketer.suggestions._value', ['value' => $p])
@endswitch
</div>
