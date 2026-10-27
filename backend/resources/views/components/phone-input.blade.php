@props(['name' => 'phone', 'label' => null, 'value' => null, 'required' => false])
@php($code = \App\Support\Phone::tenantCode())
<div data-field {{ $attributes->only('class') }}>
    @if ($label)<label for="f-{{ $name }}" class="form-label">{{ $label }}@if ($code) <bdi dir="ltr" class="text-slate-500">({{ $code }})</bdi>@endif @if ($required)<span class="text-red-500">*</span>@endif</label>@endif
    <div class="flex items-stretch" dir="ltr">
        @if ($code)<span data-phone-code class="inline-flex items-center rounded-s-lg border border-e-0 border-slate-300 bg-slate-100 px-3 text-sm font-semibold text-slate-600" aria-hidden="true">{{ $code }}</span>@endif
        <input id="f-{{ $name }}" type="tel" name="{{ $name }}" value="{{ old($name, $value) }}" inputmode="tel" autocomplete="tel-national" maxlength="{{ config('phone.max') + 6 }}"
            @required($required) {{ $attributes->except(['class', 'type'])->merge(['class' => 'form-control min-w-0 flex-1'.($code ? ' rounded-s-none' : '')]) }}>
    </div>
    @if ($code)<p class="mt-1 text-xs text-slate-500">{{ __('store.phone_code_hint', ['code' => $code]) }}</p>@endif
</div>
