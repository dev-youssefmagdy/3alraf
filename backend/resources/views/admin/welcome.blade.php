{{-- Landing page after admin login: greeting + shortcut tiles to the pages this account can open (no data queries). --}}
@php
    $u = auth('admin')->user();
    $tiles = collect([
        ['admin.dashboard', __('admin.dashboard'), 'M3 12l9-9 9 9M5 10v10h14V10', null, 'brand'],
        ['admin.pos.index', __('pos.title'), 'M3 7h18v10H3zM3 11h18M7 15h2', 'pos.use', 'sun'],
        ['admin.orders.index', __('orders.orders'), 'M3 4h2l2 12h10l2-9H6M9 20h.01M17 20h.01', 'orders.view', 'mint'],
        ['admin.products.index', __('admin.products'), 'M20 7l-8-4-8 4m16 0v10l-8 4m8-14l-8 4m0 10L4 17V7m8 14V11', 'products.view', 'violet'],
        ['admin.customers.index', __('orders.customers'), 'M16 11a4 4 0 10-8 0 4 4 0 008 0zM4 21a8 8 0 0116 0', 'customers.view', 'brand'],
        ['admin.reports.index', __('reports.reports'), 'M4 19V9m5 10V5m5 14v-7m5 7V8', 'reports.view', 'mint'],
    ])->filter(fn ($t) => Route::has($t[0]) && (! $t[3] || $u->can($t[3])));
    $tone = ['brand' => 'bg-panel-accent-soft text-panel-accent-text', 'sun' => 'bg-panel-warning-bg text-panel-warning-fg', 'mint' => 'bg-panel-success-bg text-panel-success-fg', 'violet' => 'bg-panel-info-bg text-panel-info-fg'];
@endphp
<x-layouts.admin :title="__('admin.welcome')">
    <x-slot:header><x-ui.page-header :title="__('admin.welcome').'، '.$u->name" :subtitle="__('admin.welcome_back')" /></x-slot:header>
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:gap-5" data-welcome-tiles>
        @foreach ($tiles as [$route, $label, $icon, $perm, $t])
            <a href="{{ route($route) }}" class="flex min-h-28 flex-col justify-between rounded-3xl p-5 font-bold transition hover:-translate-y-0.5 hover:shadow-md {{ $tone[$t] }}">
                <svg class="size-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                <span>{{ $label }}</span>
            </a>
        @endforeach
    </div>
</x-layouts.admin>
