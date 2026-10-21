<div data-repeater-item data-field class="flex flex-wrap items-start gap-2 rounded-lg border border-slate-200 p-3">
    <input type="hidden" name="values[{{ $i }}][id]" value="{{ $row['id'] ?? '' }}">
    @foreach ($languages as $lang)
        <div class="min-w-32 flex-1" data-field>
            <input type="text" name="values[{{ $i }}][value][{{ $lang->code }}]" value="{{ $row['value'][$lang->code] ?? '' }}" dir="{{ $lang->direction->value }}"
                class="form-control" placeholder="{{ $lang->native_name }}">
        </div>
    @endforeach
    <div class="w-28" data-field data-color-field>
        <input type="color" name="values[{{ $i }}][color_hex]" value="{{ $row['color_hex'] ?? '#000000' }}" class="form-control !p-1 h-[38px]">
    </div>
    <button type="button" data-repeater-remove class="btn-sm btn-danger mt-1">&times;</button>
</div>
