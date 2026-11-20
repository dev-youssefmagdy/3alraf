<x-layouts.admin :title="__('notifications.settings.title')">
    <x-slot:header><x-ui.page-header :title="__('notifications.settings.title')" /></x-slot:header>
    <x-ui.form-section :title="__('ui.sections.details')" :cols="1" class="mx-auto max-w-5xl">
        <form data-ajax-form data-method="PUT" action="{{ route('admin.notification-settings.update') }}" method="POST" class="space-y-3" novalidate>
            @csrf
            <p class="text-sm text-panel-text-muted">{{ __('notifications.settings.hint') }}</p>
            @foreach ($flags as $group => $on)
                <x-ui.checkbox :name="$group" :label="__('notifications.settings.'.$group)" :checked="$on" />
            @endforeach
            <x-ui.save-bar :cancel="route('admin.dashboard')" />
        </form>
    </x-ui.form-section>
</x-layouts.admin>
