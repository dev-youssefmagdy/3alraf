<x-layouts.marketer :title="__('marketing.dash.title')">
    @php
        $m = fn ($k, $r = []) => __('marketing.'.$k, $r);
        $money = fn ($v) => $v === null ? '—' : number_format((float) $v, 2);
        $s = $o['sales']; $p = $o['profit']; $mc = $o['merchandising']; $gf = $o['gift_finder'];
        $name = fn ($n) => is_array($n) ? ($n[app()->getLocale()] ?? null) ?: collect($n)->filter()->first() ?: '—' : '—';
    @endphp

    @include('reports._filter', ['f' => $f, 'branches' => []])
    <p class="mb-3 text-sm text-slate-500">{{ __('reports.amounts_in', ['c' => setting('currency_symbol') ?: setting('currency_code')]) }} · {{ $m('dash.sales_basis') }}</p>

    @foreach ($warnings as $w)
        @php $wk = 'marketing.warnings.'.($w['code'] === 'missing_data' ? 'missing_'.($w['field'] ?? 'data') : $w['code']); @endphp
        <p class="mb-2 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800" role="status" data-warning="{{ $w['code'] }}">{{ Lang::has($wk) ? __($wk, ['p' => $w['coverage_percent'] ?? '']) : $w['message'] }}</p>
    @endforeach

    <div class="mb-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4" data-kpis>
        <x-stat-card :label="$m('dash.gross_sales')" :value="$money($s['gross_sales'])" class="[&_p:nth-child(2)]:!text-2xl" data-kpi="gross_sales" />
        <x-stat-card :label="$m('dash.orders')" :value="number_format($s['orders'])" tone="slate" class="[&_p:nth-child(2)]:!text-2xl" data-kpi="orders" />
        <x-stat-card :label="$m('dash.aov')" :value="$money($s['aov'])" tone="slate" class="[&_p:nth-child(2)]:!text-2xl" data-kpi="aov" />
        @if ($withProfit)
            <x-stat-card :label="$m('dash.gross_profit')" :value="$money($p['gross_profit'])" tone="emerald" class="[&_p:nth-child(2)]:!text-2xl" data-kpi="gross_profit"
                :hint="$p['cost_coverage_percent'] === null ? $m('dash.no_sales') : $m('dash.coverage', ['p' => $p['cost_coverage_percent']])" />
        @else
            <x-stat-card :label="$m('dash.units_sold')" :value="number_format($s['units_sold'])" tone="slate" class="[&_p:nth-child(2)]:!text-2xl" />
        @endif
    </div>

    <div class="mb-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-5" data-merch>
        @foreach (['active_coupons' => 'admin.discount-codes.index', 'running_flash_sales' => 'admin.flash-sales.index', 'live_banners' => 'admin.banners.index', 'live_bundles' => 'admin.bundles.index', 'low_stock_items' => null] as $k => $route)
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-sm text-slate-500">{{ $m('dash.'.$k) }}</p>
                <p class="mt-1 text-2xl font-bold text-slate-700">{{ number_format($mc[$k]) }}</p>
                @if ($route && Route::has($route) && auth('admin')->user()->can(['admin.discount-codes.index' => 'discount_codes.view', 'admin.flash-sales.index' => 'flash_sales.view', 'admin.banners.index' => 'banners.view', 'admin.bundles.index' => 'bundles.view'][$route]))
                    <a href="{{ route($route) }}" class="mt-1 inline-block text-xs text-brand-600 hover:underline">{{ $m('dash.manage') }}</a>
                @endif
            </div>
        @endforeach
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <x-card :title="$m('dash.sales_trend')" class="lg:col-span-2">@include('reports._chart', ['chart' => $chart])</x-card>

        <x-card :title="$m('dash.gift_finder')">
            @if (! $gf['enabled'])<p class="mb-2 text-sm text-amber-700">{{ __('gift_finder.disabled_hint') }}</p>@endif
            <dl class="grid grid-cols-2 gap-3 text-sm">
                <div><dt class="text-slate-500">{{ __('gift_finder.sessions') }}</dt><dd class="text-lg font-bold">{{ $gf['sessions'] }}</dd></div>
                <div><dt class="text-slate-500">{{ __('gift_finder.cart_rate') }}</dt><dd class="text-lg font-bold">{{ $gf['cart_rate'] === null ? '—' : $gf['cart_rate'].'%' }}</dd></div>
                <div><dt class="text-slate-500">{{ __('gift_finder.orders') }}</dt><dd class="text-lg font-bold">{{ $gf['orders'] }}</dd></div>
                <div><dt class="text-slate-500">{{ __('gift_finder.aov') }}</dt><dd class="text-lg font-bold">{{ $money($gf['orders_aov']) }}</dd></div>
            </dl>
            <p class="mt-2 text-xs text-slate-400">{{ $m('dash.last_days', ['n' => $gf['days']]) }}</p>
            @can('gift_finder.view')<a href="{{ route('marketer.gift-finder.index') }}" class="mt-2 inline-block text-sm text-brand-600 hover:underline">{{ $m('dash.open') }}</a>@endcan
        </x-card>

        <x-card :title="$m('dash.top_sellers')" class="lg:col-span-2">
            @if ($best)
                <div class="overflow-x-auto"><table class="w-full text-sm" data-table="best">
                    <thead><tr class="border-b border-slate-200 text-start text-slate-500"><th class="py-2 text-start">{{ $m('dash.product') }}</th><th class="text-end">{{ $m('dash.units') }}</th><th class="text-end">{{ $m('dash.revenue') }}</th>@if ($withProfit)<th class="text-end">{{ $m('dash.gross_profit') }}</th>@endif<th class="text-end">{{ $m('dash.available') }}</th></tr></thead>
                    <tbody>@foreach ($best as $r)<tr class="border-b border-slate-100"><td class="py-2">{{ $name($r['name']) }} <span class="text-xs text-slate-400" dir="ltr">{{ $r['sku'] }}</span></td><td class="text-end">{{ $r['units_sold'] }}</td><td class="text-end">{{ $money($r['revenue']) }}</td>@if ($withProfit)<td class="text-end">{{ $money($r['gross_profit']) }}@if ($r['cost_coverage_percent'] !== null && $r['cost_coverage_percent'] < 100) <span class="badge badge-warn" title="{{ $m('dash.coverage', ['p' => $r['cost_coverage_percent']]) }}">!</span>@endif</td>@endif<td class="text-end">{{ $r['stock']['available'] }}</td></tr>@endforeach</tbody>
                </table></div>
            @else
                <p class="py-6 text-center text-sm text-slate-400">{{ __('admin.no_data') }}</p>
            @endif
        </x-card>

        <x-card :title="$m('dash.suggestions')">
            @if ($pendingSuggestions !== null)
                <p class="text-3xl font-bold text-brand-600" data-pending-suggestions>{{ $pendingSuggestions }}</p>
                <a href="{{ route('marketer.suggestions.index') }}" class="mt-2 inline-block text-sm text-brand-600 hover:underline">{{ $m('dash.open') }}</a>
            @else
                <p class="text-sm text-slate-500" data-suggestions-placeholder>{{ $m('dash.suggestions_soon') }}</p>
            @endif
        </x-card>

        <x-card :title="$m('dash.slow_movers')" class="lg:col-span-2">
            @if ($slow)
                <div class="overflow-x-auto"><table class="w-full text-sm" data-table="slow">
                    <thead><tr class="border-b border-slate-200 text-slate-500"><th class="py-2 text-start">{{ $m('dash.product') }}</th><th class="text-end">{{ $m('dash.units') }}</th><th class="text-end">{{ $m('dash.available') }}</th><th class="text-end">{{ $m('dash.last_sale') }}</th></tr></thead>
                    <tbody>@foreach ($slow as $r)<tr class="border-b border-slate-100"><td class="py-2">{{ $name($r['name']) }} <span class="text-xs text-slate-400" dir="ltr">{{ $r['sku'] }}</span></td><td class="text-end">{{ $r['units_sold'] }}</td><td class="text-end">{{ $r['stock']['available'] }}</td><td class="text-end">{{ $r['days_since_last_sale'] === null ? $m('dash.never') : $m('dash.days_ago', ['n' => $r['days_since_last_sale']]) }}</td></tr>@endforeach</tbody>
                </table></div>
            @else
                <p class="py-6 text-center text-sm text-slate-400">{{ __('admin.no_data') }}</p>
            @endif
        </x-card>

        <x-card :title="$m('dash.low_stock').' ('.$lowTotal.')'">
            @if ($low)
                <ul class="divide-y divide-slate-100 text-sm" data-table="low">
                    @foreach ($low as $r)<li class="flex justify-between gap-2 py-2"><span>{{ $name($r['name']) }}@if ($r['variant']) <span class="text-xs text-slate-400">{{ $r['variant'] }}</span>@endif</span><span class="font-semibold {{ $r['available'] === 0 ? 'text-red-600' : 'text-amber-700' }}">{{ $r['available'] }}</span></li>@endforeach
                </ul>
                @can('stock.view')<a href="{{ route('admin.stock.low') }}" class="mt-2 inline-block text-sm text-brand-600 hover:underline">{{ $m('dash.manage') }}</a>@endcan
            @else
                <p class="py-6 text-center text-sm text-slate-400">{{ __('admin.no_data') }}</p>
            @endif
        </x-card>
    </div>
</x-layouts.marketer>
