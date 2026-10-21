<x-layouts.admin guard="tenant" :title="__('admin.dashboard')">
    @if ($d)
        @include('reports._dashboard', ['d' => $d, 'f' => $f, 'guard' => 'tenant'])
    @else
        <x-card :title="$tenant->name"><p class="text-sm text-slate-500">{{ __('admin.welcome_back') }}</p></x-card>
    @endif
</x-layouts.admin>
