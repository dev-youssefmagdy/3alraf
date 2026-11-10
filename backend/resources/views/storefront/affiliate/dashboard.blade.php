@php
    $c = $stats['commissions'];
    $b = $stats['bonus'];
    $cur = setting('currency_symbol');
    $num = fn ($v) => number_format((float) $v, 2);
    $whole = fn ($v) => number_format((float) $v, fmod((float) $v, 1) == 0.0 ? 0 : 2).' '.$cur; // program figures: "200 EGP for every 10,000 EGP"
    $showBonus = $b['enabled'] || $b['earned_count'] > 0 || $bonuses->isNotEmpty();
    $statusBadge = fn ($st) => ['paid' => 'badge-success', 'voided' => 'badge-danger'][$st->value] ?? 'badge-muted';
@endphp
<x-layouts.storefront :title="__('growth.dashboard')" :noindex="true">
    @include('storefront.affiliate._nav')
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card :label="__('growth.clicks')" :value="$stats['clicks']" />
        <x-stat-card :label="__('orders.fields.orders')" :value="$stats['orders']" :hint="__('growth.conversion').': '.$stats['conversion'].'%'" tone="slate" />
        <x-stat-card :label="__('growth.sales')" :value="number_format((float) $stats['sales'], 2)" :hint="__('growth.sales_hint')" tone="slate" />
        <x-stat-card :label="__('growth.current_tier')" :value="$stats['tier'] ? rtrim(rtrim((string) $stats['tier']->percent, '0'), '.').'%' : '0%'"
            :hint="$stats['next_tier'] ? __('growth.next_tier', ['percent' => rtrim(rtrim((string) $stats['next_tier']->percent, '0'), '.'), 'sales' => number_format((float) $stats['next_tier']->min_sales, 2)]) : ($stats['tier']?->name)" tone="emerald" />
    </div>
    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card :label="__('growth.commission_pending')" :value="number_format((float) $c['pending'], 2)" :hint="__('growth.pending_hint', ['days' => app(\App\Services\Affiliates\AffiliateCommissionService::class)->returnWindowDays()])" tone="slate" />
        <x-stat-card :label="__('growth.commission_approved')" :value="number_format((float) $c['approved'], 2)" tone="slate" />
        <x-stat-card :label="__('growth.commission_paid')" :value="number_format((float) $c['paid'], 2)" tone="emerald" />
        <x-card><p class="text-sm text-slate-500">{{ __('growth.available') }}</p><p class="mt-1 text-3xl font-bold text-brand-600">{{ number_format((float) $c['available'], 2) }}</p>
            @if ((float) ($c['available_bonuses'] ?? 0) > 0)<p class="mt-1 text-xs text-slate-400">{{ __('growth.available_split', ['commissions' => $num($c['available_commissions'] ?? 0), 'bonuses' => $num($c['available_bonuses'])]) }}</p>@endif
            @if ((float) $c['available'] > 0)<button type="button" data-post-action="{{ route('storefront.affiliate.payouts.request') }}" class="btn btn-primary mt-3">{{ __('growth.request_payout') }}</button>@endif</x-card>
    </div>
    @if ($showBonus)
        <x-card class="mt-6" :title="__('growth.bonus')" data-bonus-card>
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="min-w-0 lg:col-span-2">
                    @if ($b['enabled'])
                        <p class="font-semibold text-slate-800">{{ __('growth.bonus_rule', ['amount' => $whole($b['amount']), 'threshold' => $whole($b['threshold'])]) }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ __('growth.bonus_rule_note', ['days' => app(\App\Services\Affiliates\AffiliateCommissionService::class)->returnWindowDays(),
                            'date' => $b['started_at'] ? \Illuminate\Support\Carbon::parse($b['started_at'])->timezone(config('app.timezone'))->format('Y-m-d') : '—']) }}</p>
                        @php($done = __('growth.bonus_progress', ['done' => $num($b['toward_next']), 'threshold' => $num($b['threshold']).' '.$cur]))
                        <div class="mt-4 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 text-sm">
                            <span id="bonus-progress-label" class="font-medium text-slate-700">{{ $done }}</span>
                            <span class="text-slate-500">{{ (float) $b['remaining'] > 0 ? __('growth.bonus_remaining', ['remaining' => $num($b['remaining']).' '.$cur]) : '' }}</span>
                        </div>
                        <div class="mt-2 h-3 w-full overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-labelledby="bonus-progress-label"
                            aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $b['percent'] }}" aria-valuetext="{{ $done }} ({{ $b['percent'] }}%)">
                            <div class="h-full rounded-full bg-brand-600" style="width: {{ min(100, max(0, (float) $b['percent'])) }}%"></div>
                        </div>
                        @if ((float) $b['remaining'] <= 0)<p class="mt-2 text-sm font-medium text-emerald-700">{{ __('growth.bonus_due') }}</p>@endif
                    @else
                        <p class="text-sm text-slate-600">{{ __('growth.bonus_paused') }}</p>
                    @endif
                </div>
                <div class="rounded-lg bg-slate-50 p-4">
                    <p class="text-sm text-slate-500">{{ __('growth.bonuses_earned') }}</p>
                    <p class="mt-1 text-2xl font-bold text-emerald-600">{{ $num($b['earned_total']) }} <small class="text-sm font-medium">{{ $cur }}</small></p>
                    <p class="mt-1 text-xs text-slate-500">{{ trans_choice('growth.bonus_count', $b['earned_count'], ['count' => $b['earned_count']]) }}</p>
                </div>
            </div>
            <div class="mt-5 overflow-x-auto">
                <table class="w-full text-sm">
                    <caption class="sr-only">{{ __('growth.bonuses') }}</caption>
                    <thead><tr class="text-start text-xs text-slate-500">
                        <th scope="col" class="py-2 pe-2 text-start font-medium">{{ __('growth.bonus_milestone') }}</th>
                        <th scope="col" class="py-2 pe-2 text-start font-medium">{{ __('growth.fields.amount') }}</th>
                        <th scope="col" class="py-2 pe-2 text-start font-medium">{{ __('growth.date') }}</th>
                        <th scope="col" class="py-2 pe-2 text-start font-medium">{{ __('admin.status') }}</th>
                    </tr></thead>
                    <tbody>
                        @forelse ($bonuses->take(10) as $bn)
                            <tr class="border-t border-slate-100"><td class="py-2">#{{ $bn->milestone }}</td><td class="whitespace-nowrap">{{ $num($bn->amount) }}</td>
                                <td class="whitespace-nowrap">{{ $bn->created_at->format('Y-m-d') }}</td><td><span class="badge {{ $statusBadge($bn->status) }}">{{ $bn->status->label() }}</span></td></tr>
                        @empty
                            <tr class="border-t border-slate-100"><td colspan="4" class="py-2 text-slate-500">{{ __('growth.bonus_none') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif
    <div class="mt-6 grid gap-4 lg:grid-cols-2">
        <x-card class="min-w-0" :title="__('growth.commissions')">
            <div class="overflow-x-auto"><table class="w-full text-sm"><tbody>
                @forelse ($commissions as $m)
                    <tr class="border-t border-slate-100 first:border-0"><td class="py-2">{{ $m->order?->number }}</td><td>{{ number_format((float) $m->amount, 2) }} ({{ rtrim(rtrim((string) $m->percent, '0'), '.') }}%)</td><td><span class="badge badge-muted">{{ $m->status->label() }}</span></td></tr>
                @empty
                    <tr><td class="py-2 text-slate-500">{{ __('admin.no_data') }}</td></tr>
                @endforelse
            </tbody></table></div>
        </x-card>
        <x-card class="min-w-0" :title="__('growth.payouts')">
            {{-- commission / bonus split, stored on the payout (kept on rejected payouts) --}}
            <div class="overflow-x-auto"><table class="w-full text-sm">
                <thead><tr class="text-xs text-slate-500">
                    <th scope="col" class="py-2 pe-2 text-start font-medium">{{ __('growth.date') }}</th>
                    <th scope="col" class="py-2 pe-2 text-start font-medium">{{ __('growth.commissions') }}</th>
                    <th scope="col" class="py-2 pe-2 text-start font-medium">{{ __('growth.bonuses') }}</th>
                    <th scope="col" class="py-2 pe-2 text-start font-medium">{{ __('growth.fields.amount') }}</th>
                    <th scope="col" class="py-2 pe-2 text-start font-medium">{{ __('admin.status') }}</th>
                </tr></thead>
                <tbody>
                @forelse ($payouts as $p)
                    <tr class="border-t border-slate-100"><td class="whitespace-nowrap py-2 pe-2">{{ $p->created_at->format('Y-m-d') }}</td>
                        <td class="whitespace-nowrap pe-2">{{ $num($p->commission_amount) }}</td>
                        <td class="whitespace-nowrap pe-2">{{ $num($p->bonus_amount) }}</td>
                        <td class="whitespace-nowrap pe-2 font-semibold">{{ $num($p->amount) }}</td><td><span class="badge {{ $p->status === \App\Enums\PayoutStatus::PAID ? 'badge-success' : 'badge-muted' }}">{{ $p->status->label() }}</span></td></tr>
                @empty
                    <tr class="border-t border-slate-100"><td colspan="5" class="py-2 text-slate-500">{{ __('admin.no_data') }}</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </x-card>
    </div>
</x-layouts.storefront>
