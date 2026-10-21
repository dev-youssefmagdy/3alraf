@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'required' => false, 'hint' => null])
<div data-field {{ $attributes->only('class') }}>
    @if ($label)<label for="f-{{ $name }}" class="form-label">{{ $label }}@if ($required)<span class="text-red-500">*</span>@endif</label>@endif
    <input id="f-{{ $name }}" type="{{ $type }}" name="{{ $name }}" @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
        @required($required) {{ $attributes->except('class')->merge(['class' => 'form-control']) }}>
    @if ($hint)<p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>@endif
</div>
