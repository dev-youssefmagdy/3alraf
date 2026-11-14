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
<x-layouts.storefront :title="__('growth.dashboard')" :noindex="true">
    {{-- Affiliate dashboard (design 30:11831 / 30:12134): violet nav → 4 stat cards → pending / approved / paid + violet "available" card with the payout request
         → sales-bonus card (rule, progress bar role=progressbar, earned) → bonuses → commissions + payouts side by side. Tables scroll sideways on phones. --}}
    @include('storefront.affiliate._nav')
    <h1 class="sr-only">{{ __('growth.dashboard') }}</h1>
    @php($card = fn ($tone) => 'flex min-w-0 flex-col rounded-3xl p-4 lg:p-5 '.$tones[$tone][0])
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-5">
        @foreach ($stats4 as [$label, $value, $hint, $tone])
            <div class="{{ $card($tone) }}">
                <p class="text-xs font-bold lg:text-sm {{ $tones[$tone][1] }}">{{ $label }}</p>
                <p class="mt-1 font-display text-[26px]/9 font-extrabold tabular-nums [overflow-wrap:anywhere] lg:text-[30px]/9 {{ $tones[$tone][2] }}">{{ $value }}</p>
                @if ($hint)<p class="mt-1 text-xs/[18px] text-slate-600">{{ $hint }}</p>@endif
            </div>
        @endforeach
    </div>
    <div class="mt-3 grid grid-cols-2 gap-3 lg:mt-5 lg:grid-cols-4 lg:gap-5">
        @foreach ($commission4 as [$label, $value, $hint, $tone])
            <div class="{{ $card($tone) }}">
                <p class="text-xs font-bold lg:text-sm {{ $tones[$tone][1] }}">{{ $label }}</p>
                <p class="mt-1 font-display text-[26px]/9 font-extrabold tabular-nums [overflow-wrap:anywhere] lg:text-[30px]/9 {{ $tones[$tone][2] }}">{{ $value }}</p>
                @if ($hint)<p class="mt-1 text-xs/[18px] text-slate-600">{{ $hint }}</p>@endif
            </div>
        @endforeach
        <div class="flex min-w-0 flex-col rounded-3xl bg-violet-500 p-4 text-white lg:p-5">
            <p class="text-xs font-bold lg:text-sm">{{ __('growth.available') }}</p>
            <p class="mt-1 font-display text-[26px]/9 font-extrabold tabular-nums [overflow-wrap:anywhere] lg:text-[30px]/9">{{ number_format((float) $c['available'], 2) }}</p>
            @if ((float) ($c['available_bonuses'] ?? 0) > 0)<p class="mt-1 text-xs/[18px] text-white">{{ __('growth.available_split', ['commissions' => $num($c['available_commissions'] ?? 0), 'bonuses' => $num($c['available_bonuses'])]) }}</p>@endif
            @if ((float) $c['available'] > 0)<button type="button" data-post-action="{{ route('storefront.affiliate.payouts.request') }}" class="btn btn-sun mt-3 self-start">{{ __('growth.request_payout') }}</button>@endif
        </div>
    </div>
    @if ($showBonus)
        <section class="panel mt-5 lg:mt-6 lg:p-6" data-bonus-card aria-labelledby="bonus-title">
            <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center lg:gap-8">
                <div class="min-w-0">
                    <h2 id="bonus-title" class="section-title">{{ __('growth.bonus') }}</h2>
                    @if ($b['enabled'])
                        <p class="mt-2 text-sm/[22px] font-semibold text-slate-950">{{ __('growth.bonus_rule', ['amount' => $whole($b['amount']), 'threshold' => $whole($b['threshold'])]) }}</p>
                        <p class="mt-1 text-xs/[18px] text-slate-600">{{ __('growth.bonus_rule_note', ['days' => $windowDays,
                            'date' => $b['started_at'] ? \Illuminate\Support\Carbon::parse($b['started_at'])->timezone(config('app.timezone'))->format('Y-m-d') : '—']) }}</p>
                        @php($done = __('growth.bonus_progress', ['done' => $num($b['toward_next']), 'threshold' => $num($b['threshold']).' '.$cur]))
                        <div class="mt-4 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 text-xs font-bold">
                            <span id="bonus-progress-label" class="text-slate-950">{{ $done }}</span>
                            <span class="text-pop-600">{{ (float) $b['remaining'] > 0 ? __('growth.bonus_remaining', ['remaining' => $num($b['remaining']).' '.$cur]) : '' }}</span>
                        </div>
                        <div class="mt-2 h-3 w-full overflow-hidden rounded-full bg-pop-50" role="progressbar" aria-labelledby="bonus-progress-label"
                            aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $b['percent'] }}" aria-valuetext="{{ $done }} ({{ $b['percent'] }}%)">
                            <div class="h-full rounded-full bg-pop-600" style="width: {{ min(100, max(0, (float) $b['percent'])) }}%"></div>
                        </div>
                        @if ((float) $b['remaining'] <= 0)<p class="mt-2 text-sm font-bold text-emerald-700">{{ __('growth.bonus_due') }}</p>@endif
                    @else
                        <p class="mt-2 text-sm text-slate-600">{{ __('growth.bonus_paused') }}</p>
                    @endif
                </div>
                <div class="rounded-2xl bg-sun-100 p-4 sm:max-w-xs lg:min-w-[220px] lg:p-5">
                    <p class="text-sm font-bold text-slate-950">{{ __('growth.bonuses_earned') }}</p>
                    <p class="mt-1 font-display text-[30px]/9 font-extrabold tabular-nums text-slate-950">{{ $num($b['earned_total']) }} <small class="font-sans text-sm font-bold">{{ $cur }}</small></p>
                    <p class="mt-1 text-xs text-slate-700">{{ trans_choice('growth.bonus_count', $b['earned_count'], ['count' => $b['earned_count']]) }}</p>
                </div>
            </div>
        </section>
        <section class="panel mt-5 lg:mt-6" aria-labelledby="bonuses-title">
            <h2 id="bonuses-title" class="section-title mb-3">{{ __('growth.bonuses') }}</h2>
            <div class="-mx-4 overflow-x-auto px-4 sm:-mx-5 sm:px-5">
                <table class="w-full min-w-[440px] text-sm text-slate-950 [&_td]:px-3 [&_td]:py-3">
                    <caption class="sr-only">{{ __('growth.bonuses') }}</caption>
                    <thead class="bg-slate-100"><tr>
                        <th scope="col" class="{{ $th }}">{{ __('growth.bonus_milestone') }}</th>
                        <th scope="col" class="{{ $th }}">{{ __('growth.fields.amount') }}</th>
                        <th scope="col" class="{{ $th }}">{{ __('growth.date') }}</th>
                        <th scope="col" class="{{ $th }}">{{ __('admin.status') }}</th>
                    </tr></thead>
                    <tbody>
                        @forelse ($bonuses->take(10) as $bn)
                            <tr class="border-b border-slate-100 last:border-0"><td class="py-2">#{{ $bn->milestone }}</td><td class="whitespace-nowrap tabular-nums">{{ $num($bn->amount) }}</td>
                                <td class="whitespace-nowrap tabular-nums">{{ $bn->created_at->format('Y-m-d') }}</td><td><span class="badge {{ $statusBadge($bn->status) }}">{{ $bn->status->label() }}</span></td></tr>
                        @empty
                            <tr><td colspan="4" class="py-2 text-slate-600">{{ __('growth.bonus_none') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
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
</x-layouts.storefront>
