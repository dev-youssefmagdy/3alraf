@php
    use App\Enums\ReturnStatus;
    $money = fn ($v) => \App\Support\Money::format((string) $v, $ret->order->currency);
@endphp
<x-layouts.admin guard="tenant" :title="__('orders.return').' '.$ret->number">
    <div class="grid gap-4 lg:grid-cols-3">
        <x-card class="lg:col-span-2" :title="__('orders.items')">
            <x-slot:actions><span class="badge badge-muted">{{ $ret->status->label() }}</span></x-slot:actions>
            <table class="w-full text-sm">
                <thead class="text-slate-500"><tr><th class="py-2 text-start">{{ __('ops.fields.item') }}</th><th class="text-start">{{ __('ops.fields.quantity') }}</th><th class="text-start">{{ __('orders.fields.return_value') }}</th></tr></thead>
                <tbody>@foreach ($ret->items as $i)<tr class="border-t border-slate-100"><td class="py-2">{{ $i->orderItem->name }}</td><td>{{ $i->quantity }}</td><td>{{ $money($i->return_value) }}</td></tr>@endforeach</tbody>
            </table>
            <dl class="mt-4 space-y-1 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">{{ __('orders.fields.order') }}</dt><dd><a class="text-brand-600" href="{{ route('tenant.orders.show', $ret->order_id) }}">{{ $ret->order->number }}</a></dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">{{ __('orders.fields.return_value') }}</dt><dd>{{ $money($ret->return_value) }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">{{ __('orders.fields.refund_amount') }}</dt><dd>{{ $money($ret->refund_amount) }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">{{ __('orders.fields.restock') }}</dt><dd>{{ $ret->restock ? __('admin.yes') : __('admin.no') }} ({{ $ret->branch?->name }})</dd></div>
                @if ($ret->reason)<div class="flex justify-between"><dt class="text-slate-500">{{ __('ops.fields.reason') }}</dt><dd>{{ $ret->reason }}</dd></div>@endif
            </dl>
        </x-card>
        <div class="space-y-4">
            @if ($ret->status === ReturnStatus::REQUESTED)
                @can('returns.approve')
                <x-card :title="__('orders.approve')">
                    <form data-ajax-form action="{{ route('tenant.returns.approve', $ret->id) }}" method="POST" class="space-y-3">@csrf
                        <x-select name="branch_id" :label="__('ops.fields.branch')" :options="$branches" :value="$ret->branch_id" />
                        <x-checkbox name="restock" :label="__('orders.fields.restock')" :checked="$ret->restock" />
                        <button class="btn btn-primary w-full" type="submit">{{ __('orders.approve') }}</button>
                    </form>
                    <button type="button" class="btn btn-secondary mt-2 w-full" data-post-action="{{ route('tenant.returns.reject', $ret->id) }}" data-confirm="{{ __('ops.confirm_action') }}">{{ __('orders.reject') }}</button>
                </x-card>
                @endcan
            @elseif ($ret->status === ReturnStatus::APPROVED)
                @can('returns.refund')
                <x-card :title="__('orders.refund')">
                    <form data-ajax-form action="{{ route('tenant.returns.refund', $ret->id) }}" method="POST" class="space-y-3">@csrf
                        <x-input name="amount" type="number" step="0.01" min="0.01" max="{{ $refundable }}" :label="__('orders.fields.refund_amount')" :value="$refundable" :hint="__('orders.fields.max').': '.$refundable" />
                        <button class="btn btn-primary w-full" type="submit">{{ __('orders.refund') }}</button>
                    </form>
                </x-card>
                @endcan
            @endif
        </div>
    </div>
</x-layouts.admin>
