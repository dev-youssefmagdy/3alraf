@php use App\Enums\TransferStatus; @endphp
<x-layouts.admin :title="$t->number">
    <x-slot:header><x-ui.page-header :title="$t->number" /></x-slot:header>
    <div class="space-y-4">
        <x-ui.card>
            <div class="grid gap-3 text-sm sm:grid-cols-4">
                <div><div class="text-panel-text-subtle">{{ __('ops.fields.from_branch') }}</div><div class="font-semibold">{{ $t->from?->name }}</div></div>
                <div><div class="text-panel-text-subtle">{{ __('ops.fields.to_branch') }}</div><div class="font-semibold">{{ $t->to?->name }}</div></div>
                <div><div class="text-panel-text-subtle">{{ __('admin.status') }}</div><div class="font-semibold">{{ $t->status->label() }}</div></div>
                <div><div class="text-panel-text-subtle">{{ __('ops.fields.date') }}</div><div class="font-semibold">{{ ($t->received_at ?? $t->sent_at ?? $t->created_at)->format('Y-m-d H:i') }}</div></div>
            </div>
            @if ($t->note)<p class="mt-3 text-sm text-panel-text-muted">{{ $t->note }}</p>@endif
        </x-ui.card>
        <x-ui.card :title="__('ops.items_section')">
            <x-slot:actions>
                @if ($t->status === TransferStatus::DRAFT)
                    @can('stock_transfers.edit')<x-ui.button :href="route('admin.stock-transfers.edit', $t->id)">{{ __('admin.edit') }}</x-ui.button>
                    <button type="button" class="btn btn-secondary" data-post-action="{{ route('admin.stock-transfers.cancel', $t->id) }}" data-confirm="{{ __('ops.confirm_action') }}">{{ __('ops.cancel_doc') }}</button>@endcan
                    @can('stock_transfers.send')<button type="button" class="btn btn-primary" data-post-action="{{ route('admin.stock-transfers.send', $t->id) }}" data-confirm="{{ __('ops.confirm_action') }}">{{ __('ops.send') }}</button>@endcan
                @elseif ($t->status === TransferStatus::IN_TRANSIT)
                    @can('stock_transfers.receive')<button type="button" class="btn btn-primary" data-post-action="{{ route('admin.stock-transfers.receive', $t->id) }}" data-confirm="{{ __('ops.confirm_action') }}">{{ __('ops.receive') }}</button>@endcan
                @endif
            </x-slot:actions>
            <x-ui.table>
                <thead><tr><th>{{ __('ops.fields.item') }}</th><th>{{ __('ops.fields.quantity') }}</th><th>{{ __('ops.fields.unit_cost') }}</th></tr></thead>
                <tbody>
                    @foreach ($t->items as $i)
                        <tr><td data-label="">{{ $i->label() }}</td><td data-label="{{ __('ops.fields.quantity') }}">{{ $i->quantity }}</td><td data-label="{{ __('ops.fields.unit_cost') }}">{{ $t->status === TransferStatus::DRAFT ? '—' : number_format($i->unit_cost, 2) }}</td></tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        </x-ui.card>
    </div>
</x-layouts.admin>
