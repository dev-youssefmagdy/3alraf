{{-- Checkbox (Figma Checkbox): 44px row, optional indeterminate (bulk-select header). Posts like the old <x-checkbox> (data-bool = JS sends 0/1). --}}
@props(['name', 'label', 'checked' => false, 'value' => 1, 'bool' => true, 'indeterminate' => false, 'disabled' => false, 'help' => null])
<label {{ $attributes->merge(['class' => 'flex w-fit min-h-11 cursor-pointer items-center gap-3 text-sm text-panel-text '.($disabled ? 'cursor-not-allowed opacity-50' : '')]) }}>
    <input type="checkbox" name="{{ $name }}" value="{{ $value }}" @checked(old($name, $checked)) @disabled($disabled) @if ($bool) data-bool @endif @if ($indeterminate) data-indeterminate @endif class="size-[22px] shrink-0 rounded-md border-panel-border-strong">
    <span>{{ $label }}@if ($help)<span class="block text-xs text-panel-text-subtle">{{ $help }}</span>@endif</span>
</label>
