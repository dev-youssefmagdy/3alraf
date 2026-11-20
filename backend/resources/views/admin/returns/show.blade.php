@php
    use App\Enums\ReturnStatus;
    $money = fn ($v) => \App\Support\Money::format((string) $v, $ret->order->currency);
@endphp
<x-layouts.admin :title="__('orders.return').' '.$ret->number">
    <x-slot:header><x-ui.page-header :title="__('orders.return').' '.$ret->number" /></x-slot:header>
    <div class="grid gap-4 lg:grid-cols-3">
        <x-ui.card class="lg:col-span-2" :title="__('orders.items')">
            <x-slot:actions><span class="badge badge-muted">{{ $ret->status->label() }}</span></x-slot:actions>
            <x-ui.table>
                <thead><tr><th>{{ __('ops.fields.item') }}</th><th>{{ __('ops.fields.quantity') }}</th><th>{{ __('orders.fields.return_value') }}</th></tr></thead>
                <tbody>@foreach ($ret->items as $i)<tr><td data-label="">{{ $i->orderItem->name }}</td><td data-label="{{ __('ops.fields.quantity') }}">{{ $i->quantity }}</td><td data-label="{{ __('orders.fields.return_value') }}">{{ $money($i->return_value) }}</td></tr>@endforeach</tbody>
            </x-ui.table>
            <dl class="mt-4 space-y-1 text-sm">
                <div class="flex justify-between"><dt class="text-panel-text-subtle">{{ __('orders.fields.order') }}</dt><dd><a class="text-panel-accent-text" href="{{ route('admin.orders.show', $ret->order_id) }}">{{ $ret->order->number }}</a></dd></div>
                <div class="flex justify-between"><dt class="text-panel-text-subtle">{{ __('orders.fields.return_value') }}</dt><dd>{{ $money($ret->return_value) }}</dd></div>
                <div class="flex justify-between"><dt class="text-panel-text-subtle">{{ __('orders.fields.refund_amount') }}</dt><dd>{{ $money($ret->refund_amount) }}</dd></div>
                <div class="flex justify-between"><dt class="text-panel-text-subtle">{{ __('orders.fields.restock') }}</dt><dd>{{ $ret->restock ? __('admin.yes') : __('admin.no') }} ({{ $ret->branch?->name }})</dd></div>
                @if ($ret->reason)<div class="flex justify-between"><dt class="text-panel-text-subtle">{{ __('ops.fields.reason') }}</dt><dd>{{ $ret->reason }}</dd></div>@endif
            </dl>
        </x-ui.card>
        <div class="space-y-4">
            @if ($ret->status === ReturnStatus::REQUESTED)
                @can('returns.approve')
                <x-ui.card :title="__('orders.approve')">
                    <form data-ajax-form action="{{ route('admin.returns.approve', $ret->id) }}" method="POST" class="space-y-3">@csrf
                        <x-ui.select name="branch_id" :label="__('ops.fields.branch')" :options="$branches" :value="$ret->branch_id" />
                        <x-ui.checkbox name="restock" :label="__('orders.fields.restock')" :checked="$ret->restock" />
                        <button class="btn btn-primary w-full" type="submit">{{ __('orders.approve') }}</button>
                    </form>
                    <button type="button" class="btn btn-secondary mt-2 w-full" data-post-action="{{ route('admin.returns.reject', $ret->id) }}" data-confirm="{{ __('ops.confirm_action') }}">{{ __('orders.reject') }}</button>
                </x-ui.card>
                @endcan
            @elseif ($ret->status === ReturnStatus::APPROVED)
                @can('returns.refund')
                <x-ui.card :title="__('orders.refund')">
                    <form data-ajax-form action="{{ route('admin.returns.refund', $ret->id) }}" method="POST" class="space-y-3">@csrf
                        <x-ui.input name="amount" type="number" step="0.01" min="0.01" max="{{ $refundable }}" :label="__('orders.fields.refund_amount')" :value="$refundable" :hint="__('orders.fields.max').': '.$refundable" />
                        <x-ui.select name="refund_from" :label="__('erp_acc.refund_from')" :placeholder="__('erp_acc.refund_from_default')" :options="collect(\App\Services\Erp\Posting\Poster::REFUND_ROLES)->mapWithKeys(fn ($k) => [$k => __('erp_acc.roles.'.$k)])->all()" />
                        <button class="btn btn-primary w-full" type="submit">{{ __('orders.refund') }}</button>
                    </form>
                </x-ui.card>
                @endcan
            @endif
        </div>
    </div>
</x-layouts.admin>
