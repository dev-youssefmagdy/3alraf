<x-layouts.admin guard="tenant" :title="__('notifications.settings.title')">
    <x-card class="max-w-xl">
        <form data-ajax-form data-method="PUT" action="{{ route('tenant.notification-settings.update') }}" method="POST" class="space-y-3" novalidate>
            @csrf
            <p class="text-sm text-slate-600">{{ __('notifications.settings.hint') }}</p>
            @foreach ($flags as $group => $on)
                <x-checkbox :name="$group" :label="__('notifications.settings.'.$group)" :checked="$on" />
            @endforeach
            <x-form-actions :cancel="route('tenant.dashboard')" />
        </form>
    </x-card>
</x-layouts.admin>
