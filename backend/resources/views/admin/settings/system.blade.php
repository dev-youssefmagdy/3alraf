@php
    $label = fn (string $k) => \Illuminate\Support\Facades\Lang::has("admin.system_settings.fields.$k") ? __("admin.system_settings.fields.$k") : \Illuminate\Support\Str::headline($k);
@endphp
<x-layouts.admin :title="__('admin.settings_system')">
    <form data-ajax-form data-method="PUT" data-validate-url="{{ route('admin.system-settings.validate') }}"
        action="{{ route('admin.system-settings.update') }}" method="POST" class="max-w-3xl space-y-6" novalidate>
        @csrf
        <p class="text-sm text-slate-600">{{ __('admin.system_settings.intro') }}</p>
        @foreach ($schema as $group => $items)
            <x-card :title="__('admin.system_settings.groups.'.$group)">
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ($items as $name => [$type])
                        @if ($type === 'bool')
                            <div class="sm:col-span-2"><x-checkbox :name="$name" :label="$label($name)" :checked="$values[$name]" /></div>
                        @elseif ($type === 'secret')
                            <div>
                                <x-input :name="$name" type="password" autocomplete="new-password" :label="$label($name)" value="" :placeholder="$secretSet[$name] ? __('admin.system_settings.secret_keep') : __('admin.system_settings.secret_empty')" />
                            </div>
                        @elseif ($type === 'int' || $type === 'float')
                            <div><x-input :name="$name" type="number" min="0" :step="$type === 'float' ? '0.01' : '1'" :label="$label($name)" :value="$values[$name]" required /></div>
                        @else
                            <div><x-input :name="$name" :label="$label($name)" :value="$values[$name]" /></div>
                        @endif
                    @endforeach
                </div>
            </x-card>
        @endforeach
        <x-form-actions :cancel="route('admin.dashboard')" />
    </form>
</x-layouts.admin>
