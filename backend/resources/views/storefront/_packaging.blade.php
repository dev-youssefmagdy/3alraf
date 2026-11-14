@php
    $packOptions = \App\Models\PackagingOption::active()->orderBy('sort_order')->orderBy('id')->get();
    $packSelected = app(\App\Services\Orders\CartService::class)->packagingOptionId();
@endphp
@if ($packOptions->isNotEmpty())
    {{-- Packaging option (design 21:2019): the "special packaging" checkbox reveals the options, a `packaging` Swiper of gold-soft cards; radios stay native --}}
    <div class="space-y-3" data-packaging>
        <label class="flex min-h-11 cursor-pointer items-center gap-3 text-sm font-bold text-slate-950">
            <input type="checkbox" class="size-[22px] shrink-0 rounded-md border-slate-300 accent-brand-600" data-packaging-toggle @checked($packSelected)>
            {{ __('store.packaging') }}
        </label>
        <div class="{{ $packSelected ? '' : 'hidden' }}" data-packaging-picker role="radiogroup" aria-label="{{ __('store.packaging_choose') }}">
            <input type="radio" name="packaging_option_id" value="" class="sr-only" data-packaging-select tabindex="-1" aria-hidden="true" @checked(! $packSelected)>
            <section data-swiper="packaging" aria-label="{{ __('store.packaging_choose') }}">
                <div class="swiper"><div class="swiper-wrapper">
                    @foreach ($packOptions as $o)
                        <div class="swiper-slide">
                            <label class="relative flex h-full cursor-pointer flex-col gap-2 rounded-2xl border-2 border-slate-200 bg-white p-2 text-center transition hover:border-accent has-[:checked]:border-accent has-[:checked]:bg-accent-light has-[:focus-visible]:ring-[3px] has-[:focus-visible]:ring-pop-500/45" data-packaging-card="{{ $o->id }}">
                                <input type="radio" name="packaging_option_id" value="{{ $o->id }}" class="peer sr-only" data-packaging-select @checked($packSelected === $o->id)>
                                <span class="absolute start-3 top-3 z-[1] hidden size-6 place-items-center rounded-full bg-brand-600 text-white shadow-raised peer-checked:grid" aria-hidden="true">
                                    <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="m5 12 5 5L20 7"/></svg>
                                </span>
                                @if ($o->imageUrl())
                                    <img src="{{ $o->imageUrl() }}" alt="" loading="lazy" class="aspect-[4/3] w-full rounded-xl bg-mint-100 object-cover">
                                @else
                                    <span class="grid aspect-[4/3] w-full place-items-center rounded-xl bg-accent-light text-accent-dark" aria-hidden="true"><svg class="size-8" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M20 12v9H4v-9M2 7h20v5H2zM12 21V7M12 7H7.5a2.5 2.5 0 1 1 0-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 1 0 0-5C13 2 12 7 12 7z"/></svg></span>
                                @endif
                                <span class="line-clamp-2 text-sm font-bold leading-snug text-slate-950">{{ $o->name }}</span>
                                @if ($o->description)<span class="line-clamp-2 text-xs/[18px] text-slate-600">{{ $o->description }}</span>@endif
                                <span class="mt-auto text-xs font-bold text-accent-dark"><bdi dir="ltr">+ {{ \App\Support\Money::format((string) $o->price, setting('currency_code')) }}</bdi></span>
                            </label>
                        </div>
                    @endforeach
                </div></div>
            </section>
        </div>
    </div>
@endif
