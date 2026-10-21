@php $editing = $admin->exists; @endphp
<x-layouts.admin :guard="$guard" :title="$editing ? __('admin.edit') : __('admin.add_new')">
    <x-card class="max-w-2xl">
        <form data-ajax-form @if ($editing) data-method="PUT" @endif data-validate-url="{{ route($guard.'.admins.validate', $editing ? [$admin->id] : []) }}"
            action="{{ $editing ? route($guard.'.admins.update', $admin->id) : route($guard.'.admins.store') }}" method="POST" class="space-y-4" novalidate>
            @csrf
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input name="name" :label="__('access.fields.name')" :value="$admin->name" required />
                <x-input name="email" type="email" :label="__('access.fields.email')" :value="$admin->email" required />
                <x-input name="password" type="password" autocomplete="new-password" :label="__('access.fields.password')" :required="! $editing" :hint="$editing ? __('access.password_hint') : null" />
            </div>
            <div data-field>
                <label class="form-label">{{ __('access.roles') }}</label>
                <div class="flex flex-wrap gap-x-5 gap-y-1">
                    @foreach ($roles as $id => $name)
                        <label class="inline-flex items-center gap-1.5 text-sm text-slate-700"><input type="checkbox" name="roles[]" value="{{ $id }}" @checked(in_array($id, $assigned)) class="size-4 rounded border-slate-300 text-brand-600"> {{ $name }}</label>
                    @endforeach
                </div>
            </div>
            <x-checkbox name="is_active" :label="__('admin.active')" :checked="$editing ? $admin->is_active : true" />
            <x-form-actions :cancel="route($guard.'.admins.index')" />
        </form>
    </x-card>
</x-layouts.admin>
