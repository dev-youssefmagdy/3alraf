@php
    $editing = $admin->exists;
    $countries = \App\Support\AdminPhone::countries();
    $selIso = $guard === 'tenant' ? (current_tenant()?->country ?? 'EG') : 'EG';
    $national = '';
    if ($admin->phone) { // split stored E.164 digits back into country + national number (longest dial code wins)
        foreach (collect($countries)->sortByDesc(fn ($d) => strlen($d)) as $iso => $dial) {
            if (str_starts_with($admin->phone, ltrim($dial, '+'))) { $selIso = $iso; $national = substr($admin->phone, strlen(ltrim($dial, '+'))); break; }
        }
    }
@endphp
<x-layouts.admin :guard="$guard" :title="$editing ? __('admin.edit') : __('admin.add_new')">
    <x-card class="max-w-2xl">
        <form data-ajax-form @if ($editing) data-method="PUT" @endif data-validate-url="{{ route($guard.'.admins.validate', $editing ? [$admin->id] : []) }}"
            action="{{ $editing ? route($guard.'.admins.update', $admin->id) : route($guard.'.admins.store') }}" method="POST" class="space-y-4" novalidate>
            @csrf
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input name="name" :label="__('access.fields.name')" :value="$admin->name" required />
                <x-input name="email" type="email" :label="__('access.fields.email')" :value="$admin->email" />
                <div data-field>
                    <label for="f-phone" class="form-label">{{ __('whatsapp.fields.phone') }}<span class="text-red-500">*</span></label>
                    <div class="flex" dir="ltr">
                        <select name="country" class="form-control w-28 shrink-0 rounded-e-none">@foreach ($countries as $iso => $dial)<option value="{{ $iso }}" @selected($iso === $selIso)>{{ $iso }} {{ $dial }}</option>@endforeach</select>
                        <input id="f-phone" type="tel" name="phone" value="{{ $national }}" class="form-control min-w-0 flex-1 rounded-s-none" required>
                    </div>
                </div>
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
