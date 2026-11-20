<x-layouts.admin :title="$po->number">
    <x-slot:header><x-ui.page-header :title="$po->number" /></x-slot:header>
    <div class="space-y-4">
        <x-ui.card>
            <div class="grid gap-3 text-sm sm:grid-cols-4">
                <div><div class="text-panel-text-subtle">{{ __('ops.fields.supplier') }}</div><div class="font-semibold">{{ $po->supplier?->name }}</div></div>
                <div><div class="text-panel-text-subtle">{{ __('ops.fields.branch') }}</div><div class="font-semibold">{{ $po->branch?->name }}</div></div>
                <div><div class="text-panel-text-subtle">{{ __('ops.fields.date') }}</div><div class="font-semibold">{{ $po->ordered_at->toDateString() }}</div></div>
                <div><div class="text-panel-text-subtle">{{ __('admin.status') }}</div><div class="font-semibold">{{ $po->status->label() }}</div></div>
            </div>
            @if ($po->note)<p class="mt-3 text-sm text-panel-text-muted">{{ $po->note }}</p>@endif
        </x-ui.card>
        <x-ui.card :title="__('ops.items_section')">
            <x-slot:actions>
                @if ($po->isDraft())
                    @can('purchases.edit')<x-ui.button :href="route('admin.purchases.edit', $po->id)">{{ __('admin.edit') }}</x-ui.button>
                    <button type="button" class="btn btn-secondary" data-post-action="{{ route('admin.purchases.cancel', $po->id) }}" data-confirm="{{ __('ops.confirm_action') }}">{{ __('ops.cancel_doc') }}</button>@endcan
                    @can('purchases.receive')<button type="button" class="btn btn-primary" data-post-action="{{ route('admin.purchases.receive', $po->id) }}" data-confirm="{{ __('ops.confirm_action') }}">{{ __('ops.receive') }}</button>@endcan
                @endif
            </x-slot:actions>
            <x-ui.table>
                    <thead><tr><th>{{ __('ops.fields.item') }}</th><th>{{ __('ops.fields.quantity') }}</th><th>{{ __('ops.fields.unit_cost') }}</th><th>{{ __('ops.fields.total') }}</th></tr></thead>
                    <tbody>
                        @foreach ($po->items as $i)
                            <tr><td data-label="">{{ $i->label() }}</td><td data-label="{{ __('ops.fields.quantity') }}">{{ $i->quantity }}</td><td data-label="{{ __('ops.fields.unit_cost') }}">{{ number_format($i->unit_cost, 2) }}</td><td data-label="{{ __('ops.fields.total') }}">{{ number_format($i->line_total, 2) }}</td></tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t border-panel-border text-sm">
                        <tr><td colspan="3" class="py-1 text-end text-panel-text-subtle">{{ __('ops.items_section') }}</td><td>{{ number_format($po->items_total, 2) }}</td></tr>
                        <tr><td colspan="3" class="py-1 text-end text-panel-text-subtle">{{ __('ops.fields.shipping_cost') }}</td><td>{{ number_format($po->shipping_cost, 2) }}</td></tr>
                        <tr><td colspan="3" class="py-1 text-end text-panel-text-subtle">{{ __('ops.fields.other_cost') }}</td><td>{{ number_format($po->other_cost, 2) }}</td></tr>
                        <tr class="font-semibold"><td colspan="3" class="py-1 text-end">{{ __('ops.fields.total') }}</td><td>{{ number_format($po->total_cost, 2) }}</td></tr>
                    </tfoot>
                </x-ui.table>
        </x-ui.card>
    </div>
</x-layouts.admin>
