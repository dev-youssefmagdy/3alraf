<x-layouts.admin :title="__('whatsapp.title')" :guard="$guard">
    @php($can = auth($guard)->user()->can('whatsapp.manage'))
    <div id="wa-panel" class="space-y-6" data-channel="{{ $channel }}" data-status-url="{{ route("$guard.whatsapp.status") }}"
        data-ws-key="{{ config('broadcasting.default') === 'reverb' ? config('broadcasting.connections.reverb.key') : '' }}"
        data-ws-host="{{ config('broadcasting.connections.reverb.options.host') }}" data-ws-port="{{ config('broadcasting.connections.reverb.options.port') }}"
        data-ws-scheme="{{ config('broadcasting.connections.reverb.options.scheme') }}" data-auth-url="{{ route("$guard.broadcasting.auth") }}"
        data-labels='@json(__('whatsapp.status'))' data-initial-status="{{ $session->status }}">
        <div>
            <h1 class="text-xl font-semibold">{{ __('whatsapp.title') }}</h1>
            <p class="text-sm text-slate-500">{{ __('whatsapp.subtitle') }}</p>
        </div>
        <div class="grid gap-6 lg:grid-cols-2">
            <x-card :title="__('whatsapp.title')">
                <dl class="space-y-2 p-5 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('admin.status') }}</dt><dd id="wa-status" class="font-semibold">{{ __('whatsapp.status.'.$session->status) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('whatsapp.connected_number') }}</dt><dd id="wa-phone" dir="ltr">{{ $session->phone ? '+'.$session->phone : '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('whatsapp.last_connected') }}</dt><dd id="wa-last">{{ $session->last_connected_at?->toDayDateTimeString() ?? __('whatsapp.never') }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-500">{{ __('whatsapp.last_error') }}</dt><dd id="wa-error" class="text-end text-red-600">{{ $session->last_error ?: '—' }}</dd></div>
                </dl>
                @if ($can)
                    <div class="flex flex-wrap gap-2 border-t border-slate-100 p-5">
                        <button type="button" class="btn btn-primary" data-wa-action="{{ route("$guard.whatsapp.connect") }}">{{ __('whatsapp.connect') }}</button>
                        <button type="button" class="btn" data-wa-action="{{ route("$guard.whatsapp.reconnect") }}">{{ __('whatsapp.reconnect') }}</button>
                        <button type="button" class="btn btn-danger" data-wa-action="{{ route("$guard.whatsapp.disconnect") }}" data-confirm="{{ __('whatsapp.disconnect_confirm') }}">{{ __('whatsapp.disconnect') }}</button>
                    </div>
                @endif
            </x-card>
            <x-card :title="__('whatsapp.scan_title')">
                <div class="grid place-items-center p-5">
                    <img id="wa-qr" alt="QR" class="hidden size-64 rounded-lg border border-slate-200" width="256" height="256">
                    <p id="wa-qr-hint" class="max-w-xs text-center text-sm text-slate-500">{{ __('whatsapp.scan_help') }}</p>
                </div>
            </x-card>
        </div>
        @if ($can)
            <x-card :title="__('whatsapp.settings')">
                <form data-ajax-form data-method="PUT" action="{{ route("$guard.whatsapp.settings") }}" method="POST" class="grid gap-4 p-5 md:grid-cols-3" novalidate>
                    @csrf
                    <x-checkbox name="whatsapp_enabled" :label="__('whatsapp.fields.whatsapp_enabled')" :bool="(bool) $settings['whatsapp.enabled']" />
                    <x-input name="otp_length" type="number" :label="__('whatsapp.fields.otp_length')" :value="$settings['otp.length']" />
                    <x-input name="otp_ttl_seconds" type="number" :label="__('whatsapp.fields.otp_ttl_seconds')" :value="$settings['otp.ttl_seconds']" />
                    <x-input name="otp_resend_cooldown_seconds" type="number" :label="__('whatsapp.fields.otp_resend_cooldown_seconds')" :value="$settings['otp.resend_cooldown_seconds']" />
                    <x-input name="otp_max_attempts" type="number" :label="__('whatsapp.fields.otp_max_attempts')" :value="$settings['otp.max_attempts']" />
                    <x-input name="otp_max_resends" type="number" :label="__('whatsapp.fields.otp_max_resends')" :value="$settings['otp.max_resends']" />
                    <x-input name="login_max_failures" type="number" :label="__('whatsapp.fields.login_max_failures')" :value="$settings['login.max_failures']" />
                    <x-input name="login_lockout_seconds" type="number" :label="__('whatsapp.fields.login_lockout_seconds')" :value="$settings['login.lockout_seconds']" />
                    <x-input name="otp_message" :label="__('whatsapp.fields.otp_message')" :value="$settings['otp.message']" class="md:col-span-3" />
                    <div class="md:col-span-3"><button type="submit" class="btn btn-primary">{{ __('whatsapp.save') }}</button></div>
                </form>
            </x-card>
        @endif
        <div class="grid gap-6 lg:grid-cols-2">
            <x-card :title="__('whatsapp.logs')">
                <ul class="divide-y divide-slate-100 text-sm">
                    @forelse ($logs as $l)
                        <li class="flex justify-between gap-3 px-5 py-2"><span><b>{{ $l->event }}</b> {{ $l->message }}</span><time class="shrink-0 text-xs text-slate-400">{{ $l->created_at?->diffForHumans() }}</time></li>
                    @empty
                        <li class="px-5 py-3 text-slate-500">{{ __('whatsapp.no_logs') }}</li>
                    @endforelse
                </ul>
            </x-card>
            <x-card :title="__('whatsapp.troubleshooting')">
                <ul class="list-disc space-y-1 p-5 ps-8 text-sm text-slate-600">@foreach (__('whatsapp.tips') as $tip)<li>{{ $tip }}</li>@endforeach</ul>
            </x-card>
        </div>
    </div>
</x-layouts.admin>
