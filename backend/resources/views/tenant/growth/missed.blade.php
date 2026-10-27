<x-layouts.admin guard="tenant" :title="__('growth_hub.missed.title')">
    @php $m = fn ($k) => __('growth_hub.missed.'.$k); @endphp
    <div class="space-y-4">
        <p class="text-sm text-slate-600">{{ $m('hint') }}</p>
        @can('growth.manage')<form data-ajax-form action="{{ route('tenant.growth.missed.scan') }}" method="POST" novalidate>@csrf<x-button type="submit">{{ $m('scan') }}</x-button></form>@endcan
        <x-card :title="$m('title')"><x-datatable :table="$table" :url="route('tenant.growth.missed.datatable')" /></x-card>
    </div>
    @include('tenant.growth._actions')
</x-layouts.admin>
