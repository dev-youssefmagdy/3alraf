{{-- Text input (Figma Field › text): label, helper, error, leading icon, trailing text. Same contract as the old <x-input> (value goes through old()). --}}
@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'required' => false, 'help' => null, 'hint' => null, 'error' => null, 'icon' => null, 'suffix' => null, 'disabled' => false])
<x-ui.field :name="$name" :label="$label" :help="$help ?? $hint" :error="$error" :required="$required" {{ $attributes->only('class') }}>
    <div class="relative">
        @if ($icon)<x-ui.icon :name="$icon" class="pointer-events-none absolute start-3.5 top-1/2 size-[18px] -translate-y-1/2 text-panel-text-subtle" />@endif
        <input id="f-{{ $name }}" type="{{ $type }}" name="{{ $name }}" @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
            @required($required) @disabled($disabled) @if ($error) aria-invalid="true" @endif
            {{ $attributes->except('class')->merge(['class' => 'form-control'.($error ? ' is-invalid' : '').($icon ? ' !ps-10' : '').($suffix ? ' !pe-14' : '')]) }}>
        @if ($suffix)<span class="pointer-events-none absolute end-3.5 top-1/2 -translate-y-1/2 text-xs font-semibold text-panel-text-subtle">{{ $suffix }}</span>@endif
    </div>
</x-ui.field>
