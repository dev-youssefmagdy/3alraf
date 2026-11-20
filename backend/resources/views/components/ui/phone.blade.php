{{-- Phone input (Figma Field › phone): national number with the shop's country code as a fixed prefix; always LTR. --}}
@props(['name' => 'phone', 'label' => null, 'value' => null, 'required' => false, 'help' => null, 'error' => null])
@php($code = \App\Support\Phone::shopCode())
<div data-field {{ $attributes->only('class') }}>
    @if ($label)<label for="f-{{ $name }}" class="form-label">{{ $label }}@if ($code) <bdi dir="ltr" class="font-normal text-panel-text-subtle">({{ $code }})</bdi>@endif @if ($required)<span class="text-panel-danger-solid">*</span>@endif</label>@endif
    <div class="flex items-stretch" dir="ltr">
        @if ($code)<span data-phone-code class="inline-flex min-h-11 items-center rounded-s-xl border border-e-0 border-panel-border-strong bg-panel-surface-sunken px-3 text-sm font-semibold text-panel-text-muted" aria-hidden="true">{{ $code }}</span>@endif
        <input id="f-{{ $name }}" type="tel" name="{{ $name }}" value="{{ old($name, $value) }}" inputmode="tel" autocomplete="tel-national" maxlength="{{ config('phone.max') + 6 }}"
            @required($required) {{ $attributes->except(['class', 'type'])->merge(['class' => 'form-control min-w-0 flex-1'.($code ? ' !rounded-s-none' : '').($error ? ' is-invalid' : '')]) }}>
    </div>
    @if ($error)<p class="field-error" role="alert">{{ $error }}</p>@elseif ($help ?? ($code ? __('store.phone_code_hint', ['code' => $code]) : null))<p class="field-help">{{ $help ?? __('store.phone_code_hint', ['code' => $code]) }}</p>@endif
</div>
