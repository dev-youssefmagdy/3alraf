@php($m = fn ($v) => \App\Support\Money::format((string) $v, $order->currency))
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; color: #1e293b; font-size: 12px; }
        h1 { margin: 0 0 4px; font-size: 22px; }
        .muted { color: #64748b; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 6px; border-bottom: 1px solid #e2e8f0; text-align: {{ $rtl ? 'right' : 'left' }}; }
        th { background: #f1f5f9; }
        .num { text-align: {{ $rtl ? 'left' : 'right' }}; }
        .tot td { border: 0; padding: 3px 6px; }
        .grand td { border-top: 1px solid #94a3b8; font-weight: bold; }
        .r { text-align: {{ $rtl ? 'right' : 'left' }}; }
    </style>
</head>
<body>
    <table style="margin-bottom:16px"><tr>
        <td style="border:0" class="r"><h1>{{ $t($tenant?->name) }}</h1>
            <div class="muted">{{ $t(__('orders.invoice')) }} #{{ $order->number }}<br>{{ $order->placed_at?->format('Y-m-d H:i') }}</div></td>
        <td style="border:0" class="r"><strong>{{ $t($order->customer_name) }}</strong><br>{{ $order->customer_phone }}<br>{{ $t(trim(($order->city?->name ?? '').' - '.$order->address, ' -')) }}</td>
    </tr></table>
    <table>
        <thead><tr><th>{{ $t(__('ops.fields.item')) }}</th><th class="num">{{ $t(__('orders.fields.unit_price')) }}</th><th class="num">{{ $t(__('ops.fields.quantity')) }}</th><th class="num">{{ $t(__('ops.fields.total')) }}</th></tr></thead>
        <tbody>@foreach ($order->items as $i)<tr><td>{{ $t($i->name) }}</td><td class="num">{{ number_format($i->unit_price, 2) }}</td><td class="num">{{ $i->quantity }}</td><td class="num">{{ number_format($i->line_subtotal, 2) }}</td></tr>@endforeach</tbody>
    </table>
    <table class="tot" style="width:55%;margin-top:14px;margin-{{ $rtl ? 'right' : 'left' }}:45%">
        <tr><td>{{ $t(__('orders.fields.subtotal')) }}</td><td class="num">{{ $m($order->subtotal) }}</td></tr>
        @if ($order->discount > 0)<tr><td>{{ $t(__('orders.fields.discount')) }}</td><td class="num">- {{ $m($order->discount) }}</td></tr>@endif
        <tr><td>{{ $t(__('orders.fields.shipping')) }}</td><td class="num">{{ $m($order->shipping_net) }}</td></tr>
        @if ($order->tax > 0)<tr><td>{{ $t(__('orders.fields.tax')) }}</td><td class="num">{{ $m($order->tax) }}</td></tr>@endif
        @if ($order->cod_fee > 0)<tr><td>{{ $t($order->codFeeLabel()) }}</td><td class="num">{{ $m($order->cod_fee) }}</td></tr>@endif
        @if ($order->payment_fee_charged > 0)<tr><td>{{ $t(__('orders.fields.payment_fee')) }}</td><td class="num">{{ $m($order->payment_fee_charged) }}</td></tr>@endif
        <tr class="grand"><td>{{ $t(__('ops.fields.total')) }}</td><td class="num">{{ $m($order->total) }}</td></tr>
        <tr><td>{{ $t(__('orders.fields.paid')) }}</td><td class="num">{{ $m($order->paid_amount) }}</td></tr>
        <tr><td>{{ $t(__('orders.fields.due')) }}</td><td class="num">{{ $m($order->due()) }}</td></tr>
    </table>
    <p class="muted r" style="margin-top:20px">{{ $t(($order->paymentMethod?->name ?? '').' - '.$order->payment_status->label().' - '.$order->status->label()) }}</p>
</body>
</html>
