{{-- $i index or __INDEX__ ; $v = ['id','values'=>[ids],'label','sku','weight','is_active','barcode','image'] --}}
<tr data-repeater-item data-price-group="variant" data-hash="{{ $v['hash'] ?? '' }}" class="align-top">
    <td class="py-2 pe-2">
        <input type="hidden" name="variants[{{ $i }}][id]" value="{{ $v['id'] ?? '' }}">
        <div data-values>
            @foreach ($v['values'] ?? [] as $vid)<input type="hidden" name="variants[{{ $i }}][values][]" value="{{ $vid }}">@endforeach
        </div>
        <div data-field><span data-label class="font-medium">{{ $v['label'] ?? '' }}</span></div>
    </td>
    <td class="px-1 py-2" data-field><input type="text" name="variants[{{ $i }}][sku]" value="{{ $v['sku'] ?? '' }}" class="form-control min-w-36"></td>
    <td class="px-1 py-2" data-field><input type="number" step="1" min="0" inputmode="numeric" name="variants[{{ $i }}][weight]" value="{{ $v['weight'] ?? '' }}" class="form-control w-24"></td>
    <td class="px-1 py-2">
        @if (! empty($v['barcode']))
            <img src="{{ route('admin.barcode', $v['barcode']) }}?h=40" alt="{{ $v['barcode'] }}" class="h-10"><div class="text-xs text-panel-text-subtle">{{ $v['barcode'] }}</div>
        @else
            <span class="text-xs text-panel-text-subtle">{{ __('admin.barcode_auto') }}</span>
        @endif
    </td>
    <td class="px-1 py-2"><x-ui.uploader :name="'variants['.$i.'][image]'" :preview="$v['image'] ?? null" />
        @if (! empty($v['id']))
            @can('products.edit')<button type="button" class="mt-1 text-xs font-bold text-panel-accent-text underline" data-image-picker-open data-target-type="variant" data-target-id="{{ $v['id'] }}" aria-haspopup="dialog" aria-controls="image-picker">{{ __('admin.image_picker.find') }}</button>@endcan
        @endif
    </td>
    <td class="px-1 py-2" data-field><input type="number" step="0.01" min="0" name="variants[{{ $i }}][price]" value="{{ $v['price'] ?? '' }}" class="form-control !w-24"></td>
    <td class="px-1 py-2" data-field><input type="number" step="0.01" min="0" name="variants[{{ $i }}][sale_price]" value="{{ $v['sale_price'] ?? '' }}" class="form-control !w-24"></td>
    <td class="px-1 py-2" data-field><input type="number" step="0.01" min="0" name="variants[{{ $i }}][cost]" value="{{ $v['cost'] ?? '' }}" class="form-control !w-24"></td>
    <td class="px-1 py-2"><input type="number" step="0.01" min="-100" data-profit aria-label="{{ __('admin.fields.profit_percent') }}" class="form-control !w-24"></td>
    <td class="px-1 py-2" data-field><input type="number" step="1" min="0" name="variants[{{ $i }}][min_stock_alert_qty]" value="{{ $v['min_stock_alert_qty'] ?? '' }}" class="form-control !w-20"></td>
    <td class="px-1 py-2">
        <input type="hidden" name="variants[{{ $i }}][is_active]" value="0">
        <input type="checkbox" name="variants[{{ $i }}][is_active]" value="1" @checked($v['is_active'] ?? true) class="size-4 rounded border-panel-border-strong text-panel-accent-text">
    </td>
    <td class="px-1 py-2"><button type="button" data-repeater-remove class="btn-sm btn-danger">&times;</button></td>
</tr>
