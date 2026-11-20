{{-- Field wrapper (Figma Field): label above, control (slot), helper below; an error replaces the helper (icon + red text). Server-side errors are added after
     [data-field] by resources/js/lib/ajax-form.js. The control inside needs id="f-{name}". --}}
@props(['name' => null, 'label' => null, 'help' => null, 'error' => null, 'required' => false, 'disabled' => false])
<div data-field {{ $attributes->only(["class", "data-edit-field"]) }}>
    @if ($label)<label @if ($name) for="f-{{ $name }}" @endif class="form-label">{{ $label }}@if ($required)<span class="ms-0.5 text-panel-danger-solid" title="{{ __('ui.required') }}">*</span><span class="sr-only"> ({{ __('ui.required') }})</span>@endif</label>@endif
    {{ $slot }}
    @if ($error)<p class="field-error" role="alert">{{ $error }}</p>@elseif ($help)<p class="field-help">{{ $help }}</p>@endif
</div>
