@php
    $c = $stats['commissions'];
    $b = $stats['bonus'];
    $cur = setting('currency_symbol');
    $num = fn ($v) => number_format((float) $v, 2);
    $whole = fn ($v) => number_format((float) $v, fmod((float) $v, 1) == 0.0 ? 0 : 2).' '.$cur; // program figures: "200 EGP for every 10,000 EGP"
    $showBonus = $b['enabled'] || $b['earned_count'] > 0 || $bonuses->isNotEmpty();
    $statusTone = fn ($st) => ['paid' => 'success', 'voided' => 'danger', 'rejected' => 'danger', 'approved' => 'info', 'pending' => 'warning'][$st->value] ?? 'neutral';
    $windowDays = app(\App\Services\Affiliates\AffiliateCommissionService::class)->returnWindowDays();
    $pct = fn ($v) => rtrim(rtrim((string) $v, '0'), '.');
    $stats4 = [
        [__('growth.clicks'), number_format((int) $stats['clicks']), null, 'panel', 'link'],
        [__('orders.fields.orders'), number_format((int) $stats['orders']), __('growth.conversion').': '.$stats['conversion'].'%', 'violet', 'cart'],
        [__('growth.sales'), number_format((float) $stats['sales'], 2), __('growth.sales_hint'), 'sun', 'wallet'],
        [__('growth.current_tier'), $stats['tier'] ? $pct($stats['tier']->percent).'%' : '0%',
            $stats['next_tier'] ? __('growth.next_tier', ['percent' => $pct($stats['next_tier']->percent), 'sales' => number_format((float) $stats['next_tier']->min_sales, 2)]) : ($stats['tier']?->name), 'mint', 'star'],
    ];
    $commission4 = [
        [__('growth.commission_pending'), number_format((float) $c['pending'], 2), __('growth.pending_hint', ['days' => $windowDays]), 'sun', 'clock'],
        [__('growth.commission_approved'), number_format((float) $c['approved'], 2), null, 'violet', 'check-circle'],
        [__('growth.commission_paid'), number_format((float) $c['paid'], 2), null, 'mint', 'wallet'],
    ];
