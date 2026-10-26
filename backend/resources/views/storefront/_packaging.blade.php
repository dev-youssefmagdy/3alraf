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
                <label class="flex cursor-pointer items-center gap-3 rounded-xl border-2 border-slate-200 bg-slate-50 p-2 transition has-[:checked]:border-brand-600 has-[:checked]:bg-white" data-packaging-card="{{ $o->id }}">
                    <input type="radio" name="packaging_option_id" value="{{ $o->id }}" class="accent-brand-600" data-packaging-select @checked($packSelected === $o->id)>
                    @if ($o->imageUrl())
                        <img src="{{ $o->imageUrl() }}" alt="{{ $o->name }}" loading="lazy" class="h-24 w-24 shrink-0 rounded-lg border bg-white object-cover">
                    @endif
                    <span class="min-w-0 flex-1">
                        <span class="block font-semibold">{{ $o->name }}</span>
                        @if ($o->description)<span class="block text-xs text-slate-500">{{ $o->description }}</span>@endif
                        <span class="mt-1 block font-bold text-brand-700">+{{ \App\Support\Money::format((string) $o->price, current_tenant()->currency_code) }}</span>
                    </span>
                </label>
            @endforeach
        </div>
    </div>
@endif
