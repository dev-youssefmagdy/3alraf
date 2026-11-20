{{-- Language switch (Figma LangTabs): segmented AR | EN control, one field visible at a time. $languages = Language collection; a red dot marks a locale whose
     required value is still empty. Posts name[ar], name[en] ... Same contract as the old <x-translatable-tabs>. --}}
@props(['name', 'label', 'languages', 'values' => [], 'type' => 'input', 'rows' => 3, 'requiredLocale' => null])
@php($values = is_array($values) ? $values : [])
<div data-tabs data-field-group class="space-y-1.5">
    <div class="flex items-center justify-between gap-3">
        <span class="form-label !mb-0">{{ $label }}</span>
        <div class="inline-flex rounded-full bg-panel-surface-sunken p-0.5" role="tablist">
            @foreach ($languages as $lang)
                @php($missing = $requiredLocale === $lang->code && blank(old($name.'.'.$lang->code, $values[$lang->code] ?? '')))
                <button type="button" role="tab" data-tab-target="{{ $name }}-{{ $lang->code }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                    class="relative min-h-11 md:min-h-8 rounded-full px-3.5 text-xs font-bold uppercase transition {{ $loop->first ? 'bg-panel-surface text-panel-text shadow-panel-xs is-active' : 'text-panel-text-muted' }}">{{ $lang->code }}@if ($missing)<span class="absolute end-1 top-1 size-1.5 rounded-full bg-panel-danger-solid" title="{{ __('ui.missing_translation') }}"></span>@endif</button>
            @endforeach
        </div>
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
