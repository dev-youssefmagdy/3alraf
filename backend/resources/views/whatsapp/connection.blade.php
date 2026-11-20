<x-layouts.admin :title="__('whatsapp.title')">
    <x-slot:header><x-ui.page-header :title="__('whatsapp.title')" /></x-slot:header>
    @php($can = auth('admin')->user()->can('whatsapp.manage'))
    <div id="wa-panel" class="space-y-6" data-channel="{{ $channel }}" data-status-url="{{ route("admin.whatsapp.status") }}"
        data-ws-key="{{ config('broadcasting.default') === 'reverb' ? config('broadcasting.connections.reverb.key') : '' }}"
        data-ws-host="{{ config('broadcasting.connections.reverb.options.host') }}" data-ws-port="{{ config('broadcasting.connections.reverb.options.port') }}"
        data-ws-scheme="{{ config('broadcasting.connections.reverb.options.scheme') }}" data-auth-url="{{ route("admin.broadcasting.auth") }}"
        data-labels='@json(__('whatsapp.status'))' data-initial-status="{{ $session->status }}">
        <p class="text-sm text-panel-text-subtle">{{ __('whatsapp.subtitle') }}</p>
        <div class="grid gap-6 lg:grid-cols-2">
            <x-ui.card :title="__('whatsapp.title')">
                <dl class="divide-y divide-panel-border text-sm [&>div]:py-2.5">
                    <div class="flex justify-between"><dt class="text-panel-text-subtle">{{ __('admin.status') }}</dt><dd><span id="wa-status" data-status="{{ $session->status }}" class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $session->status === 'connected' ? 'bg-panel-success-bg text-panel-success-fg' : (in_array($session->status, ['error', 'logged_out']) ? 'bg-panel-danger-bg text-panel-danger-fg' : 'bg-panel-warning-bg text-panel-warning-fg') }}"><span class="size-1.5 rounded-full bg-current"></span><span id="wa-status-text">{{ __('whatsapp.status.'.$session->status) }}</span></span></dd></div>
                    <div class="flex justify-between"><dt class="text-panel-text-subtle">{{ __('whatsapp.connected_number') }}</dt><dd id="wa-phone" dir="ltr">{{ $session->phone ? '+'.$session->phone : '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-panel-text-subtle">{{ __('whatsapp.last_connected') }}</dt><dd id="wa-last">{{ $session->last_connected_at?->toDayDateTimeString() ?? __('whatsapp.never') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-panel-text-subtle">{{ __('whatsapp.sent_today') }}</dt><dd>{{ $sentToday }} / {{ $settings['whatsapp.daily_limit'] ?: __('whatsapp.unlimited') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-panel-text-subtle">{{ __('whatsapp.sent_hour') }}</dt><dd>{{ $sentHour }} / {{ $settings['whatsapp.hourly_limit'] ?: __('whatsapp.unlimited') }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-panel-text-subtle">{{ __('whatsapp.last_error') }}</dt><dd id="wa-error" class="text-end text-panel-danger-fg">{{ $session->last_error ?: '—' }}</dd></div>
                </dl>
                @if ($can)
                    <div class="mt-4 flex flex-wrap gap-2 border-t border-panel-border pt-4">
                        <button type="button" id="wa-connect" class="btn btn-primary {{ $session->status === 'connected' ? 'hidden' : '' }}" data-wa-action="{{ route("admin.whatsapp.connect") }}">{{ __('whatsapp.connect') }}</button>
                        <button type="button" class="btn" data-wa-action="{{ route("admin.whatsapp.reconnect") }}">{{ __('whatsapp.reconnect') }}</button>
                        <button type="button" class="btn btn-danger" data-wa-action="{{ route("admin.whatsapp.disconnect") }}" data-confirm="{{ __('whatsapp.disconnect_confirm') }}">{{ __('whatsapp.disconnect') }}</button>
                    </div>
                @endif
            </x-ui.card>
            <x-ui.card :title="__('whatsapp.scan_title')">
                <div class="grid min-h-64 place-items-center">
                    <div id="wa-connected" class="{{ $session->status === 'connected' ? '' : 'hidden' }} text-center">
                        <div class="mx-auto grid size-20 place-items-center rounded-full bg-panel-success-bg text-panel-success-fg">
                            <svg class="size-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                        </div>
                        <h3 class="mt-4 text-lg font-semibold text-panel-text">{{ __('whatsapp.connected_title') }}</h3>
                        <p class="mt-1 text-sm text-panel-text-subtle">{{ __('whatsapp.connected_help') }}</p>
                        <p id="wa-phone-big" dir="ltr" class="mt-3 text-base font-semibold text-panel-success-fg">{{ $session->phone ? '+'.$session->phone : '' }}</p>
                    </div>
                    <div id="wa-scan" class="{{ $session->status === 'connected' ? 'hidden' : '' }} text-center">
                        <img id="wa-qr" alt="QR" class="mx-auto hidden size-64 rounded-lg border border-panel-border p-2" width="256" height="256">
                        <div id="wa-qr-hint" class="mx-auto max-w-xs">
                            <div class="mx-auto mb-3 grid size-16 place-items-center rounded-full bg-panel-surface-sunken text-panel-text-subtle">
                                <svg class="size-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4h6v6H3zM15 4h6v6h-6zM3 14h6v6H3zM15 14h2v2h-2zM19 14h2v2h-2zM15 18h2v2h-2zM19 18h2v2h-2z"/></svg>
                            </div>
                            <p class="text-sm text-panel-text-subtle">{{ __('whatsapp.scan_help') }}</p>
                        </div>
                    </div>
                </div>
            </x-ui.card>
        </div>
        @if ($can)
            <x-ui.card :title="__('whatsapp.settings')">
                <p class="mb-5 rounded-lg bg-panel-surface-sunken p-3 text-sm text-panel-text-muted">{{ __('whatsapp.settings_intro') }}</p>
                <form data-ajax-form data-method="PUT" action="{{ route("admin.whatsapp.settings") }}" method="POST" class="space-y-6" novalidate>
                    @csrf
                    <section>
                        <h3 class="text-sm font-semibold text-panel-text">{{ __('whatsapp.sec_sending') }}</h3>
                        <p class="mb-3 text-xs text-panel-text-subtle">{{ __('whatsapp.sec_sending_help') }}</p>
                        <div class="grid gap-4 md:grid-cols-3">
                            <x-ui.checkbox name="whatsapp_enabled" :label="__('whatsapp.fields.whatsapp_enabled')" :checked="(bool) $settings['whatsapp.enabled']" />
                            <x-ui.input name="daily_limit" type="number" :label="__('whatsapp.fields.daily_limit')" :value="$settings['whatsapp.daily_limit']" />
                            <x-ui.input name="hourly_limit" type="number" :label="__('whatsapp.fields.hourly_limit')" :value="$settings['whatsapp.hourly_limit']" />
                            <x-ui.input name="min_gap" type="number" :label="__('whatsapp.fields.min_gap')" :value="$settings['whatsapp.min_gap']" />
                            <x-ui.input name="max_gap" type="number" :label="__('whatsapp.fields.max_gap')" :value="$settings['whatsapp.max_gap']" />
                        </div>
                    </section>
                    <section class="border-t border-panel-border pt-5">
                        <h3 class="text-sm font-semibold text-panel-text">{{ __('whatsapp.sec_otp') }}</h3>
                        <p class="mb-3 text-xs text-panel-text-subtle">{{ __('whatsapp.sec_otp_help') }}</p>
                        <div class="grid gap-4 md:grid-cols-3">
                            <x-ui.input name="otp_length" type="number" :label="__('whatsapp.fields.otp_length')" :value="$settings['otp.length']" />
                            <x-ui.input name="otp_ttl_seconds" type="number" :label="__('whatsapp.fields.otp_ttl_seconds')" :value="$settings['otp.ttl_seconds']" />
                            <x-ui.input name="otp_resend_cooldown_seconds" type="number" :label="__('whatsapp.fields.otp_resend_cooldown_seconds')" :value="$settings['otp.resend_cooldown_seconds']" />
                            <x-ui.input name="otp_max_attempts" type="number" :label="__('whatsapp.fields.otp_max_attempts')" :value="$settings['otp.max_attempts']" />
                            <x-ui.input name="otp_max_resends" type="number" :label="__('whatsapp.fields.otp_max_resends')" :value="$settings['otp.max_resends']" />
                            <x-ui.input name="otp_message" :label="__('whatsapp.fields.otp_message')" :value="$settings['otp.message']" class="md:col-span-3" />
                        </div>
                    </section>
                    <section class="border-t border-panel-border pt-5">
                        <h3 class="text-sm font-semibold text-panel-text">{{ __('whatsapp.sec_login') }}</h3>
                        <p class="mb-3 text-xs text-panel-text-subtle">{{ __('whatsapp.sec_login_help') }}</p>
                        <div class="grid gap-4 md:grid-cols-3">
                            <x-ui.input name="login_max_failures" type="number" :label="__('whatsapp.fields.login_max_failures')" :value="$settings['login.max_failures']" />
                            <x-ui.input name="login_lockout_seconds" type="number" :label="__('whatsapp.fields.login_lockout_seconds')" :value="$settings['login.lockout_seconds']" />
                        </div>
                    </section>
                    <div class="border-t border-panel-border pt-5"><button type="submit" class="btn btn-primary">{{ __('whatsapp.save') }}</button></div>
                </form>
            </x-ui.card>
            <x-ui.card :title="__('whatsapp.test_title')">
                <form data-ajax-form action="{{ route("admin.whatsapp.test") }}" method="POST" class="flex flex-wrap items-end gap-3" novalidate>
                    @csrf
                    <input type="hidden" name="country" value="EG">
                    <x-ui.input name="phone" type="tel" data-intl-phone="country" :label="__('whatsapp.fields.phone')" />
                    <button type="submit" class="btn btn-primary">{{ __('whatsapp.test_send') }}</button>
                </form>
            </x-ui.card>
        @endif
        <x-ui.card :title="__('whatsapp.troubleshooting')">
            <ul class="list-disc space-y-1 ps-5 text-sm text-panel-text-muted">@foreach (__('whatsapp.tips') as $tip)<li>{{ $tip }}</li>@endforeach</ul>
        </x-ui.card>
    </div>
</x-layouts.admin>
