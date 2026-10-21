@php
    use App\Enums\OrderStatus as S;
    $cur = current_tenant()->currency_symbol;
    $flow = [S::PENDING, S::CONFIRMED, S::PROCESSING, S::SHIPPED, S::DELIVERED];
    $idx = collect($flow)->search(fn ($s) => $s === $order->status);
    $mine = $order->customer_id && $order->customer_id === auth('web')->id();
    $payer = $order->payingMethod();
    $isWallet = $payer?->type?->value === 'wallet';
    $canPayOnline = $payer?->type?->value === 'online' && in_array($order->payment_status->value, ['unpaid', 'failed'], true) && ! in_array($order->status, [S::CANCELLED, S::RETURNED], true);
    $canProof = $isWallet && in_array($order->payment_status->value, ['unpaid', 'failed'], true) && ! in_array($order->status, [S::CANCELLED, S::RETURNED], true);
    $money = fn ($v) => number_format((float) $v, 2).' '.$cur;
@endphp
<x-layouts.storefront :title="__('store.order_received').' '.$order->number" :noindex="true">
    <div class="mx-auto max-w-4xl space-y-5">
        <div class="rounded-2xl bg-white p-6 text-center ring-1 ring-slate-100">
            <div class="mx-auto mb-3 grid size-14 place-items-center rounded-full bg-emerald-50 text-3xl text-emerald-600">&#10003;</div>
            <h1 class="text-2xl font-extrabold">{{ __('store.order_received') }}</h1>
            <p class="mt-1 text-slate-500">{{ __('store.thank_you') }}</p>
            <p class="mt-3 text-lg">{{ __('store.order_number') }}: <b class="text-brand-600">{{ $order->number }}</b></p>
            <p class="mt-1 text-xs text-slate-400">{{ __('store.bookmark') }}</p>
            <div class="mt-4 flex flex-wrap justify-center gap-2">
                <a class="btn btn-secondary" href="{{ route('storefront.orders.show', ['token' => $order->public_token, 'invoice' => 1]) }}" target="_blank">{{ __('store.print_invoice') }}</a>
                <a class="btn btn-secondary" href="{{ route('storefront.orders.invoice.pdf', ['token' => $order->public_token]) }}">PDF</a>
                <a class="btn btn-primary" href="{{ route('storefront.home') }}">{{ __('store.continue_shopping') }}</a>
            </div>
        </div>

        <section class="rounded-2xl bg-white p-5 ring-1 ring-slate-100">
            <h2 class="mb-4 font-extrabold">{{ __('store.timeline') }}</h2>
            @if ($idx === false)
                <span class="badge {{ $order->status->color() }}">{{ $order->status->label() }}</span>
            @else
                <ol class="flex items-start justify-between gap-1 text-center text-xs">
                    @foreach ($flow as $i => $s)
                        <li class="flex-1"><span class="mx-auto mb-1 grid size-8 place-items-center rounded-full {{ $i <= $idx ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-400' }}">{{ $i <= $idx ? '✓' : $i + 1 }}</span>
                            <span class="{{ $i <= $idx ? 'font-bold text-slate-800' : 'text-slate-400' }}">{{ $s->label() }}</span></li>
                    @endforeach
                </ol>
            @endif
            <ul class="mt-4 space-y-1 border-t border-slate-100 pt-3 text-xs text-slate-500">
                @foreach ($order->histories->sortByDesc('id') as $h)<li>{{ $h->created_at?->format('Y-m-d H:i') }} - <b>{{ $h->to_status instanceof S ? $h->to_status->label() : $h->to_status }}</b> @if ($h->note)<span>({{ $h->note }})</span>@endif</li>@endforeach
            </ul>
        </section>

        <div class="grid gap-5 md:grid-cols-2">
            <section class="space-y-1 rounded-2xl bg-white p-5 text-sm ring-1 ring-slate-100">
                <h2 class="mb-2 font-extrabold">{{ __('store.shipping_address') }}</h2>
                <p><b>{{ $order->customer_name }}</b> - {{ $order->customer_phone }}</p>
                <p>{{ $order->city?->name }} - {{ $order->address }}</p>
                <p class="text-slate-500">{{ $order->shippingCompany?->name }}</p>
                <h2 class="mb-2 mt-4 font-extrabold">{{ __('store.payment_method') }}</h2>
                <p>{{ $order->paymentMethod?->name }} <span class="badge badge-muted">{{ $order->payment_status->label() }}</span></p>
                @if ($order->prepay_method_id)
                    <p>{{ __('orders.prepaid_shipping') }}: <b>{{ $money($order->prepaid_shipping) }}</b> ({{ $payer?->name }})</p>
                    <p>{{ __('orders.cod_amount') }}: <b>{{ $money($order->cod_amount) }}</b></p>
                @endif
                @if ($payer?->instructions)<p class="text-xs text-slate-500">{{ $payer->instructions }}</p>@endif
                @if ($isWallet && $payer->account_number)<p>{{ __('store.transfer_to') }}: <b>{{ $payer->account_number }}</b></p>@endif
                @if ($order->payment_proof)<a class="text-brand-600 underline" target="_blank" href="{{ route('storefront.orders.proof.file', $order->public_token) }}">{{ __('orders.view_proof') }}</a> <span class="badge badge-muted">{{ $order->proof_status?->label() }}</span>@endif
                @if ($canPayOnline)
                    <button type="button" class="btn btn-primary mt-3" data-pay-action="{{ route('storefront.orders.pay', $order->public_token) }}">{{ __('orders.pay_now') }}</button>
                @endif
                @if ($canProof)
                    <form data-ajax-form action="{{ route('storefront.orders.proof', $order->public_token) }}" method="POST" enctype="multipart/form-data" class="mt-3 space-y-2" novalidate>
                        @csrf
                        <x-input name="proof" type="file" accept="image/*,.pdf" :label="__('store.upload_proof')" />
                        <button class="btn btn-primary">{{ __('store.send') }}</button>
                    </form>
                @endif
            </section>

            <section class="rounded-2xl bg-white p-5 text-sm ring-1 ring-slate-100">
                <h2 class="mb-2 font-extrabold">{{ __('store.order_summary') }}</h2>
                <dl class="space-y-2">
                    <div class="flex justify-between"><dt>{{ __('store.subtotal') }}</dt><dd>{{ $money($order->subtotal) }}</dd></div>
                    @if ((float) $order->discount > 0)<div class="flex justify-between text-emerald-600"><dt>{{ __('store.discount') }}</dt><dd>-{{ $money($order->discount) }}</dd></div>@endif
                    <div class="flex justify-between"><dt>{{ __('store.shipping') }}</dt><dd>{{ (float) $order->shipping_net > 0 ? $money($order->shipping_net) : __('store.free') }}</dd></div>
                    @if ((float) $order->tax > 0)<div class="flex justify-between"><dt>{{ __('store.tax') }}</dt><dd>{{ $money($order->tax) }}</dd></div>@endif
                    @if ((float) $order->payment_fee_charged > 0)<div class="flex justify-between"><dt>{{ __('store.payment_fee') }}</dt><dd>{{ $money($order->payment_fee_charged) }}</dd></div>@endif
                    <div class="flex justify-between border-t border-slate-100 pt-2 text-base font-extrabold"><dt>{{ __('store.total') }}</dt><dd class="text-brand-600">{{ $money($order->total) }}</dd></div>
                </dl>
            </section>
        </div>

        <section class="rounded-2xl bg-white p-5 ring-1 ring-slate-100">
            <h2 class="mb-2 font-extrabold">{{ __('store.items') }}</h2>
            @foreach ($order->items as $it)
                <div class="flex justify-between gap-2 border-t border-slate-100 py-2 text-sm"><span>{{ $it->name }} <span class="text-slate-400">&times; {{ $it->quantity }}</span>@if ($it->returned_qty)<span class="badge badge-warn">{{ __('store.returned') }} {{ $it->returned_qty }}</span>@endif</span><b>{{ $money($it->line_subtotal) }}</b></div>
            @endforeach
        </section>

        @if ($mine && $order->status === S::DELIVERED)
            <section class="rounded-2xl bg-white p-5 ring-1 ring-slate-100">
                <h2 class="mb-3 font-extrabold">{{ __('store.request_return') }}</h2>
                <form data-ajax-form action="{{ route('storefront.orders.return', $order->public_token) }}" method="POST" class="space-y-3 text-sm" novalidate>
                    @csrf
                    @foreach ($order->items as $i => $it)
                        <div class="flex items-center justify-between gap-3"><span>{{ $it->name }}</span>
                            <input type="hidden" name="items[{{ $i }}][order_item_id]" value="{{ $it->id }}">
                            <input type="number" min="0" max="{{ $it->quantity - $it->returned_qty }}" value="0" name="items[{{ $i }}][quantity]" class="form-control w-24" aria-label="{{ __('store.return_qty') }}"></div>
                    @endforeach
                    <x-input name="reason" :label="__('store.return_reason')" />
                    <button class="btn btn-secondary">{{ __('store.request_return') }}</button>
                </form>
            </section>
        @endif
    </div>
</x-layouts.storefront>