@endphp
<x-layouts.affiliate :title="__('growth.dashboard')">
    {{-- Affiliate dashboard (Figma Affiliate hero): header, balance hero card + 4 KPI cards, pending / approved / paid, sales-bonus progress + earned, bonuses, commissions + payouts as list rows
         (they stay readable on a phone, no sideways scroll). --}}
    <x-slot:header><x-ui.page-header :title="__('growth.dashboard')" :subtitle="$affiliate->name" /></x-slot:header>
    <div class="grid gap-3 md:gap-4 xl:grid-cols-3">
        <x-ui.balance-card class="xl:order-last" :label="__('growth.available')" :amount="number_format((float) $c['available'], 2)"
            :split="(float) ($c['available_bonuses'] ?? 0) > 0 ? __('growth.available_split', ['commissions' => $num($c['available_commissions'] ?? 0), 'bonuses' => $num($c['available_bonuses'])]) : null"
            :payoutUrl="route('storefront.affiliate.payouts.request')" :payoutLabel="__('growth.request_payout')" />
        <div class="grid grid-cols-2 gap-3 md:gap-4 xl:col-span-2" data-stats>
            @foreach ($stats4 as [$label, $value, $hint, $tone, $ic])
                <x-ui.stat-card :label="$label" :value="$value" :hint="$hint" :tone="$tone" :icon="$ic" />
            @endforeach
        </div>
    </div>
    <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3 md:mt-4 md:gap-4">
        @foreach ($commission4 as [$label, $value, $hint, $tone, $ic])
            <x-ui.stat-card :label="$label" :value="$value" :hint="$hint" :tone="$tone" :icon="$ic" size="compact" />
        @endforeach
    </div>
    @if ($showBonus)
        <div class="mt-4 grid gap-4 md:mt-6 lg:grid-cols-[minmax(0,1fr)_280px]" data-bonus-card>
            @if ($b['enabled'])
                <x-ui.progress id="bonus" tone="pop" :title="__('growth.bonus')" :rule="__('growth.bonus_rule', ['amount' => $whole($b['amount']), 'threshold' => $whole($b['threshold'])])"
                    :done="__('growth.bonus_progress', ['done' => $num($b['toward_next']), 'threshold' => $num($b['threshold']).' '.$cur])"
                    :remaining="(float) $b['remaining'] > 0 ? __('growth.bonus_remaining', ['remaining' => $num($b['remaining']).' '.$cur]) : null" :percent="$b['percent']">
                    <p class="mt-1 text-xs text-panel-text-subtle">{{ __('growth.bonus_rule_note', ['days' => $windowDays,
                        'date' => $b['started_at'] ? \Illuminate\Support\Carbon::parse($b['started_at'])->timezone(config('app.timezone'))->format('Y-m-d') : '—']) }}</p>
                    @if ((float) $b['remaining'] <= 0)<p class="mt-2 text-sm font-bold text-panel-success-fg">{{ __('growth.bonus_due') }}</p>@endif
                </x-ui.progress>
            @else
                <x-ui.card :title="__('growth.bonus')" id="bonus-t"><p class="text-sm text-panel-text-muted">{{ __('growth.bonus_paused') }}</p></x-ui.card>
            @endif
            <x-ui.stat-card tone="sun" icon="star" :label="__('growth.bonuses_earned')" :value="$num($b['earned_total']).' '.$cur" :hint="trans_choice('growth.bonus_count', $b['earned_count'], ['count' => $b['earned_count']])" />
        </div>
        <x-ui.card class="mt-4 md:mt-6" :title="__('growth.bonuses')" :flush="true" id="bonus-title">
            @forelse ($bonuses->take(10) as $bn)
                <x-ui.list-item :title="'#'.$bn->milestone" :subtitle="$bn->created_at->format('Y-m-d')">
                    <x-slot:trail><span class="text-sm font-bold tabular-nums" dir="ltr">{{ $num($bn->amount) }}</span><x-ui.badge :tone="$statusTone($bn->status)" dot>{{ $bn->status->label() }}</x-ui.badge></x-slot:trail>
                </x-ui.list-item>
            @empty
                <x-ui.empty-state kind="nodata" :title="__('growth.bonus_none')" message="" class="!py-8" />
            @endforelse
        </x-ui.card>
    @endif
    <div class="mt-4 grid items-start gap-4 md:mt-6 lg:grid-cols-2 lg:gap-6">
        <x-ui.card :title="__('growth.commissions')" :flush="true" id="commissions-title">
            @forelse ($commissions as $m)
                <x-ui.list-item :title="'#'.$m->order?->number" :subtitle="$pct($m->percent).'%'">
                    <x-slot:trail><span class="text-sm font-bold tabular-nums" dir="ltr">{{ number_format((float) $m->amount, 2) }}</span><x-ui.badge :tone="$statusTone($m->status)" dot>{{ $m->status->label() }}</x-ui.badge></x-slot:trail>
                </x-ui.list-item>
            @empty
                <x-ui.empty-state kind="nodata" :title="__('admin.no_data')" message="" class="!py-8" />
            @endforelse
        </x-ui.card>
        {{-- commission / bonus split, stored on the payout (kept on rejected payouts) --}}
        <x-ui.card :title="__('growth.payouts')" :flush="true" id="payouts-title">
            @forelse ($payouts as $p)
                <x-ui.list-item :title="$p->created_at->format('Y-m-d')" :subtitle="__('growth.commissions').' '.$num($p->commission_amount).' · '.__('growth.bonuses').' '.$num($p->bonus_amount)">
                    <x-slot:trail><span class="text-sm font-bold tabular-nums" dir="ltr">{{ $num($p->amount) }}</span><x-ui.badge :tone="$statusTone($p->status)" dot>{{ $p->status->label() }}</x-ui.badge></x-slot:trail>
                </x-ui.list-item>
            @empty
                <x-ui.empty-state kind="nodata" :title="__('admin.no_data')" message="" class="!py-8" />
            @endforelse
        </x-ui.card>
    </div>
</x-layouts.affiliate>
