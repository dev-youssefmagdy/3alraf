{{-- Textarea (Figma Field › textarea). Same contract as the old <x-textarea>. --}}
@props(['name', 'label' => null, 'value' => null, 'rows' => 3, 'required' => false, 'help' => null, 'error' => null])
<x-ui.field :name="$name" :label="$label" :help="$help" :error="$error" :required="$required" {{ $attributes->only('class') }}>
    <textarea id="f-{{ $name }}" name="{{ $name }}" rows="{{ $rows }}" @required($required) {{ $attributes->except('class')->merge(['class' => 'form-control'.($error ? ' is-invalid' : '')]) }}>{{ old($name, $value) }}</textarea>
</x-ui.field>
