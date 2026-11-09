<x-layouts.marketer :title="__('marketing.an.title')">
    @php
        $m = fn ($k, $r = []) => __('marketing.'.$k, $r);
        $money = fn ($v) => $v === null ? '—' : number_format((float) $v, 2);
        $pct = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 2), '0'), '.').'%';
        $s = $o['sales']; $p = $o['profit']; $gf = $metrics['gift_finder']; $cp = $metrics['coupons'];
        $name = fn ($n) => is_array($n) ? ($n[app()->getLocale()] ?? null) ?: collect($n)->filter()->first() ?: '—' : '—';
        $text = fn ($n) => is_array($n) ? $name($n) : ($n ?: '—');
        $th = 'py-2 text-start'; $thEnd = 'whitespace-nowrap py-2 ps-3 text-end';
    @endphp
    @include('reports._filter', ['f' => $f, 'branches' => [], 'slot' => new \Illuminate\Support\HtmlString(view('marketer.analytics._group-by', ['by' => $groupBy])->render())])
    <p class="mb-3 text-sm text-slate-500">{{ __('reports.amounts_in', ['c' => setting('currency_symbol') ?: setting('currency_code')]) }} · {{ $m('dash.sales_basis') }}</p>
    @foreach ($warnings as $w)
        @continue($w['code'] === 'no_ad_data')
        @php $wk = 'marketing.warnings.'.($w['code'] === 'missing_data' ? 'missing_'.($w['field'] ?? 'data') : $w['code']); @endphp
        <p class="mb-2 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800" role="status" data-warning="{{ $w['code'] }}">{{ Lang::has($wk) ? __($wk, ['p' => $w['coverage_percent'] ?? '']) : $w['message'] }}</p>
    @endforeach

    <div class="mb-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4" data-kpis>
        <x-stat-card :label="$m('dash.gross_sales')" :value="$money($s['gross_sales'])" class="[&_p:nth-child(2)]:!text-2xl" data-kpi="gross_sales" />
        <x-stat-card :label="$m('dash.orders')" :value="number_format($s['orders'])" tone="slate" class="[&_p:nth-child(2)]:!text-2xl" data-kpi="orders" />
        <x-stat-card :label="$m('dash.aov')" :value="$money($s['aov'])" tone="slate" class="[&_p:nth-child(2)]:!text-2xl" data-kpi="aov" />
        <x-stat-card :label="$m('dash.gross_profit')" :value="$money($p['gross_profit'])" tone="emerald" class="[&_p:nth-child(2)]:!text-2xl" data-kpi="gross_profit"
            :hint="$p['cost_coverage_percent'] === null ? $m('dash.no_sales') : $m('dash.coverage', ['p' => $p['cost_coverage_percent']])" />
        <x-stat-card :label="$m('an.margin')" :value="$pct($p['gross_margin_percent'])" tone="slate" class="[&_p:nth-child(2)]:!text-2xl" data-kpi="margin" />
        <x-stat-card :label="$m('an.discounts')" :value="$money($s['discounts'])" tone="slate" class="[&_p:nth-child(2)]:!text-2xl" data-kpi="discounts"
            :hint="$cp['discount_share_percent'] === null ? null : $m('an.discount_share', ['p' => $cp['discount_share_percent']])" />
        <x-stat-card :label="$m('an.new_customers')" :value="number_format($metrics['store']['new_customers'])" tone="slate" class="[&_p:nth-child(2)]:!text-2xl" data-kpi="new_customers" />
        <x-stat-card :label="$m('an.cancel_rate')" :value="$pct($orders['cancellations']['rate_percent'])" tone="slate" class="[&_p:nth-child(2)]:!text-2xl" data-kpi="cancel_rate"
            :hint="$m('an.cancelled_count', ['n' => $orders['cancellations']['total']])" />
    </div>

    <div class="grid gap-4 lg:grid-cols-2 [&>*]:min-w-0">
        <x-card :title="$m('an.sales_trend')" data-chart-card="sales">@include('reports._chart', ['chart' => $salesChart])</x-card>
        <x-card :title="$m('an.orders_trend')" data-chart-card="orders">@include('reports._chart', ['chart' => $ordersChart])</x-card>

        <details class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm lg:col-span-2" data-period-table>
            <summary class="cursor-pointer text-sm font-medium text-slate-700">{{ $m('an.period_table') }}</summary>
            <div class="mt-3 max-h-96 overflow-auto"><table class="w-full text-sm">
                <thead><tr class="border-b border-slate-200 text-slate-500"><th class="{{ $th }}">{{ $m('an.period') }}</th><th class="{{ $thEnd }}">{{ $m('dash.orders') }}</th><th class="{{ $thEnd }}">{{ $m('dash.gross_sales') }}</th><th class="{{ $thEnd }}">{{ $m('an.discounts') }}</th><th class="{{ $thEnd }}">{{ $m('dash.aov') }}</th></tr></thead>
                <tbody>@foreach ($periods as $r)<tr class="border-b border-slate-100"><td class="py-2" dir="ltr">{{ $r['period'] }}</td><td class="text-end">{{ number_format($r['orders']) }}</td><td class="text-end">{{ $money($r['gross_sales']) }}</td><td class="text-end">{{ $money($r['discounts']) }}</td><td class="text-end">{{ $money($r['aov']) }}</td></tr>@endforeach</tbody>
            </table></div>
        </details>

        @foreach (['by_status' => [$statuses, 'status'], 'by_channel' => [$channels, 'channel']] as $key => [$rows, $col])
            <x-card :title="$m('an.'.$key)">
                @if ($rows)
                    <div class="overflow-x-auto"><table class="w-full text-sm" data-table="{{ $key }}">
                        <thead><tr class="border-b border-slate-200 text-slate-500"><th class="{{ $th }}">{{ $m('an.'.$col) }}</th><th class="{{ $thEnd }}">{{ $m('an.count') }}</th><th class="{{ $thEnd }}">{{ $m('an.share') }}</th></tr></thead>
                        <tbody>@foreach ($rows as $r)
                            @php $ck = 'marketing.an.channels.'.($r['key'] ?? ''); @endphp
                            <tr class="border-b border-slate-100"><td class="py-2">{{ $r['label'] ?? (Lang::has($ck) ? __($ck) : $r['key']) }}</td><td class="text-end">{{ number_format($r['count']) }}</td><td class="text-end">{{ $pct($r['share']) }}</td></tr>
                        @endforeach</tbody>
                    </table></div>
                @else
                    <p class="py-6 text-center text-sm text-slate-400">{{ __('admin.no_data') }}</p>
                @endif
            </x-card>
        @endforeach

        <x-card :title="$m('an.candidates')" class="lg:col-span-2">
            <p class="mb-2 text-xs text-slate-500">{{ $m('an.candidates_hint') }}</p>
            @if ($candidates)
                <div class="overflow-x-auto"><table class="w-full text-sm" data-table="candidates">
                    <thead><tr class="border-b border-slate-200 text-slate-500"><th class="{{ $th }}">{{ $m('dash.product') }}</th><th class="{{ $thEnd }}">{{ $m('an.score') }}</th><th class="{{ $th }} ps-4">{{ $m('an.why') }}</th></tr></thead>
                    <tbody>@foreach ($candidates as $r)<tr class="border-b border-slate-100 align-top"><td class="py-2">{{ $name($r['name']) }} <span class="text-xs text-slate-400" dir="ltr">{{ $r['sku'] }}</span></td><td class="py-2 text-end font-semibold">{{ $r['score'] }}</td><td class="py-2 ps-4 text-slate-600">{{ $r['explanation'][app()->getLocale()] ?? $r['explanation']['en'] }}</td></tr>@endforeach</tbody>
                </table></div>
            @else
                <p class="py-6 text-center text-sm text-slate-400">{{ __('admin.no_data') }}</p>
            @endif
        </x-card>

        <x-card :title="$m('dash.top_sellers')" class="lg:col-span-2">
            @if ($best)
                <div class="overflow-x-auto"><table class="w-full text-sm" data-table="best">
                    <thead><tr class="border-b border-slate-200 text-slate-500"><th class="{{ $th }}">{{ $m('dash.product') }}</th><th class="{{ $thEnd }}">{{ $m('dash.units') }}</th><th class="{{ $thEnd }}">{{ $m('dash.revenue') }}</th><th class="{{ $thEnd }}">{{ $m('dash.gross_profit') }}</th><th class="{{ $thEnd }}">{{ $m('dash.available') }}</th></tr></thead>
                    <tbody>@foreach ($best as $r)<tr class="border-b border-slate-100"><td class="py-2">{{ $name($r['name']) }} <span class="text-xs text-slate-400" dir="ltr">{{ $r['sku'] }}</span></td><td class="text-end">{{ number_format($r['units_sold']) }}</td><td class="text-end">{{ $money($r['revenue']) }}</td><td class="text-end">{{ $money($r['gross_profit']) }}</td><td class="text-end">{{ number_format($r['stock']['available']) }}</td></tr>@endforeach</tbody>
                </table></div>
            @else
                <p class="py-6 text-center text-sm text-slate-400">{{ __('admin.no_data') }}</p>
            @endif
        </x-card>

        <x-card :title="$m('an.coupons')">
            @if ($cp['by_code'])
                <div class="overflow-x-auto"><table class="w-full text-sm" data-table="coupons">
                    <thead><tr class="border-b border-slate-200 text-slate-500"><th class="{{ $th }}">{{ $m('an.code') }}</th><th class="{{ $thEnd }}">{{ $m('an.usages') }}</th><th class="{{ $thEnd }}">{{ $m('an.discount') }}</th><th class="{{ $thEnd }}">{{ $m('an.orders_total') }}</th></tr></thead>
                    <tbody>@foreach ($cp['by_code'] as $c)<tr class="border-b border-slate-100"><td class="py-2" dir="ltr">{{ $c['code'] }}</td><td class="text-end">{{ number_format($c['usages']) }}</td><td class="text-end">{{ $money($c['discount']) }}</td><td class="text-end">{{ $money($c['orders_total']) }}</td></tr>@endforeach</tbody>
                </table></div>
            @else
                <p class="py-6 text-center text-sm text-slate-400">{{ __('admin.no_data') }}</p>
            @endif
        </x-card>

        <x-card :title="$m('an.flash_sales')">
            @if ($metrics['flash_sales'])
                <div class="overflow-x-auto"><table class="w-full text-sm" data-table="flash">
                    <thead><tr class="border-b border-slate-200 text-slate-500"><th class="{{ $th }}">{{ $m('an.name') }}</th><th class="{{ $thEnd }}">{{ $m('dash.orders') }}</th><th class="{{ $thEnd }}">{{ $m('dash.units') }}</th><th class="{{ $thEnd }}">{{ $m('dash.revenue') }}</th></tr></thead>
                    <tbody>@foreach ($metrics['flash_sales'] as $r)<tr class="border-b border-slate-100"><td class="py-2">{{ $text($r['name']) }}</td><td class="text-end">{{ number_format($r['orders']) }}</td><td class="text-end">{{ number_format($r['units']) }}</td><td class="text-end">{{ $money($r['revenue']) }}</td></tr>@endforeach</tbody>
                </table></div>
            @else
                <p class="py-6 text-center text-sm text-slate-400">{{ __('admin.no_data') }}</p>
            @endif
        </x-card>

        <x-card :title="$m('an.gift_finder')">
            <dl class="grid grid-cols-2 gap-3 text-sm" data-gift-funnel>
                <div><dt class="text-slate-500">{{ __('gift_finder.sessions') }}</dt><dd class="text-lg font-bold">{{ number_format($gf['sessions']) }}</dd></div>
                <div><dt class="text-slate-500">{{ __('gift_finder.cart_rate') }}</dt><dd class="text-lg font-bold">{{ $pct($gf['cart_rate']) }}</dd></div>
                <div><dt class="text-slate-500">{{ __('gift_finder.orders') }}</dt><dd class="text-lg font-bold">{{ number_format($gf['orders']) }}</dd></div>
                <div><dt class="text-slate-500">{{ __('gift_finder.order_rate') }}</dt><dd class="text-lg font-bold">{{ $pct($gf['order_rate']) }}</dd></div>
            </dl>
            <p class="mt-2 text-xs text-slate-400">{{ $m('dash.last_days', ['n' => $gf['days']]) }}</p>
        </x-card>

        <x-card :title="$m('an.returns').' / '.$m('an.ads')">
            <p class="text-sm text-slate-500">{{ $m('an.returns') }}</p>
            <p class="text-lg font-bold" data-returns>{{ $money($orders['returns']['value']) }}</p>
            <p class="mb-3 text-xs text-slate-400">{{ $m('an.returns_hint', ['n' => $orders['returns']['units']]) }}</p>
            <p class="text-sm text-slate-500" data-ads-note>{{ $m('an.ads_none') }}</p>
            @can('meta_ads.view')<a href="{{ route('marketer.meta-ads.index') }}" class="mt-1 inline-block text-sm text-brand-600 hover:underline">{{ $m('an.ads_open') }}</a>@endcan
        </x-card>
    </div>
</x-layouts.marketer>
