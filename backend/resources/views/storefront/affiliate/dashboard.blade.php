@php
    $c = $stats['commissions'];
    $b = $stats['bonus'];
    $cur = setting('currency_symbol');
    $num = fn ($v) => number_format((float) $v, 2);
    $whole = fn ($v) => number_format((float) $v, fmod((float) $v, 1) == 0.0 ? 0 : 2).' '.$cur; // program figures: "200 EGP for every 10,000 EGP"
    $showBonus = $b['enabled'] || $b['earned_count'] > 0 || $bonuses->isNotEmpty();
    $statusBadge = fn ($st) => ['paid' => 'badge-success', 'voided' => 'badge-danger', 'rejected' => 'badge-danger', 'approved' => 'bg-brand-50 text-brand-600'][$st->value] ?? 'badge-muted';
    $windowDays = app(\App\Services\Affiliates\AffiliateCommissionService::class)->returnWindowDays();
    $pct = fn ($v) => rtrim(rtrim((string) $v, '0'), '.');
    // stat card tones (design 21:2040): Brand / Mint / Sun; the available balance is the violet card with the payout button
    $tones = [
        'brand' => ['bg-brand-50', 'text-brand-600', 'text-brand-600'],
        'mint' => ['bg-mint-100', 'text-mint-700', 'text-mint-700'],
        'sun' => ['bg-sun-100', 'text-slate-950', 'text-slate-950'],
    ];
    $stats4 = [
        [__('growth.clicks'), number_format((int) $stats['clicks']), null, 'brand'],
        [__('orders.fields.orders'), number_format((int) $stats['orders']), __('growth.conversion').': '.$stats['conversion'].'%', 'brand'],
        [__('growth.sales'), number_format((float) $stats['sales'], 2), __('growth.sales_hint'), 'brand'],
        [__('growth.current_tier'), $stats['tier'] ? $pct($stats['tier']->percent).'%' : '0%',
            $stats['next_tier'] ? __('growth.next_tier', ['percent' => $pct($stats['next_tier']->percent), 'sales' => number_format((float) $stats['next_tier']->min_sales, 2)]) : ($stats['tier']?->name), 'mint'],
    ];
    $commission4 = [
        [__('growth.commission_pending'), number_format((float) $c['pending'], 2), __('growth.pending_hint', ['days' => $windowDays]), 'sun'],
        [__('growth.commission_approved'), number_format((float) $c['approved'], 2), null, 'brand'],
        [__('growth.commission_paid'), number_format((float) $c['paid'], 2), null, 'mint'],
    ];
    $th = 'px-3 py-2.5 text-start text-xs font-bold text-slate-600 first:rounded-s-xl last:rounded-e-xl';
