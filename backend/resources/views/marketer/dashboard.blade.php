<x-layouts.marketer :title="__('marketing.dash.title')">
    @php
        $m = fn ($k, $r = []) => __('marketing.'.$k, $r);
        $money = fn ($v) => $v === null ? '—' : number_format((float) $v, 2);
        $s = $o['sales']; $p = $o['profit']; $mc = $o['merchandising']; $gf = $o['gift_finder'];
        $name = fn ($n) => is_array($n) ? ($n[app()->getLocale()] ?? null) ?: collect($n)->filter()->first() ?: '—' : '—';
        $merchIcons = ['active_coupons' => 'percent', 'running_flash_sales' => 'zap', 'live_banners' => 'image', 'live_bundles' => 'gift', 'low_stock_items' => 'alert'];
    @endphp
    <x-slot:header>
        <x-ui.page-header :title="__('admin.welcome').'، '.auth('marketer')->user()->name" :subtitle="$m('dash.title')">
            @can('ai_suggestions.view')<x-ui.button icon="sparkles" :href="route('marketer.suggestions.index')">{{ $m('groups.suggestions') }}</x-ui.button>@endcan
        </x-ui.page-header>
    </x-slot:header>

    @include('reports._filter', ['f' => $f, 'branches' => []])
    <p class="mb-3 text-panel-small text-panel-text-subtle">{{ __('reports.amounts_in', ['c' => setting('currency_symbol') ?: setting('currency_code')]) }} · {{ $m('dash.sales_basis') }}</p>

    @foreach ($warnings as $w)
        @php $wk = 'marketing.warnings.'.($w['code'] === 'missing_data' ? 'missing_'.($w['field'] ?? 'data') : $w['code']); @endphp
        <x-ui.alert tone="warning" class="mb-2" data-warning="{{ $w['code'] }}">{{ Lang::has($wk) ? __($wk, ['p' => $w['coverage_percent'] ?? '']) : $w['message'] }}</x-ui.alert>
    @endforeach

    <div class="mb-4 grid grid-cols-2 gap-3 md:gap-4 xl:grid-cols-4" data-kpis>
        <x-ui.stat-card :label="$m('dash.gross_sales')" :value="$money($s['gross_sales'])" tone="panel" icon="wallet" data-kpi="gross_sales" />
        <x-ui.stat-card :label="$m('dash.orders')" :value="number_format($s['orders'])" tone="mint" icon="cart" data-kpi="orders" />
        <x-ui.stat-card :label="$m('dash.aov')" :value="$money($s['aov'])" tone="sun" icon="receipt" data-kpi="aov" />
        @if ($withProfit)
            <x-ui.stat-card :label="$m('dash.gross_profit')" :value="$money($p['gross_profit'])" tone="violet" icon="chart" data-kpi="gross_profit"
                :hint="$p['cost_coverage_percent'] === null ? $m('dash.no_sales') : $m('dash.coverage', ['p' => $p['cost_coverage_percent']])" />
        @else
            <x-ui.stat-card :label="$m('dash.units_sold')" :value="number_format($s['units_sold'])" tone="slate" icon="box" />
        @endif
    </div>

    <div class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-3 md:gap-4 xl:grid-cols-5" data-merch>
        @foreach (['active_coupons' => 'discount-codes', 'running_flash_sales' => 'flash-sales', 'live_banners' => 'banners', 'live_bundles' => 'bundles', 'low_stock_items' => null] as $k => $uri)
            <x-ui.stat-card :label="$m('dash.'.$k)" :value="number_format($mc[$k])" :icon="$merchIcons[$k]" :tone="$k === 'low_stock_items' ? 'sun' : 'panel'" size="compact"
                :href="$uri && auth('marketer')->user()->can(str_replace('-', '_', $uri).'.view') ? route('marketer.'.$uri.'.index') : null" :hint="$uri && auth('marketer')->user()->can(str_replace('-', '_', $uri).'.view') ? $m('dash.manage') : null" />
        @endforeach
    </div>

    <div class="grid gap-4 xl:grid-cols-3">
        <x-ui.chart-card :title="$m('dash.sales_trend')" class="xl:col-span-2">@include('reports._chart', ['chart' => $chart])</x-ui.chart-card>

        <x-ui.card :title="$m('dash.gift_finder')">
            @if (! $gf['enabled'])<x-ui.alert tone="warning" class="mb-3">{{ __('gift_finder.disabled_hint') }}</x-ui.alert>@endif
            <dl class="divide-y divide-panel-border">
                <x-ui.kv :label="__('gift_finder.sessions')" :value="$gf['sessions']" />
                <x-ui.kv :label="__('gift_finder.cart_rate')" :value="$gf['cart_rate'] === null ? '—' : $gf['cart_rate'].'%'" />
                <x-ui.kv :label="__('gift_finder.orders')" :value="$gf['orders']" />
                <x-ui.kv :label="__('gift_finder.aov')" :value="$money($gf['orders_aov'])" kind="money" />
            </dl>
            <p class="mt-2 text-xs text-panel-text-subtle">{{ $m('dash.last_days', ['n' => $gf['days']]) }}</p>
            <x-slot:footer>@can('gift_finder.view')<x-ui.link :href="route('marketer.gift-finder.index')" style="standalone">{{ $m('dash.open') }}</x-ui.link>@endcan</x-slot:footer>
        </x-ui.card>

        <x-ui.card :title="$m('dash.top_sellers')" :flush="true" class="xl:col-span-2">
            @if ($best)
                <div class="overflow-x-auto"><table class="w-full text-sm" data-table="best">
                    <thead><tr class="border-b border-panel-border bg-panel-surface-sunken text-xs text-panel-text-muted"><th class="px-4 py-3 text-start font-bold">{{ $m('dash.product') }}</th><th class="px-3 text-end font-bold">{{ $m('dash.units') }}</th><th class="px-3 text-end font-bold">{{ $m('dash.revenue') }}</th>@if ($withProfit)<th class="px-3 text-end font-bold">{{ $m('dash.gross_profit') }}</th>@endif<th class="px-4 text-end font-bold">{{ $m('dash.available') }}</th></tr></thead>
                    <tbody>@foreach ($best as $r)<tr class="border-b border-panel-border last:border-0"><td class="px-4 py-3">{{ $name($r['name']) }} <span class="text-xs text-panel-text-subtle" dir="ltr">{{ $r['sku'] }}</span></td><td class="px-3 text-end tabular-nums">{{ $r['units_sold'] }}</td><td class="px-3 text-end tabular-nums">{{ $money($r['revenue']) }}</td>@if ($withProfit)<td class="px-3 text-end tabular-nums">{{ $money($r['gross_profit']) }}@if ($r['cost_coverage_percent'] !== null && $r['cost_coverage_percent'] < 100) <x-ui.badge tone="warning" size="sm" title="{{ $m('dash.coverage', ['p' => $r['cost_coverage_percent']]) }}">!</x-ui.badge>@endif</td>@endif<td class="px-4 text-end tabular-nums">{{ $r['stock']['available'] }}</td></tr>@endforeach</tbody>
                </table></div>
            @else
                <x-ui.empty-state kind="nodata" :title="__('admin.no_data')" message="" class="!py-8" />
            @endif
        </x-ui.card>

        <x-ui.card :title="$m('dash.suggestions')">
            @if ($pendingSuggestions !== null)
                <p class="text-panel-display font-bold tabular-nums text-panel-accent-text" data-pending-suggestions>{{ $pendingSuggestions }}</p>
                <x-ui.link :href="route('marketer.suggestions.index')" style="standalone" class="mt-2">{{ $m('dash.open') }}</x-ui.link>
            @else
                <p class="text-sm text-panel-text-muted" data-suggestions-placeholder>{{ $m('dash.suggestions_soon') }}</p>
            @endif
        </x-ui.card>

        <x-ui.card :title="$m('dash.slow_movers')" :flush="true" class="xl:col-span-2">
            @if ($slow)
                <div class="overflow-x-auto"><table class="w-full text-sm" data-table="slow">
                    <thead><tr class="border-b border-panel-border bg-panel-surface-sunken text-xs text-panel-text-muted"><th class="px-4 py-3 text-start font-bold">{{ $m('dash.product') }}</th><th class="px-3 text-end font-bold">{{ $m('dash.units') }}</th><th class="px-3 text-end font-bold">{{ $m('dash.available') }}</th><th class="px-4 text-end font-bold">{{ $m('dash.last_sale') }}</th></tr></thead>
                    <tbody>@foreach ($slow as $r)<tr class="border-b border-panel-border last:border-0"><td class="px-4 py-3">{{ $name($r['name']) }} <span class="text-xs text-panel-text-subtle" dir="ltr">{{ $r['sku'] }}</span></td><td class="px-3 text-end tabular-nums">{{ $r['units_sold'] }}</td><td class="px-3 text-end tabular-nums">{{ $r['stock']['available'] }}</td><td class="px-4 text-end">{{ $r['days_since_last_sale'] === null ? $m('dash.never') : $m('dash.days_ago', ['n' => $r['days_since_last_sale']]) }}</td></tr>@endforeach</tbody>
                </table></div>
            @else
                <x-ui.empty-state kind="nodata" :title="__('admin.no_data')" message="" class="!py-8" />
            @endif
        </x-ui.card>

        <x-ui.card :title="$m('dash.low_stock').' ('.$lowTotal.')'" :flush="true">
            @if ($low)
                <div data-table="low">
                    @foreach ($low as $r)
                        <x-ui.list-item :title="$name($r['name'])" :subtitle="$r['variant'] ?: null" class="!min-h-14">
                            <x-slot:trail><x-ui.badge :tone="$r['available'] === 0 ? 'danger' : 'warning'" dot><bdi dir="ltr">{{ $r['available'] }}</bdi></x-ui.badge></x-slot:trail>
                        </x-ui.list-item>
                    @endforeach
                </div>
            @else
                <x-ui.empty-state kind="nodata" :title="__('admin.no_data')" message="" class="!py-8" />
            @endif
        </x-ui.card>
    </div>
</x-layouts.marketer>
