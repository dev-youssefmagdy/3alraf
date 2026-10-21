{{-- Per-language inputs: name[ar], name[en]... $values = ['ar' => '..', 'en' => '..'] ; $languages = Language collection --}}
@props(['name', 'label', 'languages', 'values' => [], 'type' => 'input', 'rows' => 3, 'requiredLocale' => null])
@php($values = is_array($values) ? $values : [])
<div data-tabs data-field-group class="space-y-1">
    <div class="form-label">{{ $label }}</div>
    <div class="flex gap-1 border-b border-slate-200">
        @foreach ($languages as $lang)
            <button type="button" data-tab-target="{{ $name }}-{{ $lang->code }}"
                class="-mb-px border-b-2 px-3 py-1.5 text-xs font-semibold uppercase {{ $loop->first ? 'border-brand-600 text-brand-600' : 'border-transparent text-slate-500' }}">{{ $lang->code }}</button>
        @endforeach
    </div>
    @foreach ($languages as $lang)
        <div data-tab-pane="{{ $name }}-{{ $lang->code }}" data-field class="{{ $loop->first ? '' : 'hidden' }}" dir="{{ $lang->direction->value }}">
            @if ($type === 'textarea')
                <textarea name="{{ $name }}[{{ $lang->code }}]" rows="{{ $rows }}" class="form-control" placeholder="{{ $lang->native_name }}">{{ old($name.'.'.$lang->code, $values[$lang->code] ?? '') }}</textarea>
            @else
                <input type="text" name="{{ $name }}[{{ $lang->code }}]" value="{{ old($name.'.'.$lang->code, $values[$lang->code] ?? '') }}" class="form-control" placeholder="{{ $lang->native_name }}">
            @endif
        </div>
    @endforeach
</div>
