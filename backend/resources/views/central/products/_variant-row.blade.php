{{-- $i index or __INDEX__ ; $v = ['id','values'=>[ids],'label','sku','weight','is_active','barcode','image'] --}}
<tr data-repeater-item data-hash="{{ $v['hash'] ?? '' }}" class="align-top">
    <td class="py-2 pe-2">
        <input type="hidden" name="variants[{{ $i }}][id]" value="{{ $v['id'] ?? '' }}">
        <div data-values>
            @foreach ($v['values'] ?? [] as $vid)<input type="hidden" name="variants[{{ $i }}][values][]" value="{{ $vid }}">@endforeach
        </div>
        <div data-field><span data-label class="font-medium">{{ $v['label'] ?? '' }}</span></div>
    </td>
    <td class="px-1 py-2" data-field><input type="text" name="variants[{{ $i }}][sku]" value="{{ $v['sku'] ?? '' }}" class="form-control min-w-36"></td>
    <td class="px-1 py-2" data-field><input type="number" step="0.001" min="0" name="variants[{{ $i }}][weight]" value="{{ $v['weight'] ?? '' }}" class="form-control w-24"></td>
    <td class="px-1 py-2">
        @if (! empty($v['barcode']))
            <img src="{{ route('central.barcode', $v['barcode']) }}?h=40" alt="{{ $v['barcode'] }}" class="h-10"><div class="text-xs text-slate-500">{{ $v['barcode'] }}</div>
        @else
            <span class="text-xs text-slate-400">{{ __('admin.barcode_auto') }}</span>
        @endif
    </td>
    <td class="px-1 py-2" data-field>
        @if (! empty($v['image']))<img src="{{ $v['image'] }}" class="mb-1 size-10 rounded object-cover" alt="">@endif
        <input type="file" name="variants[{{ $i }}][image]" accept="image/*" class="w-40 text-xs">
    </td>
    <td class="px-1 py-2">
        <input type="hidden" name="variants[{{ $i }}][is_active]" value="0">
        <input type="checkbox" name="variants[{{ $i }}][is_active]" value="1" @checked($v['is_active'] ?? true) class="size-4 rounded border-slate-300 text-brand-600">
    </td>
    <td class="px-1 py-2"><button type="button" data-repeater-remove class="btn-sm btn-danger">&times;</button></td>
</tr>
