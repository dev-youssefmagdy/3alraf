<x-layouts.admin guard="tenant" :title="__('admin.edit').': '.$tp->product->name">
    <x-card class="max-w-4xl">
        <form data-ajax-form data-method="PUT" data-validate-url="{{ route('tenant.catalog.validate', $tp) }}"
            action="{{ route('tenant.catalog.update', $tp) }}" method="POST" class="space-y-5" novalidate>
            @csrf
            <div class="flex items-center gap-3">
                @if ($u = $tp->product->primaryImageUrl())<img src="{{ $u }}" class="size-16 rounded object-cover" alt="">@endif
                <div><div class="font-semibold">{{ $tp->product->name }}</div><div class="text-xs text-slate-500">{{ $tp->product->sku }}</div></div>
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <x-input name="price" type="number" step="0.01" min="0" :label="__('admin.fields.price')" :value="$tp->price" required />
                <x-input name="sale_price" type="number" step="0.01" min="0" :label="__('admin.fields.sale_price')" :value="$tp->sale_price" />
                <x-input name="cost" type="number" step="0.01" min="0" :label="__('admin.fields.cost')" :value="$tp->cost" required />
            </div>
            <x-checkbox name="is_active" :label="__('admin.fields.is_active')" :checked="$tp->is_active" />

            @if ($tp->variants->isNotEmpty())
                <div>
                    <h3 class="mb-1 font-semibold">{{ __('admin.variants') }}</h3>
                    <p class="mb-2 text-xs text-slate-500">{{ __('admin.variant_override_hint') }}</p>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead><tr class="text-xs uppercase text-slate-500"><th class="text-start">{{ __('admin.variant') }}</th><th class="text-start">{{ __('admin.fields.price') }}</th><th class="text-start">{{ __('admin.fields.sale_price') }}</th><th class="text-start">{{ __('admin.fields.cost') }}</th><th class="text-start">{{ __('admin.fields.is_active') }}</th></tr></thead>
                            <tbody>
                            @foreach ($tp->variants->filter(fn ($tv) => $tv->variant && ! $tv->variant->trashed())->values() as $i => $tv)
                                <tr class="border-t border-slate-100">
                                    <td class="py-2">
                                        <input type="hidden" name="variants[{{ $i }}][id]" value="{{ $tv->id }}">
                                        {{ $tv->variant->label() }} <span class="text-xs text-slate-400">{{ $tv->variant->sku }}</span>
                                    </td>
                                    <td data-field><input type="number" step="0.01" min="0" name="variants[{{ $i }}][price]" value="{{ $tv->price }}" placeholder="{{ $tp->price }}" class="form-control !w-28"></td>
                                    <td data-field><input type="number" step="0.01" min="0" name="variants[{{ $i }}][sale_price]" value="{{ $tv->sale_price }}" class="form-control !w-28"></td>
                                    <td data-field><input type="number" step="0.01" min="0" name="variants[{{ $i }}][cost]" value="{{ $tv->cost }}" placeholder="{{ $tp->cost }}" class="form-control !w-28"></td>
                                    <td><input type="hidden" name="variants[{{ $i }}][is_active]" value="0"><input type="checkbox" name="variants[{{ $i }}][is_active]" value="1" @checked($tv->is_active) class="size-4 rounded border-slate-300 text-brand-600"></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
            <x-form-actions :cancel="route('tenant.catalog.index')" />
        </form>
    </x-card>
    @php($pq = $qrCodes->get(\App\Models\TenantProduct::class.':'.$tp->id))
    <x-card class="mt-4 max-w-4xl">
        <h3 class="mb-1 font-semibold">{{ __('admin.qr_code') }}</h3>
        <p class="mb-3 text-xs text-slate-500">{{ __('admin.qr_hint') }}</p>
        <div class="flex flex-wrap gap-6">
            <div class="text-center">
                <img src="{{ $pq?->file_url ?? route('tenant.catalog.qr', $tp) }}" alt="QR" class="size-40 rounded border border-slate-200" loading="lazy">
                <div class="mt-1 text-xs text-slate-500">{{ __('admin.qr_product') }}</div>
                <a href="{{ route('tenant.catalog.qr', $tp) }}" class="btn-sm btn-secondary mt-2" download>{{ __('admin.qr_download') }}</a>
                @can('catalog.edit')<button type="button" class="btn-sm btn-secondary mt-2" data-post-action="{{ route('tenant.catalog.qr.regenerate', $tp) }}" data-confirm="{{ __('admin.qr_regenerate_confirm') }}">{{ __('admin.qr_regenerate') }}</button>@endcan
            </div>
            @foreach ($tp->variants->filter(fn ($tv) => $tv->is_active && $tv->variant && ! $tv->variant->trashed() && $tv->variant->is_active) as $tv)
                @php($vq = $qrCodes->get(\App\Models\TenantProductVariant::class.':'.$tv->id))
                <div class="text-center">
                    <img src="{{ $vq?->file_url ?? route('tenant.catalog.qr', [$tp, 'variant' => $tv->id]) }}" alt="QR" class="size-40 rounded border border-slate-200" loading="lazy">
                    <div class="mt-1 text-xs text-slate-500">{{ $tv->variant->label() }}</div>
                    <a href="{{ route('tenant.catalog.qr', [$tp, 'variant' => $tv->id]) }}" class="btn-sm btn-secondary mt-2" download>{{ __('admin.qr_download') }}</a>
                    @can('catalog.edit')<button type="button" class="btn-sm btn-secondary mt-2" data-post-action="{{ route('tenant.catalog.qr.regenerate', [$tp, 'variant' => $tv->id]) }}" data-confirm="{{ __('admin.qr_regenerate_confirm') }}">{{ __('admin.qr_regenerate') }}</button>@endcan
                </div>
            @endforeach
        </div>
    </x-card>
</x-layouts.admin>
