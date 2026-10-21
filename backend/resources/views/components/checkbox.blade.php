@props(['name', 'label', 'checked' => false, 'value' => 1, 'bool' => true])
<label class="inline-flex items-center gap-2 text-sm text-slate-700">
    <input type="checkbox" name="{{ $name }}" value="{{ $value }}" @checked(old($name, $checked)) @if ($bool) data-bool @endif class="size-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
    {{ $label }}
</label>
