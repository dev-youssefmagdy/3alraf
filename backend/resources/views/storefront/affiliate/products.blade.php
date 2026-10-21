<x-layouts.storefront :title="__('growth.my_links')">
    @include('storefront.affiliate._nav')
    <p class="mb-4 text-sm text-slate-500">{{ __('growth.links_intro') }}</p>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($items as $tp)
            @php($p = $tp->product)
            <x-card>
                <div class="flex gap-3">
                    @if ($img = $p->primaryImageUrl())<img src="{{ $img }}" alt="" class="size-16 rounded-lg object-cover">@endif
                    <div class="min-w-0"><h3 class="truncate font-semibold">{{ $p->name }}</h3><p class="text-sm text-slate-500">{{ number_format((float) $tp->effectivePrice(), 2) }} {{ current_tenant()->currency_symbol }}</p></div>
                </div>
                <div class="mt-3 flex items-center justify-center gap-4">
                    <a href="{{ $tp->qr_url ?? route('storefront.affiliate.qr', $p->slug) }}" download="qr-{{ $p->slug }}.png"><img src="{{ $tp->qr_url ?? route('storefront.affiliate.qr', $p->slug) }}" alt="QR" class="size-28" loading="lazy"></a>
                    @if ($p->barcode)<div class="max-w-[9rem] overflow-hidden text-center">{!! app(\App\Services\BarcodeService::class)->svg($p->barcode, 50) !!}<span class="text-xs text-slate-500">{{ $p->barcode }}</span></div>@endif
                </div>
                <div class="mt-3 flex gap-2">
                    <input readonly value="{{ $tp->affiliate_url }}" class="form-control text-xs" onclick="this.select()">
                    <button type="button" class="btn-sm btn-secondary shrink-0" data-copy="{{ $tp->affiliate_url }}" data-copied="{{ __('growth.copied') }}">{{ __('growth.copy') }}</button>
                </div>
                @if (! empty($tp->variant_links))
                    <details class="mt-3 text-sm">
                        <summary class="cursor-pointer font-semibold">{{ __('growth.variant_links') }}</summary>
                        <ul class="mt-2 space-y-2">
                            @foreach ($tp->variant_links as $v)
                                @php($vu = $v['qr_url'] ?? route('storefront.affiliate.qr', [$p->slug, 'variant' => $v['id']]))
                                <li class="flex items-center gap-3">
                                    <a href="{{ $vu }}" download="qr-{{ $p->slug }}-{{ $v['id'] }}.png"><img src="{{ $vu }}" alt="QR" class="size-16" loading="lazy"></a>
                                    <div class="min-w-0 flex-1"><div class="truncate">{{ $v['label'] }}</div><button type="button" class="btn-sm btn-secondary mt-1" data-copy="{{ $v['link'] }}" data-copied="{{ __('growth.copied') }}">{{ __('growth.copy') }}</button></div>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            </x-card>
        @empty
            <p class="text-slate-500">{{ __('admin.no_data') }}</p>
        @endforelse
    </div>
</x-layouts.storefront>
