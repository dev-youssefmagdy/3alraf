<x-layouts.admin :title="__('stock_requests.low_stock')">
    <x-slot:header><x-ui.page-header :title="__('stock_requests.low_stock')" /></x-slot:header>
    <x-ui.card>
        <p class="mb-4 text-sm text-panel-text-subtle">{{ __('stock_requests.low_stock_hint') }}</p>
        <x-ui.table>
                <thead><tr>
                    <th>{{ __('growth.product') }}</th><th>{{ __('admin.variant') }}</th>
                    <th>{{ __('stock_requests.available') }}</th><th>{{ __('stock_requests.alert_at') }}</th><th></th>
                </tr></thead>
                <tbody>
                @forelse ($rows as $r)
                    <tr>
                        <td data-label=""><div class="flex items-center gap-2">@if ($img = $r['product']->primaryImageUrl())<img src="{{ $img }}" class="size-10 rounded object-cover" alt="">@endif
                            <div><div class="font-semibold">{{ $r['product']->name }}</div><div class="text-xs text-panel-text-subtle">{{ $r['variant']?->sku ?? $r['product']->sku }}</div></div></div></td>
                        <td data-label="{{ __('admin.variant') }}">{{ $r['variant']?->label() ?? '—' }}</td>
                        <td data-label="{{ __('stock_requests.available') }}"><span class="badge {{ $r['available'] === 0 ? 'badge-danger' : 'badge-warn' }}">{{ $r['available'] === 0 ? __('store.out_of_stock') : $r['available'] }}</span></td>
                        <td data-label="{{ __('stock_requests.alert_at') }}">{{ $r['alert'] }}</td>
                        <td class="ui-table-actions text-end">@can('products.edit')<a href="{{ route('admin.products.edit', $r['product']) }}" class="btn-sm btn-secondary">{{ __('admin.edit') }}</a>@endcan</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center">{{ __('stock_requests.low_stock_empty') }}</td></tr>
                @endforelse
                </tbody>
            </x-ui.table>
    </x-ui.card>
</x-layouts.admin>
