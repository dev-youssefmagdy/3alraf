@php use App\Enums\TransferStatus; @endphp
<x-layouts.admin guard="tenant" :title="$t->number">
    <div class="space-y-4">
        <x-card>
            <div class="grid gap-3 text-sm sm:grid-cols-4">
                <div><div class="text-slate-500">{{ __('ops.fields.from_branch') }}</div><div class="font-semibold">{{ $t->from?->name }}</div></div>
                <div><div class="text-slate-500">{{ __('ops.fields.to_branch') }}</div><div class="font-semibold">{{ $t->to?->name }}</div></div>
                <div><div class="text-slate-500">{{ __('admin.status') }}</div><div class="font-semibold">{{ $t->status->label() }}</div></div>
                <div><div class="text-slate-500">{{ __('ops.fields.date') }}</div><div class="font-semibold">{{ ($t->received_at ?? $t->sent_at ?? $t->created_at)->format('Y-m-d H:i') }}</div></div>
            </div>
            @if ($t->note)<p class="mt-3 text-sm text-slate-600">{{ $t->note }}</p>@endif
        </x-card>
        <x-card :title="__('ops.items_section')">
            <x-slot:actions>
                @if ($t->status === TransferStatus::DRAFT)
                    @can('stock_transfers.edit')<x-button :href="route('tenant.stock-transfers.edit', $t->id)">{{ __('admin.edit') }}</x-button>
                    <button type="button" class="btn btn-secondary" data-post-action="{{ route('tenant.stock-transfers.cancel', $t->id) }}" data-confirm="{{ __('ops.confirm_action') }}">{{ __('ops.cancel_doc') }}</button>@endcan
                    @can('stock_transfers.send')<button type="button" class="btn btn-primary" data-post-action="{{ route('tenant.stock-transfers.send', $t->id) }}" data-confirm="{{ __('ops.confirm_action') }}">{{ __('ops.send') }}</button>@endcan
                @elseif ($t->status === TransferStatus::IN_TRANSIT)
                    @can('stock_transfers.receive')<button type="button" class="btn btn-primary" data-post-action="{{ route('tenant.stock-transfers.receive', $t->id) }}" data-confirm="{{ __('ops.confirm_action') }}">{{ __('ops.receive') }}</button>@endcan
                @endif
            </x-slot:actions>
            <table class="w-full text-sm">
                <thead class="text-slate-500"><tr><th class="py-2 text-start">{{ __('ops.fields.item') }}</th><th class="text-start">{{ __('ops.fields.quantity') }}</th><th class="text-start">{{ __('ops.fields.unit_cost') }}</th></tr></thead>
                <tbody>
                    @foreach ($t->items as $i)
                        <tr class="border-t border-slate-100"><td class="py-2">{{ $i->label() }}</td><td>{{ $i->quantity }}</td><td>{{ $t->status === TransferStatus::DRAFT ? '—' : number_format($i->unit_cost, 2) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </x-card>
    </div>
</x-layouts.admin>
