<x-layouts.admin guard="tenant" :title="__('admin.dashboard')">
    @if (! empty($erp))
        <section class="mb-6" data-erp-kpis>
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-sm font-semibold text-slate-700">{{ __('erp_acc.home.title') }} <span class="font-normal text-slate-400">· {{ __('erp_acc.home.mtd') }}</span></h2>
                <a href="{{ route('tenant.erp.dashboard') }}" class="text-sm text-brand-600 hover:underline">{{ __('erp_acc.home.open') }} <span class="rtl:hidden">&rarr;</span><span class="ltr:hidden">&larr;</span></a>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach (['net_revenue' => 'brand', 'net_profit' => 'emerald', 'cash' => 'slate', 'receivable' => 'slate'] as $k => $tone)
                    <x-stat-card :label="__('erp_acc.home.'.$k)" :value="number_format((float) $erp[$k], 2)" :tone="$tone" data-erp-kpi="{{ $k }}" />
                @endforeach
            </div>
        </section>
    @endif
    @if ($d)
        @include('reports._dashboard', ['d' => $d, 'f' => $f, 'guard' => 'tenant'])
    @else
        <x-card :title="$tenant->name"><p class="text-sm text-slate-500">{{ __('admin.welcome_back') }}</p></x-card>
    @endif
</x-layouts.admin>
