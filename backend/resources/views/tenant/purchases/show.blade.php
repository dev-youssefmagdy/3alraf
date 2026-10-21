<x-layouts.admin guard="tenant" :title="$po->number">
    <div class="space-y-4">
        <x-card>
            <div class="grid gap-3 text-sm sm:grid-cols-4">
                <div><div class="text-slate-500">{{ __('ops.fields.supplier') }}</div><div class="font-semibold">{{ $po->supplier?->name }}</div></div>
                <div><div class="text-slate-500">{{ __('ops.fields.branch') }}</div><div class="font-semibold">{{ $po->branch?->name }}</div></div>
                <div><div class="text-slate-500">{{ __('ops.fields.date') }}</div><div class="font-semibold">{{ $po->ordered_at->toDateString() }}</div></div>
                <div><div class="text-slate-500">{{ __('admin.status') }}</div><div class="font-semibold">{{ $po->status->label() }}</div></div>
            </div>
            @if ($po->note)<p class="mt-3 text-sm text-slate-600">{{ $po->note }}</p>@endif
        </x-card>
        <x-card :title="__('ops.items_section')">
            <x-slot:actions>
                @if ($po->isDraft())
                    @can('purchases.edit')<x-button :href="route('tenant.purchases.edit', $po->id)">{{ __('admin.edit') }}</x-button>
                    <button type="button" class="btn btn-secondary" data-post-action="{{ route('tenant.purchases.cancel', $po->id) }}" data-confirm="{{ __('ops.confirm_action') }}">{{ __('ops.cancel_doc') }}</button>@endcan
                    @can('purchases.receive')<button type="button" class="btn btn-primary" data-post-action="{{ route('tenant.purchases.receive', $po->id) }}" data-confirm="{{ __('ops.confirm_action') }}">{{ __('ops.receive') }}</button>@endcan
                @endif
            </x-slot:actions>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-start text-slate-500"><tr><th class="py-2 text-start">{{ __('ops.fields.item') }}</th><th class="text-start">{{ __('ops.fields.quantity') }}</th><th class="text-start">{{ __('ops.fields.unit_cost') }}</th><th class="text-start">{{ __('ops.fields.total') }}</th></tr></thead>
                    <tbody>
                        @foreach ($po->items as $i)
                            <tr class="border-t border-slate-100"><td class="py-2">{{ $i->label() }}</td><td>{{ $i->quantity }}</td><td>{{ number_format($i->unit_cost, 2) }}</td><td>{{ number_format($i->line_total, 2) }}</td></tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t border-slate-200 text-sm">
                        <tr><td colspan="3" class="py-1 text-end text-slate-500">{{ __('ops.items_section') }}</td><td>{{ number_format($po->items_total, 2) }}</td></tr>
                        <tr><td colspan="3" class="py-1 text-end text-slate-500">{{ __('ops.fields.shipping_cost') }}</td><td>{{ number_format($po->shipping_cost, 2) }}</td></tr>
                        <tr><td colspan="3" class="py-1 text-end text-slate-500">{{ __('ops.fields.other_cost') }}</td><td>{{ number_format($po->other_cost, 2) }}</td></tr>
                        <tr class="font-semibold"><td colspan="3" class="py-1 text-end">{{ __('ops.fields.total') }}</td><td>{{ number_format($po->total_cost, 2) }}</td></tr>
                    </tfoot>
                </table>
            </div>
        </x-card>
    </div>
</x-layouts.admin>
