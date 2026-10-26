@php
    use App\Enums\{OrderStatus, PaymentStatus, ProofStatus};
    $next = $order->status->next();
    $canReturn = $order->status === OrderStatus::DELIVERED;
    $money = fn ($v) => \App\Support\Money::format((string) $v, $order->currency);
@endphp
<x-layouts.admin guard="tenant" :title="__('orders.order').' '.$order->number">
    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-card :title="__('orders.items')">
                <x-slot:actions>
                    <span class="badge {{ $order->status->color() }}">{{ $order->status->label() }}</span>
                    <a href="{{ route('tenant.orders.invoice', $order->id) }}" target="_blank" class="btn-sm btn-secondary">{{ __('orders.invoice') }}</a>
                    <a href="{{ route('tenant.orders.invoice.pdf', $order->id) }}" class="btn-sm btn-secondary">PDF</a>
                </x-slot:actions>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-slate-500"><tr><th class="py-2 text-start">{{ __('ops.fields.item') }}</th><th class="text-start">{{ __('orders.fields.unit_price') }}</th><th class="text-start">{{ __('ops.fields.quantity') }}</th><th class="text-start">{{ __('orders.fields.discount') }}</th><th class="text-start">{{ __('ops.fields.total') }}</th><th class="text-start">{{ __('orders.fields.returned') }}</th></tr></thead>
                        <tbody>
                            @php $grp = null; @endphp
                            @foreach ($order->items as $i)
                                @if ($i->bundle_group && $i->bundle_group !== $grp)<tr class="border-t border-slate-100 bg-slate-50"><td colspan="6" class="py-1.5 text-xs font-bold text-brand-600">{{ __('orders.in_bundle') }}: {{ $i->bundle_name }}</td></tr>@endif
                                @php $grp = $i->bundle_group; @endphp
                                <tr class="border-t border-slate-100"><td class="py-2 {{ $i->bundle_group ? 'ps-4' : '' }}">{{ $i->name }}<div class="text-xs text-slate-400">{{ $i->sku }}</div></td><td>{{ number_format($i->unit_price, 2) }}</td><td>{{ $i->quantity }}</td><td>{{ number_format($i->line_discount, 2) }}</td><td>{{ number_format($i->line_subtotal - $i->line_discount, 2) }}</td><td>{{ $i->returned_qty }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @include('tenant.orders._totals', ['order' => $order])
            </x-card>

            @if ($canReturn)
                @can('returns.create')
                <x-card :title="__('orders.create_return')">
                    <form data-ajax-form data-validate-url="{{ route('tenant.orders.returns.validate', $order->id) }}" action="{{ route('tenant.orders.returns.store', $order->id) }}" method="POST" class="space-y-3" novalidate>
                        @csrf
                        @foreach ($order->items as $i)
                            @continue($i->quantity - $i->returned_qty <= 0)
                            <div class="grid items-end gap-2 sm:grid-cols-12">
                                <input type="hidden" name="items[{{ $i->id }}][order_item_id]" value="{{ $i->id }}">
                                <div class="text-sm sm:col-span-9">@if ($i->bundle_name)<span class="text-xs font-bold text-brand-600">[{{ $i->bundle_name }}]</span> @endif{{ $i->name }} <span class="text-xs text-slate-400">({{ __('orders.fields.returnable') }}: {{ $i->quantity - $i->returned_qty }})</span></div>
                                <x-input class="sm:col-span-3" name="items[{{ $i->id }}][quantity]" type="number" min="0" max="{{ $i->quantity - $i->returned_qty }}" :value="0" />
                            </div>
                        @endforeach
                        <div class="grid gap-3 sm:grid-cols-3">
                            <x-select name="branch_id" :label="__('ops.fields.branch')" :options="$branches" :value="$order->branch_id" />
                            <x-input name="return_shipping_cost" type="number" step="0.01" min="0" :label="__('orders.fields.return_shipping_cost')" :value="0" />
                            <x-input name="reason" :label="__('ops.fields.reason')" />
                        </div>
                        <div class="flex flex-wrap gap-4">
                            <x-checkbox name="restock" :label="__('orders.fields.restock')" :checked="true" />
                            <x-checkbox name="refund_shipping" :label="__('orders.fields.refund_shipping')" :checked="false" />
                        </div>
                        <button class="btn btn-primary" type="submit">{{ __('orders.create_return') }}</button>
                    </form>
                </x-card>
                @endcan
            @endif

            @if ($order->returns->isNotEmpty())
                <x-card :title="__('orders.returns')">
                    @foreach ($order->returns as $r)
                        <div class="flex items-center justify-between border-t border-slate-100 py-2 text-sm first:border-0">
                            <a class="font-semibold text-brand-600" href="{{ route('tenant.returns.show', $r->id) }}">{{ $r->number }}</a>
                            <span>{{ $money($r->return_value) }}</span><span class="badge badge-muted">{{ $r->status->label() }}</span>
                        </div>
                    @endforeach
                </x-card>
            @endif
        </div>

        <div class="space-y-4">
            @if ($next)
                @can('orders.edit')
                <x-card :title="__('orders.change_status')">
                    <form data-ajax-form data-validate-url="{{ route('tenant.orders.status.validate', $order->id) }}" action="{{ route('tenant.orders.status', $order->id) }}" method="POST" class="space-y-3" novalidate>
                        @csrf
                        <x-select name="status" :options="collect($next)->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()" />
                        <x-input name="note" :placeholder="__('ops.fields.note')" />
                        <button class="btn btn-primary w-full" type="submit">{{ __('orders.update') }}</button>
                    </form>
                </x-card>
                @endcan
            @endif

            <x-card :title="__('orders.fields.customer')">
                <div class="space-y-1 text-sm">
                    <div class="font-semibold">{{ $order->customer_name }}@if ($order->customer) <a class="text-xs text-brand-600" href="{{ route('tenant.customers.show', $order->customer_id) }}">#{{ $order->customer_id }}</a>@endif</div>
                    <div>{{ $order->customer_phone }}</div><div>{{ $order->customer_email }}</div>
                    <div class="text-slate-600">{{ $order->city?->name }} - {{ $order->address }}</div>
                    @if ($order->notes)<div class="text-slate-500">{{ $order->notes }}</div>@endif
                    <div class="pt-2 text-slate-500">{{ __('ops.fields.branch') }}: {{ $order->branch?->name }} | {{ $order->shippingCompany?->name }}</div>
                </div>
            </x-card>

            <x-card :title="__('orders.fields.payment')">
                <div class="space-y-2 text-sm">
                    <div>{{ $order->paymentMethod?->name }} <span class="badge {{ $order->payment_status === PaymentStatus::PAID ? 'badge-success' : 'badge-muted' }}">{{ $order->payment_status->label() }}</span></div>
                    @if ($order->prepay_method_id)
                        <div class="text-slate-600">{{ __('orders.prepaid_shipping') }}: <b>{{ number_format((float) $order->prepaid_shipping, 2) }}</b> ({{ $order->prepayMethod?->name }}) | {{ __('orders.cod_amount') }}: <b>{{ number_format((float) $order->cod_amount, 2) }}</b></div>
                    @elseif ((float) $order->cod_amount > 0)
                        <div class="text-slate-600">{{ __('orders.cod_amount') }}: <b>{{ number_format((float) $order->cod_amount, 2) }}</b></div>
                    @endif
                    @if ($order->payment_proof)
                        <a href="{{ route('tenant.orders.proof.file', $order->id) }}" target="_blank" class="text-brand-600 underline">{{ __('orders.view_proof') }}</a>
                        <span class="badge badge-muted">{{ $order->proof_status?->label() }}</span>
                        @if ($sharedProof->isNotEmpty())
                            <div class="rounded-lg border border-amber-300 bg-amber-50 p-2 text-xs text-amber-800" role="alert">
                                <b>{{ __('orders.proof_shared_warning') }}</b>
                                @foreach ($sharedProof as $other)
                                    <a href="{{ route('tenant.orders.show', $other->id) }}" class="underline">{{ $other->number }}</a>@if (! $loop->last), @endif
                                @endforeach
                            </div>
                        @endif
                        @if ($order->payment_status === PaymentStatus::REVIEW)
                            @can('orders.payments')
                            <div class="flex gap-2 pt-1">
                                <button type="button" class="btn-sm btn-primary" data-post-action="{{ route('tenant.orders.proof.approve', $order->id) }}" data-confirm="{{ __('ops.confirm_action') }}">{{ __('orders.approve') }}</button>
                                <button type="button" class="btn-sm btn-danger" data-post-action="{{ route('tenant.orders.proof.reject', $order->id) }}" data-confirm="{{ __('ops.confirm_action') }}">{{ __('orders.reject') }}</button>
                            </div>
                            @endcan
                        @endif
                    @endif
                </div>
            </x-card>

            <x-card :title="__('orders.history')">
                <ol class="space-y-2 text-sm">
                    @foreach ($order->histories as $h)
                        <li class="border-s-2 border-slate-200 ps-3">
                            <div class="font-medium">{{ $h->to_status->label() }}</div>
                            <div class="text-xs text-slate-500">{{ $h->created_at->format('Y-m-d H:i') }}@if ($h->user) - {{ $h->user->name }}@endif</div>
                            @if ($h->note)<div class="text-xs text-slate-600">{{ $h->note }}</div>@endif
                        </li>
                    @endforeach
                </ol>
            </x-card>
            @isset($activities)@include('tenant._activity', ['activities' => $activities])@endisset
        </div>
    </div>
</x-layouts.admin>
