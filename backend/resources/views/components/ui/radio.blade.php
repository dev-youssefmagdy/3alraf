{{-- Radio (Figma Radio): use for 5 options or fewer, otherwise a Select. --}}
@props(['name', 'label', 'value', 'checked' => false, 'disabled' => false])
<label {{ $attributes->merge(['class' => 'flex w-fit min-h-11 cursor-pointer items-center gap-3 text-sm text-panel-text '.($disabled ? 'cursor-not-allowed opacity-50' : '')]) }}>
    <input type="radio" name="{{ $name }}" value="{{ $value }}" @checked((string) old($name, $checked ? $value : null) === (string) $value) @disabled($disabled) class="size-[22px] shrink-0 border-panel-border-strong">
    <span>{{ $label }}</span>
</label>
