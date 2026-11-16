{{-- Boolean setting (Figma Panel/Switch). Posts 0/1 like <x-checkbox>; track uses the panel accent when on. --}}
@props(['name', 'label', 'help' => null, 'checked' => false, 'disabled' => false])
<label class="flex cursor-pointer items-start justify-between gap-4 {{ $disabled ? 'cursor-not-allowed opacity-50' : '' }}" data-field>
    <span class="min-w-0">
        <span class="block text-sm font-semibold text-slate-900">{{ $label }}</span>
        @if ($help)<span class="mt-0.5 block text-xs text-slate-500">{{ $help }}</span>@endif
    </span>
    <input type="hidden" name="{{ $name }}" value="0">
    <input type="checkbox" name="{{ $name }}" value="1" class="peer sr-only" @checked($checked) @disabled($disabled) {{ $attributes }}>
    <span class="relative h-6 w-10 shrink-0 rounded-full bg-slate-300 transition peer-checked:bg-panel peer-focus-visible:ring-2 peer-focus-visible:ring-panel/40
        after:absolute after:top-[3px] after:start-[3px] after:size-[18px] after:rounded-full after:bg-white after:shadow after:transition peer-checked:after:translate-x-4 rtl:peer-checked:after:-translate-x-4" aria-hidden="true"></span>
</label>
