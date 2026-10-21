@php($m = fn ($v) => \App\Support\Money::format((string) $v, $order->currency))
<dl class="mt-4 ms-auto max-w-sm space-y-1 text-sm">
    <div class="flex justify-between"><dt class="text-slate-500">{{ __('orders.fields.subtotal') }}</dt><dd>{{ $m($order->subtotal) }}</dd></div>
    @if ($order->discount > 0)<div class="flex justify-between"><dt class="text-slate-500">{{ __('orders.fields.discount') }}@if ($order->discountCode) ({{ $order->discountCode->code }})@endif</dt><dd>- {{ $m($order->discount) }}</dd></div>@endif
    <div class="flex justify-between"><dt class="text-slate-500">{{ __('orders.fields.shipping') }}</dt><dd>{{ $m($order->shipping_net) }}</dd></div>
    @if ($order->tax > 0)<div class="flex justify-between"><dt class="text-slate-500">{{ __('orders.fields.tax') }}</dt><dd>{{ $m($order->tax) }}</dd></div>@endif
    @if ($order->payment_fee_charged > 0)<div class="flex justify-between"><dt class="text-slate-500">{{ __('orders.fields.payment_fee') }}</dt><dd>{{ $m($order->payment_fee_charged) }}</dd></div>@endif
    <div class="flex justify-between border-t border-slate-200 pt-1 font-semibold"><dt>{{ __('ops.fields.total') }}</dt><dd>{{ $m($order->total) }}</dd></div>
    <div class="flex justify-between"><dt class="text-slate-500">{{ __('orders.fields.paid') }}</dt><dd>{{ $m($order->paid_amount) }}</dd></div>
    <div class="flex justify-between"><dt class="text-slate-500">{{ __('orders.fields.due') }}</dt><dd>{{ $m($order->due()) }}</dd></div>
    @if ($order->refunded_amount > 0)<div class="flex justify-between"><dt class="text-slate-500">{{ __('orders.fields.refunded') }}</dt><dd>{{ $m($order->refunded_amount) }}</dd></div>@endif
</dl>