@endphp
<x-layouts.affiliate :title="__('growth.dashboard')">
    {{-- Affiliate dashboard on the affiliate panel shell (Figma page 10): page header → 4 stat cards → pending / approved / paid + balance card
         (payout request) → sales-bonus progress + earned → bonuses → commissions + payouts. Tables scroll sideways on phones. --}}
    <x-slot:header><x-ui.page-header :title="__('growth.dashboard')" :subtitle="$affiliate->name" /></x-slot:header>
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-5" data-stats>
        @foreach ($stats4 as [$label, $value, $hint, $tone])
            <x-stat-card :label="$label" :value="$value" :hint="$hint" :tone="$tone" />
        @endforeach
    </div>
    <div class="mt-3 grid grid-cols-2 gap-3 lg:mt-5 lg:grid-cols-4 lg:gap-5">
        @foreach ($commission4 as [$label, $value, $hint, $tone])
            <x-stat-card :label="$label" :value="$value" :hint="$hint" :tone="$tone" />
        @endforeach
        <x-affiliate.balance-card :label="__('growth.available')" :amount="number_format((float) $c['available'], 2)"
            :split="(float) ($c['available_bonuses'] ?? 0) > 0 ? __('growth.available_split', ['commissions' => $num($c['available_commissions'] ?? 0), 'bonuses' => $num($c['available_bonuses'])]) : null"
            :payoutUrl="route('storefront.affiliate.payouts.request')" :payoutLabel="__('growth.request_payout')" />
    </div>
    @if ($showBonus)
        <div class="mt-5 grid gap-5 lg:mt-6 lg:grid-cols-[minmax(0,1fr)_260px]" data-bonus-card>
            @if ($b['enabled'])
                <x-ui.progress id="bonus" tone="pop" :title="__('growth.bonus')" :rule="__('growth.bonus_rule', ['amount' => $whole($b['amount']), 'threshold' => $whole($b['threshold'])])"
                    :done="__('growth.bonus_progress', ['done' => $num($b['toward_next']), 'threshold' => $num($b['threshold']).' '.$cur])"
                    :remaining="(float) $b['remaining'] > 0 ? __('growth.bonus_remaining', ['remaining' => $num($b['remaining']).' '.$cur]) : null" :percent="$b['percent']">
                    <p class="mt-1 text-xs text-slate-500">{{ __('growth.bonus_rule_note', ['days' => $windowDays,
                        'date' => $b['started_at'] ? \Illuminate\Support\Carbon::parse($b['started_at'])->timezone(config('app.timezone'))->format('Y-m-d') : '—']) }}</p>
                    @if ((float) $b['remaining'] <= 0)<p class="mt-2 text-sm font-bold text-emerald-700">{{ __('growth.bonus_due') }}</p>@endif
                </x-ui.progress>
            @else
                <section class="rounded-3xl border border-slate-200 bg-white p-5" aria-labelledby="bonus-t">
                    <h2 id="bonus-t" class="text-base font-bold text-slate-950">{{ __('growth.bonus') }}</h2>
                    <p class="mt-2 text-sm text-slate-600">{{ __('growth.bonus_paused') }}</p>
                </section>
            @endif
            <x-stat-card tone="sun" :label="__('growth.bonuses_earned')" :value="$num($b['earned_total']).' '.$cur" :hint="trans_choice('growth.bonus_count', $b['earned_count'], ['count' => $b['earned_count']])" />
        </div>
        <x-card class="mt-5 lg:mt-6" :title="__('growth.bonuses')" :flush="true">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[440px] text-sm text-slate-950 [&_td]:px-4 [&_td]:py-3">
                    <caption class="sr-only">{{ __('growth.bonuses') }}</caption>
                    <thead class="bg-slate-100"><tr>
                        <th scope="col" class="{{ $th }}">{{ __('growth.bonus_milestone') }}</th>
                        <th scope="col" class="{{ $th }}">{{ __('growth.fields.amount') }}</th>
                        <th scope="col" class="{{ $th }}">{{ __('growth.date') }}</th>
                        <th scope="col" class="{{ $th }}">{{ __('admin.status') }}</th>
                    </tr></thead>
                    <tbody>
                        @forelse ($bonuses->take(10) as $bn)
                            <tr class="border-b border-slate-100 last:border-0"><td>#{{ $bn->milestone }}</td><td class="whitespace-nowrap tabular-nums">{{ $num($bn->amount) }}</td>
                                <td class="whitespace-nowrap tabular-nums">{{ $bn->created_at->format('Y-m-d') }}</td><td><span class="badge {{ $statusBadge($bn->status) }}">{{ $bn->status->label() }}</span></td></tr>
                        @empty
                            <tr><td colspan="4" class="text-slate-600">{{ __('growth.bonus_none') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif
    <div class="mt-5 grid gap-5 lg:mt-6 lg:grid-cols-2 lg:gap-6">
        <section class="panel min-w-0" aria-labelledby="commissions-title">
            <h2 id="commissions-title" class="section-title mb-3">{{ __('growth.commissions') }}</h2>
            <div class="-mx-4 overflow-x-auto px-4 sm:-mx-5 sm:px-5">
                <table class="w-full min-w-[320px] text-sm text-slate-950 [&_td]:px-3 [&_td]:py-3">
                    <caption class="sr-only">{{ __('growth.commissions') }}</caption>
                    <thead class="bg-slate-100"><tr>
                        <th scope="col" class="{{ $th }}">{{ __('orders.fields.order') }}</th>
                        <th scope="col" class="{{ $th }}">{{ __('growth.fields.amount') }}</th>
                        <th scope="col" class="{{ $th }}">{{ __('admin.status') }}</th>
                    </tr></thead>
                    <tbody>
                    @forelse ($commissions as $m)
                        <tr class="border-b border-slate-100 last:border-0"><td class="py-2"><bdi>{{ $m->order?->number }}</bdi></td><td class="whitespace-nowrap tabular-nums">{{ number_format((float) $m->amount, 2) }} ({{ $pct($m->percent) }}%)</td><td><span class="badge {{ $statusBadge($m->status) }}">{{ $m->status->label() }}</span></td></tr>
                    @empty
                        <tr><td colspan="3" class="py-2 text-slate-600">{{ __('admin.no_data') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
        <section class="panel min-w-0" aria-labelledby="payouts-title">
            <h2 id="payouts-title" class="section-title mb-3">{{ __('growth.payouts') }}</h2>
            {{-- commission / bonus split, stored on the payout (kept on rejected payouts) --}}
            <div class="-mx-4 overflow-x-auto px-4 sm:-mx-5 sm:px-5">
                <table class="w-full min-w-[440px] text-sm text-slate-950 [&_td]:px-3 [&_td]:py-3">
                    <caption class="sr-only">{{ __('growth.payouts') }}</caption>
                    <thead class="bg-slate-100"><tr>
                        <th scope="col" class="{{ $th }}">{{ __('growth.date') }}</th>
                        <th scope="col" class="{{ $th }}">{{ __('growth.commissions') }}</th>
                        <th scope="col" class="{{ $th }}">{{ __('growth.bonuses') }}</th>
                        <th scope="col" class="{{ $th }}">{{ __('growth.fields.amount') }}</th>
                        <th scope="col" class="{{ $th }}">{{ __('admin.status') }}</th>
                    </tr></thead>
                    <tbody>
                    @forelse ($payouts as $p)
                        <tr class="border-b border-slate-100 tabular-nums last:border-0"><td class="whitespace-nowrap py-2 pe-2">{{ $p->created_at->format('Y-m-d') }}</td>
                            <td class="whitespace-nowrap pe-2">{{ $num($p->commission_amount) }}</td>
                            <td class="whitespace-nowrap pe-2">{{ $num($p->bonus_amount) }}</td>
                            <td class="whitespace-nowrap pe-2 font-bold">{{ $num($p->amount) }}</td><td><span class="badge {{ $statusBadge($p->status) }}">{{ $p->status->label() }}</span></td></tr>
                    @empty
                        <tr><td colspan="5" class="py-2 text-slate-600">{{ __('admin.no_data') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts.affiliate>
