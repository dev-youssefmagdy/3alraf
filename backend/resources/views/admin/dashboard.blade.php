<x-layouts.admin :title="__('admin.dashboard')">
    <x-slot:header>
        <x-ui.page-header :title="__('admin.welcome').'، '.auth('admin')->user()->name" :subtitle="now()->translatedFormat('l j F Y')">
            @can('products.create')<x-button variant="secondary" :href="route('admin.products.create')">{{ __('admin.products') }} +</x-button>@endcan
            @can('pos.use')<x-button :href="route('admin.pos.index')">{{ __('pos.title') }}</x-button>@endcan
        </x-ui.page-header>
    </x-slot:header>
    @if (! empty($erp))
        <section class="mb-6" data-erp-kpis>
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-sm font-semibold text-slate-700">{{ __('erp_acc.home.title') }} <span class="font-normal text-slate-400">· {{ __('erp_acc.home.mtd') }}</span></h2>
                <a href="{{ route('admin.erp.dashboard') }}" class="text-sm text-brand-600 hover:underline">{{ __('erp_acc.home.open') }} <span class="rtl:hidden">&rarr;</span><span class="ltr:hidden">&larr;</span></a>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach (['net_revenue' => 'brand', 'net_profit' => 'mint', 'cash' => 'sun', 'receivable' => 'violet'] as $k => $tone)
                    <x-stat-card :label="__('erp_acc.home.'.$k)" :value="number_format((float) $erp[$k], 2)" :tone="$tone" data-erp-kpi="{{ $k }}" />
                @endforeach
            </div>
        </section>
    @endif
    @if ($d)
        @include('reports._dashboard', ['d' => $d, 'f' => $f, 'guard' => 'admin'])
    @else
        <x-card :title="setting('name') ?: config('app.name')"><p class="text-sm text-slate-500">{{ __('admin.welcome_back') }}</p></x-card>
    @endif
</x-layouts.admin>
