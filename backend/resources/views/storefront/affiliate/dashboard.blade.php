@php($c = $stats['commissions'])
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
            @if ((float) $c['available'] > 0)<button type="button" data-post-action="{{ route('storefront.affiliate.payouts.request') }}" class="btn btn-primary mt-3">{{ __('growth.request_payout') }}</button>@endif</x-card>
    </div>
    <div class="mt-6 grid gap-4 lg:grid-cols-2">
        <x-card :title="__('growth.commissions')">
            <table class="w-full text-sm"><tbody>
                @forelse ($commissions as $m)
                    <tr class="border-t border-slate-100 first:border-0"><td class="py-2">{{ $m->order?->number }}</td><td>{{ number_format((float) $m->amount, 2) }} ({{ rtrim(rtrim((string) $m->percent, '0'), '.') }}%)</td><td><span class="badge badge-muted">{{ $m->status->label() }}</span></td></tr>
                @empty
                    <tr><td class="py-2 text-slate-500">{{ __('admin.no_data') }}</td></tr>
                @endforelse
            </tbody></table>
        </x-card>
        <x-card :title="__('growth.payouts')">
            <table class="w-full text-sm"><tbody>
                @forelse ($payouts as $p)
                    <tr class="border-t border-slate-100 first:border-0"><td class="py-2">{{ $p->created_at->format('Y-m-d') }}</td><td>{{ number_format((float) $p->amount, 2) }}</td><td><span class="badge badge-muted">{{ $p->status->label() }}</span></td></tr>
                @empty
                    <tr><td class="py-2 text-slate-500">{{ __('admin.no_data') }}</td></tr>
                @endforelse
            </tbody></table>
        </x-card>
    </div>
</x-layouts.storefront>
