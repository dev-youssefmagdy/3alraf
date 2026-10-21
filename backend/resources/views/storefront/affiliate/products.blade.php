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
                    <img src="{{ route('storefront.affiliate.qr', $p->slug) }}" alt="QR" class="size-28" loading="lazy">
                    @if ($p->barcode)<div class="max-w-[9rem] overflow-hidden text-center">{!! app(\App\Services\BarcodeService::class)->svg($p->barcode, 50) !!}<span class="text-xs text-slate-500">{{ $p->barcode }}</span></div>@endif
                </div>
                <div class="mt-3 flex gap-2">
                    <input readonly value="{{ $tp->affiliate_url }}" class="form-control text-xs" onclick="this.select()">
                    <button type="button" class="btn-sm btn-secondary shrink-0" data-copy="{{ $tp->affiliate_url }}" data-copied="{{ __('growth.copied') }}">{{ __('growth.copy') }}</button>
                </div>
            </x-card>
        @empty
            <p class="text-slate-500">{{ __('admin.no_data') }}</p>
        @endforelse
    </div>
</x-layouts.storefront>
