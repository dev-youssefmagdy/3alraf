<x-layouts.admin :title="$a->number">
    <x-slot:header><x-ui.page-header :title="$a->number" /></x-slot:header>
    <div class="space-y-4">
        <x-ui.card>
            <div class="grid gap-3 text-sm sm:grid-cols-4">
                <div><div class="text-panel-text-subtle">{{ __('ops.fields.branch') }}</div><div class="font-semibold">{{ $a->branch?->name }}</div></div>
                <div><div class="text-panel-text-subtle">{{ __('ops.fields.type') }}</div><div class="font-semibold">{{ $a->type->label() }}</div></div>
                <div><div class="text-panel-text-subtle">{{ __('ops.fields.reason') }}</div><div class="font-semibold">{{ $a->reason->label() }}</div></div>
                <div><div class="text-panel-text-subtle">{{ __('ops.fields.date') }}</div><div class="font-semibold">{{ $a->created_at->format('Y-m-d H:i') }}</div></div>
            </div>
            @if ($a->note)<p class="mt-3 text-sm text-panel-text-muted">{{ $a->note }}</p>@endif
        </x-ui.card>
        <x-ui.card :title="__('ops.items_section')">
            <x-ui.table>
                <thead><tr><th>{{ __('ops.fields.item') }}</th><th>{{ __('ops.fields.quantity') }}</th><th>{{ __('ops.fields.unit_cost') }}</th></tr></thead>
                <tbody>
                    @foreach ($a->items as $i)
                        <tr><td data-label="">{{ $i->label() }}</td><td data-label="{{ __('ops.fields.quantity') }}">{{ $i->quantity }}</td><td data-label="{{ __('ops.fields.unit_cost') }}">{{ number_format($i->unit_cost, 2) }}</td></tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        </x-ui.card>
    </div>
</x-layouts.admin>
