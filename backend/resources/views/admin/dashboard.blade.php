<x-layouts.admin :title="__('admin.dashboard')">
    <x-slot:header>
        <x-ui.page-header :title="__('admin.welcome').'، '.auth('admin')->user()->name" :subtitle="now()->translatedFormat('l j F Y')">
            @can('products.create')<x-ui.button variant="secondary" icon="plus" :href="route('admin.products.create')">{{ __('admin.products') }}</x-ui.button>@endcan
            @can('pos.use')<x-ui.button icon="pos" :href="route('admin.pos.index')">{{ __('pos.title') }}</x-ui.button>@endcan
        </x-ui.page-header>
    </x-slot:header>
    @if (! empty($erp))
        <section class="mb-6" data-erp-kpis>
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-panel-h4 font-bold text-panel-text">{{ __('erp_acc.home.title') }} <span class="text-sm font-normal text-panel-text-subtle">· {{ __('erp_acc.home.mtd') }}</span></h2>
                <x-ui.link :href="route('admin.erp.dashboard')" style="standalone">{{ __('erp_acc.home.open') }}</x-ui.link>
            </div>
            <div class="grid grid-cols-2 gap-3 md:gap-4 xl:grid-cols-4">
                @foreach (['net_revenue' => ['panel', 'wallet'], 'net_profit' => ['mint', 'activity'], 'cash' => ['sun', 'receipt'], 'receivable' => ['violet', 'clock']] as $k => [$tone, $ic])
                    <x-ui.stat-card :label="__('erp_acc.home.'.$k)" :value="number_format((float) $erp[$k], 2)" :tone="$tone" :icon="$ic" data-erp-kpi="{{ $k }}" />
                @endforeach
            </div>
        </section>
    @endif
    @if ($d)
        @include('reports._dashboard', ['d' => $d, 'f' => $f, 'guard' => 'admin'])
    @else
        <x-ui.card :title="setting('name') ?: config('app.name')"><p class="text-sm text-panel-text-muted">{{ __('admin.welcome_back') }}</p></x-ui.card>
    @endif
</x-layouts.admin>
