@php
    use App\Enums\OrderStatus as S;
    $cur = setting('currency_symbol');
    $flow = [S::PENDING, S::CONFIRMED, S::PROCESSING, S::SHIPPED, S::DELIVERED];
    $idx = collect($flow)->search(fn ($s) => $s === $order->status);
    $mine = $order->customer_id && $order->customer_id === auth('web')->id();
    $payer = $order->payingMethod();
    $isWallet = $payer?->type?->value === 'wallet';
    $canPayOnline = $payer?->type?->value === 'online' && in_array($order->payment_status->value, ['unpaid', 'failed'], true) && ! in_array($order->status, [S::CANCELLED, S::RETURNED], true);
    $canProof = $isWallet && in_array($order->payment_status->value, ['unpaid', 'failed'], true) && ! in_array($order->status, [S::CANCELLED, S::RETURNED], true);
    $money = fn ($v) => number_format((float) $v, 2).' '.$cur;
    $svg = fn (string $d, string $cls = 'size-5') => '<svg class="'.$cls.' shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="'.$d.'"/></svg>';
    $statusBadge = fn ($s) => $s->color() === 'badge-muted' ? 'badge-warn' : $s->color();
    $payBadge = match ($order->payment_status->value) { 'paid' => 'badge-success', 'failed', 'refunded' => 'badge-danger', 'unpaid' => 'badge-warn', default => 'badge-muted' };
    $h2 = 'font-display text-[22px]/[30px] font-bold text-slate-950';
