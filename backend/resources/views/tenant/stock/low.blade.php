<x-layouts.admin guard="tenant" :title="__('stock_requests.low_stock')">
    <x-card>
        <p class="mb-4 text-sm text-slate-500">{{ __('stock_requests.low_stock_hint') }}</p>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="text-xs uppercase text-slate-500">
                    <th class="text-start">{{ __('growth.product') }}</th><th class="text-start">{{ __('admin.variant') }}</th>
                    <th class="text-start">{{ __('stock_requests.available') }}</th><th class="text-start">{{ __('stock_requests.alert_at') }}</th><th></th>
                </tr></thead>
                <tbody>
                @forelse ($rows as $r)
                    <tr class="border-t border-slate-100">
                        <td class="py-2"><div class="flex items-center gap-2">@if ($img = $r['tp']->product->primaryImageUrl())<img src="{{ $img }}" class="size-10 rounded object-cover" alt="">@endif
                            <div><div class="font-semibold">{{ $r['tp']->product->name }}</div><div class="text-xs text-slate-400">{{ $r['variant']?->sku ?? $r['tp']->product->sku }}</div></div></div></td>
                        <td>{{ $r['variant']?->label() ?? '—' }}</td>
                        <td><span class="badge {{ $r['available'] === 0 ? 'badge-danger' : 'badge-warn' }}">{{ $r['available'] === 0 ? __('store.out_of_stock') : $r['available'] }}</span></td>
                        <td>{{ $r['alert'] }}</td>
                        <td class="text-end">@can('catalog.edit')<a href="{{ route('tenant.catalog.edit', $r['tp']) }}" class="btn-sm btn-secondary">{{ __('admin.edit') }}</a>@endcan</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-6 text-center text-slate-400">{{ __('stock_requests.low_stock_empty') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</x-layouts.admin>
