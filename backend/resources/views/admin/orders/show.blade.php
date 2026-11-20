@php
    use App\Enums\{OrderStatus, PaymentStatus, ProofStatus};
    $next = $order->status->next();
    $canReturn = $order->status === OrderStatus::DELIVERED;
    $money = fn ($v) => \App\Support\Money::format((string) $v, $order->currency);
@endphp
<x-layouts.admin :title="__('orders.order').' '.$order->number">
    <x-slot:header><x-ui.page-header :title="__('orders.order').' '.$order->number" /></x-slot:header>
    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-ui.card :title="__('orders.items')">
                <x-slot:actions>
                    <span class="badge {{ $order->status->color() }}">{{ $order->status->label() }}</span>
                    <a href="{{ route('admin.orders.invoice', $order->id) }}" target="_blank" class="btn-sm btn-secondary">{{ __('orders.invoice') }}</a>
                    <a href="{{ route('admin.orders.invoice.pdf', $order->id) }}" class="btn-sm btn-secondary">PDF</a>
                </x-slot:actions>
                <x-ui.table>
                        <thead><tr><th>{{ __('ops.fields.item') }}</th><th>{{ __('orders.fields.unit_price') }}</th><th>{{ __('ops.fields.quantity') }}</th><th>{{ __('orders.fields.discount') }}</th><th>{{ __('ops.fields.total') }}</th><th>{{ __('orders.fields.returned') }}</th></tr></thead>
                        <tbody>
                            @php $grp = null; @endphp
                            @foreach ($order->items as $i)
                                @if ($i->bundle_group && $i->bundle_group !== $grp)<tr class="bg-panel-surface-sunken"><td colspan="6" class="font-bold text-panel-accent-text">{{ __('orders.in_bundle') }}: {{ $i->bundle_name }}</td></tr>@endif
                                @php $grp = $i->bundle_group; @endphp
                                <tr><td data-label="" class="py-2 {{ $i->bundle_group ? 'ps-4' : '' }}">{{ $i->name }}<div class="text-xs text-panel-text-subtle">{{ $i->sku }}</div></td><td data-label="{{ __('orders.fields.unit_price') }}">{{ number_format($i->unit_price, 2) }}</td><td data-label="{{ __('ops.fields.quantity') }}">{{ $i->quantity }}</td><td data-label="{{ __('orders.fields.discount') }}">{{ number_format($i->line_discount, 2) }}</td><td data-label="{{ __('ops.fields.total') }}">{{ number_format($i->line_subtotal - $i->line_discount, 2) }}</td><td data-label="{{ __('orders.fields.returned') }}">{{ $i->returned_qty }}</td></tr>
                            @endforeach
                        </tbody>
                    </x-ui.table>
                @include('admin.orders._totals', ['order' => $order])
            </x-ui.card>

            @if ($canReturn)
                @can('returns.create')
                <x-ui.card :title="__('orders.create_return')">
                    <form data-ajax-form data-validate-url="{{ route('admin.orders.returns.validate', $order->id) }}" action="{{ route('admin.orders.returns.store', $order->id) }}" method="POST" class="space-y-3" novalidate>
                        @csrf
                        @foreach ($order->items as $i)
                            @continue($i->quantity - $i->returned_qty <= 0)
                            <div class="grid items-end gap-2 sm:grid-cols-12">
                                <input type="hidden" name="items[{{ $i->id }}][order_item_id]" value="{{ $i->id }}">
                                <div class="text-sm sm:col-span-9">@if ($i->bundle_name)<span class="text-xs font-bold text-panel-accent-text">[{{ $i->bundle_name }}]</span> @endif{{ $i->name }} <span class="text-xs text-panel-text-subtle">({{ __('orders.fields.returnable') }}: {{ $i->quantity - $i->returned_qty }})</span></div>
                                <x-ui.input class="sm:col-span-3" name="items[{{ $i->id }}][quantity]" type="number" min="0" max="{{ $i->quantity - $i->returned_qty }}" :value="0" />
                            </div>
                        @endforeach
                        <div class="grid gap-3 sm:grid-cols-3">
                            <x-ui.select name="branch_id" :label="__('ops.fields.branch')" :options="$branches" :value="$order->branch_id" />
                            <x-ui.input name="return_shipping_cost" type="number" step="0.01" min="0" :label="__('orders.fields.return_shipping_cost')" :value="0" />
                            <x-ui.input name="reason" :label="__('ops.fields.reason')" />
                            <x-ui.select name="reason_code" :label="__('orders.reason_code')" :options="\App\Enums\ReturnReasonCode::options()" :placeholder="'—'" />
                        </div>
                        <div class="flex flex-wrap gap-4">
                            <x-ui.checkbox name="restock" :label="__('orders.fields.restock')" :checked="true" />
                            <x-ui.checkbox name="refund_shipping" :label="__('orders.fields.refund_shipping')" :checked="false" />
                        </div>
                        <button class="btn btn-primary" type="submit">{{ __('orders.create_return') }}</button>
                    </form>
                </x-ui.card>
                @endcan
            @endif

            @if ($order->returns->isNotEmpty())
                <x-ui.card :title="__('orders.returns')">
                    @foreach ($order->returns as $r)
                        <div class="flex items-center justify-between border-t border-panel-border py-2 text-sm first:border-0">
                            <a class="font-semibold text-panel-accent-text" href="{{ route('admin.returns.show', $r->id) }}">{{ $r->number }}</a>
                            <span>{{ $money($r->return_value) }}</span><span class="badge badge-muted">{{ $r->status->label() }}</span>
                        </div>
                    @endforeach
                </x-ui.card>
            @endif
        </div>

        <div class="space-y-4">
            @if ($next)
                @can('orders.edit')
                <x-ui.card :title="__('orders.change_status')">
                    <form data-ajax-form data-validate-url="{{ route('admin.orders.status.validate', $order->id) }}" action="{{ route('admin.orders.status', $order->id) }}" method="POST" class="space-y-3" novalidate>
                        @csrf
                        <x-ui.select name="status" :options="collect($next)->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()" />
                        <x-ui.input name="note" :placeholder="__('ops.fields.note')" />
                        <button class="btn btn-primary w-full" type="submit">{{ __('orders.update') }}</button>
                    </form>
                </x-ui.card>
                @endcan
            @endif

            <x-ui.card :title="__('orders.fields.customer')">
                <dl class="divide-y divide-panel-border">
                    <x-ui.kv :label="__('orders.fields.customer')">{{ $order->customer_name }}@if ($order->customer) <a class="text-xs text-panel-accent-text" href="{{ route('admin.customers.show', $order->customer_id) }}">#{{ $order->customer_id }}</a>@endif</x-ui.kv>
                    <x-ui.kv :label="__('pos.recipient_phone')"><bdi dir="ltr">{{ $order->phone_display ?: $order->customer_phone }}</bdi>@if ($order->customer_email)<br>{{ $order->customer_email }}@endif</x-ui.kv>
                    <x-ui.kv :label="__('pos.city')" :value="$order->city?->name.' - '.$order->address" />
                    @if ($order->notes)<x-ui.kv :label="__('pos.notes')" :value="$order->notes" />@endif
                    <x-ui.kv :label="__('ops.fields.branch')" :value="$order->branch?->name.' | '.$order->shippingCompany?->name" />
                </dl>
            </div>
            </x-ui.card>

            <x-ui.card :title="__('orders.fields.payment')">
                <div class="space-y-2 text-sm">
                    <div>{{ $order->paymentMethod?->name }} <span class="badge {{ $order->payment_status === PaymentStatus::PAID ? 'badge-success' : 'badge-muted' }}">{{ $order->payment_status->label() }}</span></div>
                    @if ($order->prepay_method_id)
                        <div class="text-panel-text-muted">{{ __('orders.prepaid_shipping') }}: <b>{{ number_format((float) $order->prepaid_shipping, 2) }}</b> ({{ $order->prepayMethod?->name }}) | {{ __('orders.cod_amount') }}: <b>{{ number_format((float) $order->cod_amount, 2) }}</b></div>
                    @elseif ((float) $order->cod_amount > 0)
                        <div class="text-panel-text-muted">{{ __('orders.cod_amount') }}: <b>{{ number_format((float) $order->cod_amount, 2) }}</b></div>
                    @endif
                    @if ($order->payment_proof)
                        <a href="{{ route('admin.orders.proof.file', $order->id) }}" target="_blank" class="text-panel-accent-text underline">{{ __('orders.view_proof') }}</a>
                        <span class="badge badge-muted">{{ $order->proof_status?->label() }}</span>
                        @if ($sharedProof->isNotEmpty())
                            <div class="rounded-lg border border-panel-warning-solid bg-panel-warning-bg p-2 text-xs text-panel-warning-fg" role="alert">
                                <b>{{ __('orders.proof_shared_warning') }}</b>
                                @foreach ($sharedProof as $other)
                                    <a href="{{ route('admin.orders.show', $other->id) }}" class="underline">{{ $other->number }}</a>@if (! $loop->last), @endif
                                @endforeach
                            </div>
                        @endif
                        @if ($order->payment_status === PaymentStatus::REVIEW)
                            @can('orders.payments')
                            <div class="flex gap-2 pt-1">
                                <button type="button" class="btn-sm btn-primary" data-post-action="{{ route('admin.orders.proof.approve', $order->id) }}" data-confirm="{{ __('ops.confirm_action') }}">{{ __('orders.approve') }}</button>
                                <button type="button" class="btn-sm btn-danger" data-post-action="{{ route('admin.orders.proof.reject', $order->id) }}" data-confirm="{{ __('ops.confirm_action') }}">{{ __('orders.reject') }}</button>
                            </div>
                            @endcan
                        @endif
                    @endif
                </div>
            </x-ui.card>

            <x-ui.card :title="__('orders.history')">
                <ol>
                    @foreach ($order->histories as $h)
                        <x-ui.timeline-item :title="$h->to_status->label()" :time="$h->created_at->format('Y-m-d H:i').($h->user ? ' - '.$h->user->name : '')" :state="$loop->last ? 'current' : 'done'">
                            @if ($h->note)<p class="text-xs text-panel-text-muted">{{ $h->note }}</p>@endif
                        </x-ui.timeline-item>
                    @endforeach
                </ol>
            </x-ui.card>
            @isset($activities)@include('admin._activity', ['activities' => $activities])@endisset
        </div>
    </div>
</x-layouts.admin>
