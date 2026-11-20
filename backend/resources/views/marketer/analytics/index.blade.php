<x-layouts.marketer :title="__('marketing.an.title')">
    <x-slot:header><x-ui.page-header :title="__('marketing.an.title')" :subtitle="__('reports.amounts_in', ['c' => setting('currency_symbol') ?: setting('currency_code')]).' · '.__('marketing.dash.sales_basis')" /></x-slot:header>
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
    @foreach ($warnings as $w)
        @continue($w['code'] === 'no_ad_data')
        @php $wk = 'marketing.warnings.'.($w['code'] === 'missing_data' ? 'missing_'.($w['field'] ?? 'data') : $w['code']); @endphp
        <x-ui.alert tone="warning" class="mb-2" data-warning="{{ $w['code'] }}">{{ Lang::has($wk) ? __($wk, ['p' => $w['coverage_percent'] ?? '']) : $w['message'] }}</x-ui.alert>
    @endforeach

    <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4" data-kpis>
        <x-ui.stat-card size="compact" icon="wallet" :label="$m('dash.gross_sales')" :value="$money($s['gross_sales'])" data-kpi="gross_sales" />
        <x-ui.stat-card size="compact" :label="$m('dash.orders')" :value="number_format($s['orders'])" tone="slate" data-kpi="orders" />
        <x-ui.stat-card size="compact" :label="$m('dash.aov')" :value="$money($s['aov'])" tone="slate" data-kpi="aov" />
        <x-ui.stat-card size="compact" :label="$m('dash.gross_profit')" :value="$money($p['gross_profit'])" tone="emerald" data-kpi="gross_profit"
            :hint="$p['cost_coverage_percent'] === null ? $m('dash.no_sales') : $m('dash.coverage', ['p' => $p['cost_coverage_percent']])" />
        <x-ui.stat-card size="compact" :label="$m('an.margin')" :value="$pct($p['gross_margin_percent'])" tone="slate" data-kpi="margin" />
        <x-ui.stat-card size="compact" :label="$m('an.discounts')" :value="$money($s['discounts'])" tone="slate" data-kpi="discounts"
            :hint="$cp['discount_share_percent'] === null ? null : $m('an.discount_share', ['p' => $cp['discount_share_percent']])" />
        <x-ui.stat-card size="compact" :label="$m('an.new_customers')" :value="number_format($metrics['store']['new_customers'])" tone="slate" data-kpi="new_customers" />
        <x-ui.stat-card size="compact" :label="$m('an.cancel_rate')" :value="$pct($orders['cancellations']['rate_percent'])" tone="slate" data-kpi="cancel_rate"
            :hint="$m('an.cancelled_count', ['n' => $orders['cancellations']['total']])" />
    </div>

    <div class="grid gap-4 lg:grid-cols-2 [&>*]:min-w-0">
        <x-ui.card :title="$m('an.sales_trend')" data-chart-card="sales">@include('reports._chart', ['chart' => $salesChart])</x-ui.card>
        <x-ui.card :title="$m('an.orders_trend')" data-chart-card="orders">@include('reports._chart', ['chart' => $ordersChart])</x-ui.card>

        <details class="rounded-2xl border border-panel-border bg-panel-surface p-4 shadow-panel-xs lg:col-span-2" data-period-table>
            <summary class="cursor-pointer text-sm font-medium text-panel-text">{{ $m('an.period_table') }}</summary>
            <div class="mt-3 max-h-96 overflow-auto"><x-ui.table>
                <thead><tr><th>{{ $m('an.period') }}</th><th>{{ $m('dash.orders') }}</th><th>{{ $m('dash.gross_sales') }}</th><th>{{ $m('an.discounts') }}</th><th>{{ $m('dash.aov') }}</th></tr></thead>
                <tbody>@foreach ($periods as $r)<tr><td data-label="" dir="ltr">{{ $r['period'] }}</td><td data-label="{{ $m('dash.orders') }}">{{ number_format($r['orders']) }}</td><td data-label="{{ $m('dash.gross_sales') }}">{{ $money($r['gross_sales']) }}</td><td data-label="{{ $m('an.discounts') }}">{{ $money($r['discounts']) }}</td><td data-label="{{ $m('dash.aov') }}">{{ $money($r['aov']) }}</td></tr>@endforeach</tbody>
            </x-ui.table>
        </details>

        @foreach (['by_status' => [$statuses, 'status'], 'by_channel' => [$channels, 'channel']] as $key => [$rows, $col])
            <x-ui.card :title="$m('an.'.$key)">
                @if ($rows)
                    <x-ui.table data-table="{{ $key }}">
                        <thead><tr><th>{{ $m('an.'.$col) }}</th><th>{{ $m('an.count') }}</th><th>{{ $m('an.share') }}</th></tr></thead>
                        <tbody>@foreach ($rows as $r)
                            @php $ck = 'marketing.an.channels.'.($r['key'] ?? ''); @endphp
                            <tr><td data-label="">{{ $r['label'] ?? (Lang::has($ck) ? __($ck) : $r['key']) }}</td><td data-label="{{ $m('an.count') }}">{{ number_format($r['count']) }}</td><td data-label="{{ $m('an.share') }}">{{ $pct($r['share']) }}</td></tr>
                        @endforeach</tbody>
                    </x-ui.table>
                @else
                    <x-ui.empty-state kind="nodata" :title="__('admin.no_data')" message="" class="!py-6" />
                @endif
            </x-ui.card>
        @endforeach

        <x-ui.card :title="$m('an.candidates')" class="lg:col-span-2">
            <p class="mb-2 text-xs text-panel-text-muted">{{ $m('an.candidates_hint') }}</p>
            @if ($candidates)
                <x-ui.table data-table="candidates">
                    <thead><tr><th>{{ $m('dash.product') }}</th><th>{{ $m('an.score') }}</th><th>{{ $m('an.why') }}</th></tr></thead>
                    <tbody>@foreach ($candidates as $r)<tr><td data-label="">{{ $name($r['name']) }} <span class="text-xs text-panel-text-subtle" dir="ltr">{{ $r['sku'] }}</span></td><td data-label="{{ $m('an.score') }}">{{ $r['score'] }}</td><td data-label="{{ $m('an.why') }}">{{ $r['explanation'][app()->getLocale()] ?? $r['explanation']['en'] }}</td></tr>@endforeach</tbody>
                </x-ui.table>
            @else
                <x-ui.empty-state kind="nodata" :title="__('admin.no_data')" message="" class="!py-6" />
            @endif
        </x-ui.card>

        <x-ui.card :title="$m('dash.top_sellers')" class="lg:col-span-2">
            @if ($best)
                <x-ui.table data-table="best">
                    <thead><tr><th>{{ $m('dash.product') }}</th><th>{{ $m('dash.units') }}</th><th>{{ $m('dash.revenue') }}</th><th>{{ $m('dash.gross_profit') }}</th><th>{{ $m('dash.available') }}</th></tr></thead>
                    <tbody>@foreach ($best as $r)<tr><td data-label="">{{ $name($r['name']) }} <span class="text-xs text-panel-text-subtle" dir="ltr">{{ $r['sku'] }}</span></td><td data-label="{{ $m('dash.units') }}">{{ number_format($r['units_sold']) }}</td><td data-label="{{ $m('dash.revenue') }}">{{ $money($r['revenue']) }}</td><td data-label="{{ $m('dash.gross_profit') }}">{{ $money($r['gross_profit']) }}</td><td data-label="{{ $m('dash.available') }}">{{ number_format($r['stock']['available']) }}</td></tr>@endforeach</tbody>
                </x-ui.table>
            @else
                <x-ui.empty-state kind="nodata" :title="__('admin.no_data')" message="" class="!py-6" />
            @endif
        </x-ui.card>

        <x-ui.card :title="$m('an.coupons')">
            @if ($cp['by_code'])
                <x-ui.table data-table="coupons">
                    <thead><tr><th>{{ $m('an.code') }}</th><th>{{ $m('an.usages') }}</th><th>{{ $m('an.discount') }}</th><th>{{ $m('an.orders_total') }}</th></tr></thead>
                    <tbody>@foreach ($cp['by_code'] as $c)<tr><td data-label="" dir="ltr">{{ $c['code'] }}</td><td data-label="{{ $m('an.usages') }}">{{ number_format($c['usages']) }}</td><td data-label="{{ $m('an.discount') }}">{{ $money($c['discount']) }}</td><td data-label="{{ $m('an.orders_total') }}">{{ $money($c['orders_total']) }}</td></tr>@endforeach</tbody>
                </x-ui.table>
            @else
                <x-ui.empty-state kind="nodata" :title="__('admin.no_data')" message="" class="!py-6" />
            @endif
        </x-ui.card>

        <x-ui.card :title="$m('an.flash_sales')">
            @if ($metrics['flash_sales'])
                <x-ui.table data-table="flash">
                    <thead><tr><th>{{ $m('an.name') }}</th><th>{{ $m('dash.orders') }}</th><th>{{ $m('dash.units') }}</th><th>{{ $m('dash.revenue') }}</th></tr></thead>
                    <tbody>@foreach ($metrics['flash_sales'] as $r)<tr><td data-label="">{{ $text($r['name']) }}</td><td data-label="{{ $m('dash.orders') }}">{{ number_format($r['orders']) }}</td><td data-label="{{ $m('dash.units') }}">{{ number_format($r['units']) }}</td><td data-label="{{ $m('dash.revenue') }}">{{ $money($r['revenue']) }}</td></tr>@endforeach</tbody>
                </x-ui.table>
            @else
                <x-ui.empty-state kind="nodata" :title="__('admin.no_data')" message="" class="!py-6" />
            @endif
        </x-ui.card>

        <x-ui.card :title="$m('an.gift_finder')">
            <dl class="grid grid-cols-2 gap-3 text-sm" data-gift-funnel>
                <div><dt class="text-panel-text-muted">{{ __('gift_finder.sessions') }}</dt><dd class="text-lg font-bold">{{ number_format($gf['sessions']) }}</dd></div>
                <div><dt class="text-panel-text-muted">{{ __('gift_finder.cart_rate') }}</dt><dd class="text-lg font-bold">{{ $pct($gf['cart_rate']) }}</dd></div>
                <div><dt class="text-panel-text-muted">{{ __('gift_finder.orders') }}</dt><dd class="text-lg font-bold">{{ number_format($gf['orders']) }}</dd></div>
                <div><dt class="text-panel-text-muted">{{ __('gift_finder.order_rate') }}</dt><dd class="text-lg font-bold">{{ $pct($gf['order_rate']) }}</dd></div>
            </dl>
            <p class="mt-2 text-xs text-panel-text-subtle">{{ $m('dash.last_days', ['n' => $gf['days']]) }}</p>
        </x-ui.card>

        <x-ui.card :title="$m('an.returns').' / '.$m('an.ads')">
            <p class="text-sm text-panel-text-muted">{{ $m('an.returns') }}</p>
            <p class="text-lg font-bold" data-returns>{{ $money($orders['returns']['value']) }}</p>
            <p class="mb-3 text-xs text-panel-text-subtle">{{ $m('an.returns_hint', ['n' => $orders['returns']['units']]) }}</p>
            <p class="text-sm text-panel-text-muted" data-ads-note>{{ $m('an.ads_none') }}</p>
            @can('meta_ads.view')<a href="{{ route('marketer.meta-ads.index') }}" class="mt-1 inline-block text-sm text-panel-accent-text hover:underline">{{ $m('an.ads_open') }}</a>@endcan
        </x-ui.card>
    </div>
</x-layouts.marketer>
