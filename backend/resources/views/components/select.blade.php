@props(['name', 'label' => null, 'options' => [], 'value' => null, 'placeholder' => null, 'required' => false, 'search' => false])
<div data-field {{ $attributes->only('class') }}>
    @if ($label)<label for="f-{{ $name }}" class="form-label">{{ $label }}@if ($required)<span class="text-red-500">*</span>@endif</label>@endif
    <select @if ($search) data-search @endif id="f-{{ $name }}" name="{{ $name }}" {{ $attributes->except('class')->merge(['class' => 'form-control']) }}>
        @if ($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif
        @foreach ($options as $k => $v)
            <option value="{{ $k }}" @selected((string) old($name, $value) === (string) $k)>{{ $v }}</option>
        @endforeach
    </select>
</div>
