{{-- OtpInput (Figma OtpInput): N boxes with auto-advance, backspace and paste; the joined digits go in a hidden input `name` (default "code"), so the
     AJAX form and validation are unchanged. Digits stay LTR in RTL. Behaviour: resources/js/lib/ui.js [data-otp]. --}}
@props(['name' => 'code', 'length' => 6, 'error' => null, 'label' => null])
<div data-field {{ $attributes->only('class') }}>
    @if ($label)<span class="form-label">{{ $label }}</span>@endif
    <div data-otp data-length="{{ $length }}" dir="ltr" class="flex justify-center gap-2 sm:gap-3" role="group" aria-label="{{ $label ?? __('ui.otp_code') }}">
        @for ($i = 0; $i < $length; $i++)
            <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="{{ $i === 0 ? $length : 1 }}" autocomplete="{{ $i === 0 ? 'one-time-code' : 'off' }}" data-otp-box aria-label="{{ __('ui.otp_digit', ['n' => $i + 1]) }}"
                class="size-12 rounded-xl border border-panel-border-strong bg-panel-surface text-center text-xl font-bold text-panel-text transition focus:border-2 focus:border-panel-accent focus:outline-none focus:ring-4 focus:ring-panel-accent-soft sm:size-14 {{ $error ? 'is-invalid !border-panel-danger-solid' : '' }}" @if ($i === 0) autofocus @endif>
        @endfor
        <input type="hidden" name="{{ $name }}" data-otp-value>
    </div>
    @if ($error)<p class="field-error justify-center" role="alert">{{ $error }}</p>@endif
</div>
