<x-layouts.admin guard="central" :title="__('admin.dashboard')">
    @if ($selected)
        <p class="mb-4 text-sm text-slate-500">{{ __('admin.viewing') }}: <strong>{{ $selected->name }}</strong></p>
    @endif
    <div class="mb-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <x-stat-card :label="__('admin.total_tenants')" :value="$tenantsCount" />
        <x-stat-card :label="__('admin.active_tenants')" :value="$activeTenants" tone="emerald" />
        <x-stat-card :label="__('admin.total_languages')" :value="$languagesCount" tone="slate" />
    </div>
    @if ($d)
        @include('reports._dashboard', ['d' => $d, 'f' => $f, 'guard' => 'central'])
    @endif
</x-layouts.admin>
