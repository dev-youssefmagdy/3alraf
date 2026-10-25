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
        <div class="{{ $packSelected ? '' : 'hidden' }}" data-packaging-picker>
            <select name="packaging_option_id" class="form-control" data-packaging-select aria-label="{{ __('store.packaging_choose') }}">
                <option value="">{{ __('store.packaging_choose') }}</option>
                @foreach ($packOptions as $o)
                    <option value="{{ $o->id }}" @selected($packSelected === $o->id)>{{ $o->name }} (+{{ \App\Support\Money::format((string) $o->price, current_tenant()->currency_code) }})</option>
                @endforeach
            </select>
            @foreach ($packOptions as $o)
                @if ($o->description)<p class="mt-1 hidden text-xs text-slate-500" data-packaging-desc="{{ $o->id }}">{{ $o->description }}</p>@endif
            @endforeach
        </div>
    </div>
@endif
