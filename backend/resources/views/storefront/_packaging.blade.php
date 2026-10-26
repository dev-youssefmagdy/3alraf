@php
    $packOptions = \App\Models\PackagingOption::active()->orderBy('sort_order')->orderBy('id')->get();
    $packSelected = app(\App\Services\Orders\CartService::class)->packagingOptionId();
@endphp
@if ($packOptions->isNotEmpty())
    <div class="space-y-2 rounded-xl border border-slate-200 p-3 text-sm" data-packaging>
        <label class="flex cursor-pointer items-center gap-2 font-semibold">
            <input type="checkbox" class="accent-brand-600" data-packaging-toggle @checked($packSelected)>
            {{ __('store.packaging') }}
        </label>
        <div class="{{ $packSelected ? '' : 'hidden' }} space-y-2" data-packaging-picker role="radiogroup" aria-label="{{ __('store.packaging_choose') }}">
            <input type="radio" name="packaging_option_id" value="" class="sr-only" data-packaging-select tabindex="-1" aria-hidden="true" @checked(! $packSelected)>
            @foreach ($packOptions as $o)
                <label class="group relative flex cursor-pointer items-center gap-4 rounded-2xl border border-slate-200 bg-white p-3 pe-4 shadow-sm transition hover:border-brand-300 hover:shadow-md has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50/50 has-[:checked]:shadow-md has-[:checked]:ring-1 has-[:checked]:ring-brand-500 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand-500" data-packaging-card="{{ $o->id }}">
                    <input type="radio" name="packaging_option_id" value="{{ $o->id }}" class="peer sr-only" data-packaging-select @checked($packSelected === $o->id)>
                    @if ($o->imageUrl())
                        <img src="{{ $o->imageUrl() }}" alt="{{ $o->name }}" loading="lazy" class="h-20 w-20 shrink-0 rounded-xl bg-slate-100 object-cover ring-1 ring-slate-200 sm:h-24 sm:w-24">
                    @endif
                    <span class="flex min-w-0 flex-1 flex-col gap-1">
                        <span class="text-sm font-bold leading-snug text-slate-900">{{ $o->name }}</span>
                        @if ($o->description)<span class="line-clamp-2 text-xs leading-relaxed text-slate-500">{{ $o->description }}</span>@endif
                        <span class="mt-1"><bdi dir="ltr" class="inline-block rounded-full bg-brand-50 px-3 py-1 text-xs font-bold text-brand-700 ring-1 ring-inset ring-brand-200">+ {{ \App\Support\Money::format((string) $o->price, current_tenant()->currency_code) }}</bdi></span>
                    </span>
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border-2 border-slate-300 bg-white text-transparent transition peer-checked:border-brand-600 peer-checked:bg-brand-600 peer-checked:text-white" aria-hidden="true">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 011.4-1.4l3.8 3.8 6.8-6.8a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>
                    </span>
                </label>
            @endforeach
        </div>
    </div>
@endif
