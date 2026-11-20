{{-- Marketer account form (marketers.manage). Phone is stored as E.164 digits; the login uses phone + password + WhatsApp OTP. --}}
@php
    $a = fn ($k) => __('marketing.accounts.'.$k);
    $editing = $m->exists;
    $countries = \App\Support\AdminPhone::countries();
    $selIso = strtoupper((string) setting('country', 'EG')) ?: 'EG';
    $national = '';
    if ($m->phone) {
        foreach (collect($countries)->sortByDesc(fn ($d) => strlen($d)) as $iso => $dial) {
            if (str_starts_with($m->phone, ltrim($dial, '+'))) { $selIso = $iso; $national = substr($m->phone, strlen(ltrim($dial, '+'))); break; }
        }
    }
@endphp
<x-layouts.marketer :title="$editing ? __('admin.edit').': '.$m->name : $a('add')">
    <x-slot:header><x-ui.page-header :title="$editing ? __('admin.edit').': '.$m->name : $a('add')" :breadcrumbs="[[$a('title'), route('marketer.accounts.index')]]" /></x-slot:header>
    <form data-ajax-form @if ($editing) data-method="PUT" @endif data-validate-url="{{ route('marketer.accounts.validate', $editing ? [$m->id] : []) }}"
        action="{{ $editing ? route('marketer.accounts.update', $m->id) : route('marketer.accounts.store') }}" method="POST" class="space-y-4" novalidate>
        @csrf
        <x-ui.form-section :title="__('access.fields.name')">
            <x-ui.input name="name" :label="__('access.fields.name')" :value="$m->name" required />
            <x-ui.input name="email" type="email" :label="__('access.fields.email')" :value="$m->email" />
            <x-ui.field name="phone" :label="__('whatsapp.fields.phone')" :required="true">
                <div dir="ltr">
                    <input type="hidden" name="country" value="{{ $selIso }}">
                    <input id="f-phone" type="tel" data-intl-phone="country" name="phone" value="{{ $national }}" class="form-control w-full" required>
                </div>
            </x-ui.field>
            <x-ui.input name="password" type="password" autocomplete="new-password" :label="__('access.fields.password')" :required="! $editing" :hint="$editing ? __('access.password_hint') : null" />
        </x-ui.form-section>
        <x-ui.form-section :title="__('access.fields.roles')" :cols="1">
            <div data-field>
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($roles as $r)
                        <label class="flex min-h-11 cursor-pointer items-start gap-3 rounded-xl border border-panel-border p-3 text-sm has-[:checked]:border-panel-accent has-[:checked]:bg-panel-accent-soft">
                            <input type="radio" name="role" value="{{ $r }}" @checked($role === $r) class="mt-0.5 size-5 shrink-0 border-panel-border-strong">
                            <span><span class="font-semibold text-panel-text">{{ $a('roles.'.$r) }}</span><span class="block text-xs text-panel-text-muted">{{ $a('role_hints.'.$r) }}</span></span>
                        </label>
                    @endforeach
                </div>
            </div>
            <x-ui.checkbox name="is_active" :label="__('admin.active')" :checked="$editing ? $m->is_active : true" />
            <x-ui.checkbox name="otp_exempt" :label="__('access.otp_exempt')" :help="__('access.otp_exempt_hint')" :checked="$editing && $m->otp_exempt" />
        </x-ui.form-section>
        <x-ui.save-bar :cancel="route('marketer.accounts.index')" />
    </form>
</x-layouts.marketer>
