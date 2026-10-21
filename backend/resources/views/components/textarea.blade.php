@props(['name', 'label' => null, 'value' => null, 'rows' => 3, 'required' => false])
<div data-field {{ $attributes->only('class') }}>
    @if ($label)<label for="f-{{ $name }}" class="form-label">{{ $label }}@if ($required)<span class="text-red-500">*</span>@endif</label>@endif
    <textarea id="f-{{ $name }}" name="{{ $name }}" rows="{{ $rows }}" {{ $attributes->except('class')->merge(['class' => 'form-control']) }}>{{ old($name, $value) }}</textarea>
</div>
