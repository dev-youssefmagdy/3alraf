@php($tenant = current_tenant())
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $tenant?->isRtl(app()->getLocale()) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('orders.invoice') }} {{ $order->number }}</title>
    <style>
        body { font-family: system-ui, sans-serif; color: #1e293b; margin: 0; background: #f1f5f9; }
        .sheet { max-width: 800px; margin: 24px auto; background: #fff; padding: 32px; border-radius: 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; font-size: 14px; }
        th, td { padding: 8px; border-bottom: 1px solid #e2e8f0; text-align: start; }
        .tot { margin-inline-start: auto; width: 280px; margin-top: 16px; font-size: 14px; }
        .tot div { display: flex; justify-content: space-between; padding: 3px 0; }
        .actions { max-width: 800px; margin: 16px auto 0; display: flex; gap: 8px; }
        button, .btn { padding: 8px 14px; border: 0; background: #dc2626; color: #fff; border-radius: 6px; cursor: pointer; font-size: 14px; text-decoration: none; }
        @media print { body { background: #fff; } .sheet { margin: 0; padding: 0; } .actions { display: none; } }
    </style>
</head>
<body>
    <div class="actions"><button onclick="window.print()">{{ __('orders.print') }}</button> <a class="btn" href="{{ ($public ?? false) ? route('storefront.orders.invoice.pdf', ['locale' => app()->getLocale(), 'token' => $order->public_token]) : route('tenant.orders.invoice.pdf', $order->id) }}">PDF</a></div>
    <div class="sheet">
        <div style="display:flex;justify-content:space-between;align-items:flex-start">
            <div><h1 style="margin:0">{{ $tenant?->name }}</h1><div style="color:#64748b;font-size:14px">{{ __('orders.invoice') }} #{{ $order->number }}<br>{{ $order->placed_at?->format('Y-m-d H:i') }}</div></div>
            <div style="text-align:end;font-size:14px"><strong>{{ $order->customer_name }}</strong><br>{{ $order->customer_phone }}<br>{{ $order->city?->name }} - {{ $order->address }}</div>
        </div>
        <table>
            <thead><tr><th>{{ __('ops.fields.item') }}</th><th>{{ __('orders.fields.unit_price') }}</th><th>{{ __('ops.fields.quantity') }}</th><th>{{ __('ops.fields.total') }}</th></tr></thead>
            <tbody>@foreach ($order->items as $i)<tr><td>{{ $i->name }}</td><td>{{ number_format($i->unit_price, 2) }}</td><td>{{ $i->quantity }}</td><td>{{ number_format($i->line_subtotal, 2) }}</td></tr>@endforeach</tbody>
        </table>
        <div class="tot">
            @php($m = fn ($v) => \App\Support\Money::format((string) $v, $order->currency))
            <div><span>{{ __('orders.fields.subtotal') }}</span><span>{{ $m($order->subtotal) }}</span></div>
            @if ($order->discount > 0)<div><span>{{ __('orders.fields.discount') }}</span><span>- {{ $m($order->discount) }}</span></div>@endif
            <div><span>{{ __('orders.fields.shipping') }}</span><span>{{ $m($order->shipping_net) }}</span></div>
            @if ($order->tax > 0)<div><span>{{ __('orders.fields.tax') }}</span><span>{{ $m($order->tax) }}</span></div>@endif
            @if ($order->payment_fee_charged > 0)<div><span>{{ __('orders.fields.payment_fee') }}</span><span>{{ $m($order->payment_fee_charged) }}</span></div>@endif
            <div style="font-weight:700;border-top:1px solid #cbd5e1"><span>{{ __('ops.fields.total') }}</span><span>{{ $m($order->total) }}</span></div>
            <div><span>{{ __('orders.fields.paid') }}</span><span>{{ $m($order->paid_amount) }}</span></div>
            <div><span>{{ __('orders.fields.due') }}</span><span>{{ $m($order->due()) }}</span></div>
        </div>
        <p style="margin-top:24px;font-size:13px;color:#64748b">{{ $order->paymentMethod?->name }} - {{ $order->payment_status->label() }} - {{ $order->status->label() }}</p>
        @if (($public ?? false) && $order->payingMethod()?->type === \App\Enums\PaymentMethodType::WALLET && ! in_array($order->payment_status, [\App\Enums\PaymentStatus::PAID, \App\Enums\PaymentStatus::REFUNDED]) && ! in_array($order->status, [\App\Enums\OrderStatus::CANCELLED]))
            <div class="actions" style="margin:12px 0 0;display:block;font-size:14px">
                <p>{{ $order->paymentMethod->instructions }} {{ $order->paymentMethod->account_number }}</p>
                <form data-ajax-form action="{{ route('storefront.orders.proof', ['locale' => app()->getLocale(), 'token' => $order->public_token]) }}" method="POST" enctype="multipart/form-data">
                    @csrf <input type="file" name="proof" accept="image/*,.pdf" required> <button type="submit">{{ __('orders.upload_proof') }}</button>
                </form>
            </div>
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </div>
</body>
</html>
