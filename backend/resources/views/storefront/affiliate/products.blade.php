<x-layouts.affiliate :title="__('growth.my_links')">
    {{-- Affiliate products & links (design 30:12437 / 30:12728): nav → intro → link cards 1 / 2 / 3 columns (thumb, name, price, QR + download (no barcode),
         readonly URL + pop Copy, variant links accordion with their own QR + copy). --}}
    <x-slot:header><x-ui.page-header :title="__('growth.my_links')" :count="count($items)" :subtitle="__('growth.links_intro')" /></x-slot:header>
    @php
        $copyIcon = '<svg class="size-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 9h11v11H9zM5 15H4V4h11v1"/></svg>';
        $qrIcon = '<svg class="size-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v12M7 11l5 5 5-5M5 20h14"/></svg>';
    @endphp
    <div class="grid items-start gap-4 sm:grid-cols-2 lg:grid-cols-3 lg:gap-5">
        @forelse ($items as $tp)
            @php($p = $tp)
            @php($qr = $tp->qr_url ?? route('storefront.affiliate.qr', $p->slug))
            <x-ui.card class="!rounded-3xl"><article class="flex min-w-0 flex-col gap-4">
                <div class="flex items-center gap-3">
                    @if ($img = $p->primaryImageUrl())<img src="{{ $img }}" alt="" width="64" height="64" loading="lazy" decoding="async" class="size-16 shrink-0 rounded-2xl bg-panel-surface-sunken object-cover">@endif
                    <div class="min-w-0">
                        <h2 class="line-clamp-2 text-base/6 font-bold text-panel-text">{{ $p->name }}</h2>
                        <p class="mt-0.5 text-lg/6 font-bold tabular-nums text-panel-accent-text">{{ number_format((float) $tp->effectivePrice(), 2) }} <small class="font-sans text-sm font-bold">{{ setting('currency_symbol') }}</small></p>
                    </div>
                </div>
                {{-- the affiliate's QR only (no product barcode): it carries their tracked link --}}
                <a href="{{ $qr }}" download="qr-{{ $p->slug }}.png" class="flex flex-col items-center gap-2 rounded-2xl border border-panel-border bg-panel-surface-sunken p-3 text-sm font-bold text-panel-accent-text transition hover:border-panel-accent hover:bg-panel-surface">
                    <img src="{{ $qr }}" alt="QR: {{ $p->name }}" width="160" height="160" class="size-40 rounded-xl bg-panel-surface p-1.5" loading="lazy">
                    <span class="inline-flex items-center gap-1.5">{!! $qrIcon !!}{{ __('store.qr_download') }}</span>
                </a>
                <div class="flex gap-2">
                    <input readonly value="{{ $tp->affiliate_url }}" dir="ltr" aria-label="{{ __('growth.my_links') }}: {{ $p->name }}" class="form-control min-w-0 flex-1 bg-panel-surface-sunken text-xs" onclick="this.select()">
                    <x-ui.button class="shrink-0" icon="copy" data-copy="{{ $tp->affiliate_url }}" data-copied="{{ __('growth.copied') }}">{{ __('growth.copy') }}</x-ui.button>
                </div>
                @if (! empty($tp->variant_links))
                    <x-ui.accordion :title="__('growth.variant_links')">
                        <ul class="space-y-3">
                            @foreach ($tp->variant_links as $v)
                                @php($vu = $v['qr_url'] ?? route('storefront.affiliate.qr', [$p->slug, 'variant' => $v['id']]))
                                <li class="flex items-center gap-3">
                                    <a href="{{ $vu }}" download="qr-{{ $p->slug }}-{{ $v['id'] }}.png" class="shrink-0 rounded-xl border border-panel-border p-1 hover:border-panel-accent" aria-label="{{ __('store.qr_download') }}: {{ $v['label'] }}"><img src="{{ $vu }}" alt="QR" width="64" height="64" class="size-16" loading="lazy"></a>
                                    <div class="min-w-0 flex-1">
                                        <div class="truncate font-semibold text-panel-text">{{ $v['label'] }}</div>
                                        <x-ui.button variant="secondary" size="sm" icon="copy" class="mt-1" data-copy="{{ $v['link'] }}" data-copied="{{ __('growth.copied') }}">{{ __('growth.copy') }}</x-ui.button>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </x-ui.accordion>
                @endif
            </article></x-ui.card>
        @empty
            <x-ui.empty-state kind="nodata" :title="__('admin.no_data')" message="" class="sm:col-span-2 lg:col-span-3" />
        @endforelse
    </div>
</x-layouts.affiliate>