@endphp
<x-layouts.storefront :title="__('store.order_received').' '.$order->number" :noindex="true">
    {{-- design 26:6311 / 26:6655: mint success hero, timeline (vertical below 768), shipping + payment / summary + items, cancel --}}
    <div class="space-y-5 lg:space-y-6">
        <section class="relative overflow-hidden rounded-3xl bg-mint-400 px-5 pb-8 pt-7 text-center text-slate-950 lg:rounded-[32px] lg:py-10">
            <span class="pointer-events-none absolute -bottom-16 -start-16 size-44 rounded-full bg-sun-400 lg:-bottom-24 lg:-start-24 lg:size-72" aria-hidden="true"></span>
            <span class="pointer-events-none absolute end-6 top-5 size-10 rounded-full bg-pop-600 lg:end-10 lg:size-14" aria-hidden="true"></span>
            <span class="pointer-events-none absolute start-[24%] top-6 size-5 rounded-full bg-violet-500 max-sm:hidden" aria-hidden="true"></span>
            <div class="relative">
                <span class="mx-auto mb-4 grid size-16 place-items-center rounded-full bg-white text-emerald-700 shadow-[0_4px_0_var(--color-mint-700)] lg:size-[72px]">{!! $svg('m5 12 5 5L20 7', 'size-8') !!}</span>
                <h1 class="font-display text-[32px]/[40px] font-extrabold lg:text-[44px]/[52px]">{{ __('store.order_received') }}</h1>
                <p class="mt-1 text-sm/[22px] font-semibold lg:text-base">{{ __('store.thank_you') }}</p>
                <p class="mt-4 inline-flex items-center gap-2 rounded-full bg-white py-1.5 pe-1.5 ps-4 text-sm shadow-card">
                    <span>{{ __('store.order_number') }}:</span> <b class="font-display text-base font-extrabold"><bdi dir="ltr">{{ $order->number }}</bdi></b>
                    <button type="button" class="grid size-9 place-items-center rounded-full text-slate-600 transition hover:bg-slate-100 hover:text-brand-600" data-copy="{{ $order->number }}" aria-label="{{ __('store.copy_number') }}">{!! $svg('M9 9h11v11H9zM5 15H4V4h11v1', 'size-4') !!}</button>
                </p>
                <p class="mt-2 text-xs/[18px] font-semibold">{{ __('store.bookmark') }}</p>
                <div class="mt-5 flex flex-wrap justify-center gap-2">
                    <a class="btn btn-secondary inline-flex items-center gap-2 !border-white" href="{{ route('storefront.orders.show', ['token' => $order->public_token, 'invoice' => 1]) }}" target="_blank">{!! $svg('M6 9V3h12v6M6 18H4v-7h16v7h-2M8 14h8v7H8z', 'size-4') !!}{{ __('store.print_invoice') }}</a>
                    <a class="btn btn-secondary !border-white" href="{{ route('storefront.orders.invoice.pdf', ['token' => $order->public_token]) }}">PDF</a>
                    <a class="btn btn-primary" href="{{ route('storefront.home') }}">{{ __('store.continue_shopping') }}</a>
                </div>
            </div>
        </section>

        {{-- Order timeline (design 21:1935): done = emerald check, current = pop dot, upcoming = grey; a reached step's label is bold --}}
        <section class="panel lg:p-6" aria-labelledby="ord-timeline">
            <h2 id="ord-timeline" class="{{ $h2 }} mb-4">{{ __('store.timeline') }}</h2>
            @if ($idx === false)
                <span class="badge {{ $order->status->color() }} !px-3 !py-1 !text-sm">{{ $order->status->label() }}</span>
            @else
                <ol class="flex flex-col gap-0 md:flex-row md:items-start">
                    @foreach ($flow as $i => $s)
                        @php $done = $i < $idx || $idx === count($flow) - 1; $current = $i === $idx && ! $done; @endphp
                        <li class="relative flex items-center gap-3 pb-5 last:pb-0 md:flex-1 md:flex-col md:gap-2 md:pb-0 md:text-center" @if ($i === $idx) aria-current="step" @endif>
                            @if (! $loop->last)
                                <span class="absolute start-[15px] top-8 h-[calc(100%-2rem)] w-0.5 rounded-full md:start-[calc(50%+24px)] md:top-[15px] md:h-1 md:w-[calc(100%-48px)] {{ $i < $idx ? 'bg-emerald-700' : 'bg-slate-200' }}" aria-hidden="true"></span>
                            @endif
                            <span class="relative grid size-8 shrink-0 place-items-center rounded-full font-display text-sm font-extrabold {{ $done ? 'bg-emerald-700 text-white' : ($current ? 'bg-pop-600 text-white ring-4 ring-pop-100' : 'border-2 border-slate-200 bg-white text-slate-600') }}">
                                @if ($done){!! $svg('m5 12 5 5L20 7', 'size-4') !!}@else{{ $i + 1 }}@endif
                            </span>
                            <span class="{{ $i <= $idx ? 'font-bold text-slate-950' : 'text-slate-600' }} text-sm">{{ $s->label() }}</span>
                        </li>
                    @endforeach
                </ol>
            @endif
            @if ($order->histories->isNotEmpty())
                <ul class="mt-5 space-y-1 rounded-2xl bg-slate-100 px-4 py-3 text-xs/[20px] text-slate-600">
                    @foreach ($order->histories->sortByDesc('id') as $h)<li><bdi dir="ltr">{{ $h->created_at?->format('Y-m-d H:i') }}</bdi> - <b class="text-slate-950">{{ $h->to_status instanceof S ? $h->to_status->label() : $h->to_status }}</b> @if ($h->note)<span>({{ $h->note }})</span>@endif</li>@endforeach
                </ul>
            @endif
        </section>

        <div class="grid items-start gap-5 lg:grid-cols-2 lg:gap-6">
            <div class="space-y-5 lg:space-y-6">
                <section class="panel space-y-2 text-sm text-slate-600 lg:p-6" aria-labelledby="ord-ship">
                    <h2 id="ord-ship" class="{{ $h2 }} mb-1">{{ __('store.shipping_address') }}</h2>
                    <p><b class="text-slate-950">{{ $order->customer_name }}</b> - <bdi dir="ltr">{{ $order->phone_display ?: $order->customer_phone }}</bdi></p>
                    <p>{{ $order->city?->name }} - {{ $order->address }}</p>
                    @if ($order->shippingCompany)<p class="flex items-center gap-2">{!! $svg('M3 7h11v9H3zM14 10h4l3 3v3h-7M7 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4zM17 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4z', 'size-4 text-mint-700') !!}{{ $order->shippingCompany->name }}</p>@endif

                    <h2 class="{{ $h2 }} !mt-5 mb-1">{{ __('store.payment_method') }}</h2>
                    <p class="flex flex-wrap items-center gap-2"><b class="text-slate-950">{{ $order->paymentMethod?->name }}</b> <span class="badge {{ $payBadge }}">{{ $order->payment_status->label() }}</span></p>
                    @if ($order->prepay_method_id)<p>{{ __('orders.prepaid_shipping') }}: <b class="text-slate-950">{{ $money($order->prepaid_shipping) }}</b> ({{ $payer?->name }})</p>@endif
                    @if ($payer?->instructions)<p class="text-xs/[18px]">{{ $payer->instructions }}</p>@endif
                    @if ($isWallet && $payer->account_number)<p>{{ __('store.transfer_to') }}: <b class="text-slate-950"><bdi dir="ltr">{{ $payer->account_number }}</bdi></b></p>@endif
                    @if ($order->payment_proof)<p class="flex flex-wrap items-center gap-2"><a class="link" target="_blank" href="{{ route('storefront.orders.proof.file', $order->public_token) }}">{{ __('orders.view_proof') }}</a> <span class="badge badge-muted">{{ $order->proof_status?->label() }}</span></p>@endif
                    @if ($canPayOnline)
                        <button type="button" class="btn btn-primary mt-3" data-pay-action="{{ route('storefront.orders.pay', $order->public_token) }}">{{ __('orders.pay_now') }}</button>
                    @endif
                    @if ($canProof)
                        <form data-ajax-form action="{{ route('storefront.orders.proof', $order->public_token) }}" method="POST" enctype="multipart/form-data" class="!mt-4 space-y-3 rounded-2xl bg-brand-50 p-3" novalidate>
                            @csrf
                            <div data-field>
                                <label class="flex cursor-pointer flex-col items-center gap-1 rounded-2xl border-2 border-dashed border-brand-300 bg-white px-4 py-6 text-center transition hover:bg-brand-50 has-[:focus-visible]:ring-[3px] has-[:focus-visible]:ring-pop-500/45 has-[.is-invalid]:border-red-600">
                                    <span class="text-brand-600">{!! $svg('M12 16V4M7 9l5-5 5 5M4 20h16', 'size-6') !!}</span>
                                    <span class="font-bold text-brand-600" data-file-text>{{ __('store.upload_proof') }}</span>
                                    <input id="f-proof" type="file" name="proof" accept="image/*,.pdf" class="sr-only" data-file-label>
                                </label>
                            </div>
                            <div class="flex justify-end"><button class="btn btn-primary">{{ __('store.send') }}</button></div>
                        </form>
                    @endif
                </section>

                @if ($mine && app(\App\Services\Orders\CustomerCancellationService::class)->canCancel($order, auth('web')->user()))
                    <section class="rounded-3xl bg-red-50 p-4 sm:p-5 lg:p-6" aria-labelledby="ord-cancel">
                        <h2 id="ord-cancel" class="font-display text-[22px]/[30px] font-bold text-red-700">{{ __('orders.customer_cancel.title') }}</h2>
                        <form data-ajax-form action="{{ route('storefront.orders.cancel', $order->public_token) }}" method="POST" class="mt-3 space-y-3 text-sm" novalidate>
                            @csrf
                            <div data-field>
                                <label for="cancel-reason" class="form-label">{{ __('orders.customer_cancel.reason') }}</label>
                                <select id="cancel-reason" name="reason_code" class="form-control mt-1 w-full bg-white"><option value="">{{ __('orders.customer_cancel.reason') }}</option>@foreach (\App\Enums\ReturnReasonCode::options() as $c => $l)<option value="{{ $c }}">{{ $l }}</option>@endforeach</select>
                            </div>
                            <button class="btn btn-danger !bg-white hover:!bg-red-100">{{ __('orders.customer_cancel.button') }}</button>
                        </form>
                    </section>
                @endif
            </div>

            <div class="space-y-5 lg:space-y-6">
                <section class="panel lg:p-6" aria-labelledby="ord-summary">
                    <h2 id="ord-summary" class="{{ $h2 }} mb-3">{{ __('store.order_summary') }}</h2>
                    <dl class="space-y-2.5 text-sm text-slate-600 [&_dd]:whitespace-nowrap [&_dd]:font-semibold [&_dd]:text-slate-950">
                        <div class="flex justify-between gap-3"><dt>{{ __('store.subtotal') }}</dt><dd>{{ $money($order->subtotal) }}</dd></div>
                        @if ((float) $order->discount > 0)<div class="flex justify-between gap-3 text-emerald-700"><dt>{{ __('store.discount') }}</dt><dd class="!text-emerald-700">-{{ $money($order->discount) }}</dd></div>@endif
                        <div class="flex justify-between gap-3"><dt>{{ __('store.shipping') }}</dt><dd>{{ (float) $order->shipping_net > 0 ? $money($order->shipping_net) : __('store.free') }}</dd></div>
                        @if ((float) $order->packaging_fee > 0)<div class="flex justify-between gap-3"><dt>{{ __('store.packaging') }}: {{ $order->packagingLabel() }}</dt><dd>{{ $money($order->packaging_fee) }}</dd></div>@endif
                        @if ((float) $order->taxTotal() > 0)<div class="flex justify-between gap-3"><dt>{{ __('store.tax') }}</dt><dd>{{ $money($order->taxTotal()) }}</dd></div>@endif
                        @if ((float) $order->cod_fee > 0)<div class="flex justify-between gap-3"><dt>{{ $order->codFeeLabel() }}</dt><dd>{{ $money($order->cod_fee) }}</dd></div>@endif
                        @if ((float) $order->payment_fee_charged > 0)<div class="flex justify-between gap-3"><dt>{{ __('store.payment_fee') }}</dt><dd>{{ $money($order->payment_fee_charged) }}</dd></div>@endif
                        <div class="flex items-center justify-between gap-3 border-t border-slate-200 pt-3"><dt class="text-base font-bold text-slate-950">{{ __('store.total') }}</dt><dd><span class="font-display text-[26px]/[32px] font-extrabold text-brand-600 lg:text-[30px]/9">{{ $money($order->total) }}</span></dd></div>
                    </dl>
                    @if ($order->prepay_method_id)
                        <dl class="mt-3 space-y-1 rounded-xl bg-mint-100 px-3 py-2.5 text-xs font-semibold text-mint-700">
                            <div class="flex justify-between gap-3"><dt>{{ __('orders.prepaid_shipping') }}</dt><dd class="whitespace-nowrap font-bold">{{ $money($order->prepaid_shipping) }}</dd></div>
                            <div class="flex justify-between gap-3"><dt>{{ __('orders.cod_amount') }}</dt><dd class="whitespace-nowrap font-bold">{{ $money($order->cod_amount) }}</dd></div>
                        </dl>
                    @endif
                </section>

                <section class="panel lg:p-6" aria-labelledby="ord-items">
                    <h2 id="ord-items" class="{{ $h2 }} mb-2">{{ __('store.items') }}</h2>
                    @php $grp = null; @endphp
                    <div class="divide-y divide-slate-200 text-sm">
                        @foreach ($order->items as $it)
                            @if ($it->bundle_group && $it->bundle_group !== $grp)<p class="!border-t-0 pt-3"><span class="inline-flex items-center gap-1.5 rounded-full bg-accent-light px-3 py-1 text-xs font-bold text-accent-dark">{!! $svg('M21 8 12 3 3 8v8l9 5 9-5zM3 8l9 5 9-5M12 13v8', 'size-3.5') !!}{{ __('store.bundle') }}: {{ $it->bundle_name }}</span></p>@endif
                            @php $grp = $it->bundle_group; @endphp
                            <div class="flex items-start justify-between gap-3 py-3 {{ $it->bundle_group ? 'ps-4' : '' }}">
                                <span class="min-w-0 text-slate-950">{{ $it->name }} <span class="whitespace-nowrap text-slate-600">&times; {{ $it->quantity }}</span>@if ($it->returned_qty) <span class="badge badge-warn">{{ __('store.returned') }} {{ $it->returned_qty }}</span>@endif</span>
                                <b class="whitespace-nowrap text-slate-950">{{ $money($it->line_subtotal) }}</b>
                            </div>
                        @endforeach
                    </div>
                </section>
            </div>
        </div>

        {{-- Customer return requests hidden for now; flip to true to re-enable. --}}
        @if (false && $mine && $order->status === S::DELIVERED)
            <section class="panel">
                <h2 class="{{ $h2 }} mb-3">{{ __('store.request_return') }}</h2>
                <form data-ajax-form action="{{ route('storefront.orders.return', $order->public_token) }}" method="POST" class="space-y-3 text-sm" novalidate>
                    @csrf
                    @foreach ($order->items as $i => $it)
                        <div class="flex items-center justify-between gap-3"><span>@if ($it->bundle_name)<span class="text-xs font-bold text-brand-600">[{{ $it->bundle_name }}]</span> @endif{{ $it->name }}</span>
                            <input type="hidden" name="items[{{ $i }}][order_item_id]" value="{{ $it->id }}">
                            <input type="number" min="0" max="{{ $it->quantity - $it->returned_qty }}" value="0" name="items[{{ $i }}][quantity]" class="form-control w-24" aria-label="{{ __('store.return_qty') }}"></div>
                    @endforeach
                    <x-input name="reason" :label="__('store.return_reason')" />
                    <button class="btn btn-secondary">{{ __('store.request_return') }}</button>
                </form>
            </section>
        @endif
    </div>
    @if ($order->placed_at && $order->placed_at->gt(now()->subHours(2)))
        <script>
            (function () {
                if (!window.sfTrack) return;
                var k = 'sf_purchase_' + @js($order->number);
                try { if (localStorage.getItem(k)) return; localStorage.setItem(k, '1'); } catch (e) {}
                sfTrack('purchase', {transaction_id: @js($order->number), value: {{ (float) $order->total }}, items: @js($order->items->map(fn ($i) => ['id' => (string) $i->product_id, 'name' => $i->name, 'price' => (float) $i->unit_price, 'qty' => (int) $i->quantity])->values())});
            })();
        </script>
    @endif
</x-layouts.storefront>
