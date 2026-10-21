<x-layouts.admin guard="tenant" :title="__('growth.product_lists')">
    <div class="space-y-6">
        <p class="text-sm text-slate-500">{{ __('growth.lists_intro') }}</p>
        @foreach (\App\Enums\ProductListType::cases() as $type)
            @php($data = $lists[$type->value])
            <x-card :title="$type->label()">
                <form data-ajax-form data-method="PUT" data-validate-url="{{ route('tenant.product-lists.validate', $type->value) }}" action="{{ route('tenant.product-lists.update', $type->value) }}" method="POST" class="space-y-3" novalidate>
                    @csrf
                    <div data-pin-list class="space-y-2">
                        <div class="flex flex-wrap gap-2">
                            <select data-pin-select class="form-control max-w-md"><option value="">{{ __('admin.select') }}</option>@foreach ($options as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach</select>
                            <button type="button" data-pin-add class="btn btn-secondary">{{ __('growth.pin') }}</button>
                        </div>
                        <ul data-pin-items class="space-y-1">
                            @foreach ($data['pinned'] as $item)
                                <li data-pin-item class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-1.5 text-sm">
                                    <input type="hidden" name="product_ids[]" value="{{ $item->product_id }}"><span class="flex-1">{{ $item->product?->name }}</span>
                                    <button type="button" data-pin-up class="btn-sm btn-secondary">↑</button><button type="button" data-pin-down class="btn-sm btn-secondary">↓</button><button type="button" data-pin-remove class="btn-sm btn-danger">&times;</button>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="flex items-end gap-3">
                        <x-input class="w-40" name="limit" type="number" min="1" max="60" :label="__('growth.fields.limit')" :value="$data['limit']" required />
                        <button type="submit" class="btn btn-primary">{{ __('admin.save') }}</button>
                    </div>
                    <p class="text-xs text-slate-500">{{ __('growth.fill_hint.'.$type->value) }}</p>
                </form>
            </x-card>
        @endforeach
    </div>
</x-layouts.admin>
