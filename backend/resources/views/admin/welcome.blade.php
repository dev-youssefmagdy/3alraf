<x-layouts.admin :title="__('admin.welcome')">
    @can('marketing.panel')
        <a href="{{ route('marketer.dashboard') }}" class="block max-w-md rounded-xl border border-brand-200 bg-brand-50 p-5 shadow-sm hover:bg-brand-100" data-marketing-panel-link>
            <p class="text-base font-bold text-brand-800">{{ __('marketing.panel') }}</p>
            <p class="mt-1 text-sm text-brand-700">{{ __('marketing.welcome_hint') }}</p>
        </a>
    @endcan
</x-layouts.admin>
