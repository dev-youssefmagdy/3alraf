<x-layouts.storefront :title="__('growth.my_links')" :noindex="true">
    {{-- Affiliate products & links (design 30:12437 / 30:12728): nav → intro → link cards 1 / 2 / 3 columns (thumb, name, price, QR + download (no barcode),
         readonly URL + pop Copy, variant links accordion with their own QR + copy). --}}
    @include('storefront.affiliate._nav')
    <h1 class="sr-only">{{ __('growth.my_links') }}</h1>
    <p class="mb-5 max-w-3xl text-base/[26px] text-slate-600">{{ __('growth.links_intro') }}</p>
    @php
        $copyIcon = '<svg class="size-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 9h11v11H9zM5 15H4V4h11v1"/></svg>';
        $qrIcon = '<svg class="size-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v12M7 11l5 5 5-5M5 20h14"/></svg>';
    @endphp
    <div class="grid items-start gap-4 sm:grid-cols-2 lg:grid-cols-3 lg:gap-5">
        @forelse ($items as $tp)
            @php($p = $tp)
            @php($qr = $tp->qr_url ?? route('storefront.affiliate.qr', $p->slug))
            <article class="flex min-w-0 flex-col gap-4 rounded-3xl border border-slate-200 bg-white p-4 sm:p-5">
                <div class="flex items-center gap-3">
                    @if ($img = $p->primaryImageUrl())<img src="{{ $img }}" alt="" width="64" height="64" loading="lazy" decoding="async" class="size-16 shrink-0 rounded-2xl bg-slate-100 object-cover">@endif
                    <div class="min-w-0">
                        <h2 class="line-clamp-2 text-base/6 font-bold text-slate-950">{{ $p->name }}</h2>
                        <p class="price mt-0.5 text-lg/6 !text-brand-600">{{ number_format((float) $tp->effectivePrice(), 2) }} <small class="font-sans text-sm font-bold">{{ setting('currency_symbol') }}</small></p>
                    </div>
                </div>
                {{-- the affiliate's QR only (no product barcode): it carries their tracked link --}}
                <a href="{{ $qr }}" download="qr-{{ $p->slug }}.png" class="flex flex-col items-center gap-2 rounded-2xl border border-slate-200 bg-slate-50 p-3 text-sm font-bold text-brand-600 transition hover:border-brand-500 hover:bg-white">
                    <img src="{{ $qr }}" alt="QR: {{ $p->name }}" width="160" height="160" class="size-40 rounded-xl bg-white p-1.5" loading="lazy">
                    <span class="inline-flex items-center gap-1.5">{!! $qrIcon !!}{{ __('store.qr_download') }}</span>
                </a>
                <div class="flex gap-2">
                    <input readonly value="{{ $tp->affiliate_url }}" dir="ltr" aria-label="{{ __('growth.my_links') }}: {{ $p->name }}" class="form-control min-w-0 flex-1 bg-slate-50 text-xs" onclick="this.select()">
                    <button type="button" class="btn btn-pop inline-flex shrink-0 items-center gap-1.5 !px-4" data-copy="{{ $tp->affiliate_url }}" data-copied="{{ __('growth.copied') }}">{!! $copyIcon !!}{{ __('growth.copy') }}</button>
                </div>
                @if (! empty($tp->variant_links))
                    <details class="group rounded-2xl border border-slate-200 text-sm">
                        <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between gap-3 px-4 font-bold text-slate-950 [&::-webkit-details-marker]:hidden">
                            {{ __('growth.variant_links') }}
                            <svg class="size-5 shrink-0 text-pop-600 group-open:hidden" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                            <svg class="hidden size-5 shrink-0 text-pop-600 group-open:block" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14"/></svg>
                        </summary>
                        <ul class="space-y-3 border-t border-slate-200 p-3">
                            @foreach ($tp->variant_links as $v)
                                @php($vu = $v['qr_url'] ?? route('storefront.affiliate.qr', [$p->slug, 'variant' => $v['id']]))
                                <li class="flex items-center gap-3">
                                    <a href="{{ $vu }}" download="qr-{{ $p->slug }}-{{ $v['id'] }}.png" class="shrink-0 rounded-xl border border-slate-200 p-1 hover:border-brand-500" aria-label="{{ __('store.qr_download') }}: {{ $v['label'] }}"><img src="{{ $vu }}" alt="QR" width="64" height="64" class="size-16" loading="lazy"></a>
                                    <div class="min-w-0 flex-1">
                                        <div class="truncate font-semibold text-slate-950">{{ $v['label'] }}</div>
                                        <button type="button" class="btn btn-secondary mt-1 inline-flex items-center gap-1.5 !px-4 !text-xs" data-copy="{{ $v['link'] }}" data-copied="{{ __('growth.copied') }}">{!! $copyIcon !!}{{ __('growth.copy') }}</button>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            </article>
        @empty
            <x-empty-state type="search" :title="__('admin.no_data')" />
        @endforelse
    </div>
</x-layouts.storefront>
