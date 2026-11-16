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
    <x-card class="max-w-2xl">
        <form data-ajax-form @if ($editing) data-method="PUT" @endif data-validate-url="{{ route('marketer.accounts.validate', $editing ? [$m->id] : []) }}"
            action="{{ $editing ? route('marketer.accounts.update', $m->id) : route('marketer.accounts.store') }}" method="POST" class="space-y-4" novalidate>
            @csrf
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input name="name" :label="__('access.fields.name')" :value="$m->name" required />
                <x-input name="email" type="email" :label="__('access.fields.email')" :value="$m->email" />
                <div data-field>
                    <label for="f-phone" class="form-label">{{ __('whatsapp.fields.phone') }}<span class="text-red-500">*</span></label>
                    <div dir="ltr">
                        <input type="hidden" name="country" value="{{ $selIso }}">
                        <input id="f-phone" type="tel" data-intl-phone="country" name="phone" value="{{ $national }}" class="form-control w-full" required>
                    </div>
                </div>
                <x-input name="password" type="password" autocomplete="new-password" :label="__('access.fields.password')" :required="! $editing" :hint="$editing ? __('access.password_hint') : null" />
            </div>
            <div data-field>
                <span class="form-label">{{ __('access.fields.roles') }}</span>
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($roles as $r)
                        <label class="flex cursor-pointer items-start gap-2 rounded-lg border border-slate-200 p-3 text-sm has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                            <input type="radio" name="role" value="{{ $r }}" @checked($role === $r) class="mt-1 size-4 text-brand-600">
                            <span><span class="font-semibold text-slate-800">{{ $a('roles.'.$r) }}</span><span class="block text-xs text-slate-500">{{ $a('role_hints.'.$r) }}</span></span>
                        </label>
                    @endforeach
                </div>
            </div>
            <x-checkbox name="is_active" :label="__('admin.active')" :checked="$editing ? $m->is_active : true" />
            <div><x-checkbox name="otp_exempt" :label="__('access.otp_exempt')" :checked="$editing && $m->otp_exempt" />
                <p class="mt-1 text-xs text-slate-500">{{ __('access.otp_exempt_hint') }}</p></div>
            <x-form-actions :cancel="route('marketer.accounts.index')" />
        </form>
    </x-card>
</x-layouts.marketer>
