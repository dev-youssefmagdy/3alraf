{{-- Boolean setting (Figma Switch). Posts 0/1 like <x-checkbox>; track uses the panel accent when on; the thumb mirrors in RTL. --}}
@props(['name', 'label', 'help' => null, 'checked' => false, 'disabled' => false])
<label class="flex min-h-11 cursor-pointer items-start justify-between gap-4 {{ $disabled ? 'cursor-not-allowed opacity-50' : '' }}" data-field>
    <span class="min-w-0">
        <span class="block text-sm font-semibold text-panel-text">{{ $label }}</span>
        @if ($help)<span class="mt-0.5 block text-xs text-panel-text-subtle">{{ $help }}</span>@endif
    </span>
    <input type="hidden" name="{{ $name }}" value="0">
    <input type="checkbox" name="{{ $name }}" value="1" role="switch" class="peer sr-only" @checked(old($name, $checked)) @disabled($disabled) {{ $attributes }}>
    <span class="relative mt-0.5 h-6 w-10 shrink-0 rounded-full bg-panel-border-strong transition duration-[120ms] peer-checked:bg-panel-accent peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-panel-accent
        after:absolute after:top-[3px] after:start-[3px] after:size-[18px] after:rounded-full after:bg-white after:shadow after:transition peer-checked:after:translate-x-4 rtl:peer-checked:after:-translate-x-4" aria-hidden="true"></span>
</label>
