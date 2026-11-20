{{-- Select (Figma Field › select): options = [value => label]; searchable turns it into a Tom Select (the native <select> stays the source of truth). --}}
@props(['name', 'label' => null, 'options' => [], 'value' => null, 'placeholder' => null, 'required' => false, 'searchable' => false, 'search' => false, 'help' => null, 'error' => null])
<x-ui.field :name="$name" :label="$label" :help="$help" :error="$error" :required="$required" {{ $attributes->only('class') }}>
    <select @if ($searchable || $search) data-search @endif id="f-{{ $name }}" name="{{ $name }}" @required($required) {{ $attributes->except('class')->merge(['class' => 'form-control'.($error ? ' is-invalid' : '')]) }}>
        @if ($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif
        @foreach ($options as $k => $v)<option value="{{ $k }}" @selected((string) old($name, $value) === (string) $k)>{{ $v }}</option>@endforeach
    </select>
</x-ui.field>
