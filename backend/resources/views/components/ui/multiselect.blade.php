{{-- Multi select (Figma Field › multiselect): Tom Select with remove buttons. name is posted as name[]; value = array of selected keys. --}}
@props(['name', 'label' => null, 'options' => [], 'value' => [], 'placeholder' => null, 'required' => false, 'help' => null, 'error' => null])
@php $selected = array_map('strval', (array) old(rtrim($name, '[]'), $value)); @endphp
<x-ui.field :name="$name" :label="$label" :help="$help" :error="$error" :required="$required" {{ $attributes->only('class') }}>
    <select multiple data-search id="f-{{ $name }}" name="{{ rtrim($name, '[]') }}[]" @if ($placeholder) placeholder="{{ $placeholder }}" @endif {{ $attributes->except('class')->merge(['class' => 'form-control']) }}>
        @foreach ($options as $k => $v)<option value="{{ $k }}" @selected(in_array((string) $k, $selected, true))>{{ $v }}</option>@endforeach
    </select>
</x-ui.field>
