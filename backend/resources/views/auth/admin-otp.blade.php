<x-layouts.base :title="__('whatsapp.otp_title')">
    <div class="grid min-h-screen place-items-center px-4">
        <div class="w-full max-w-sm">
            <x-card :title="__('whatsapp.otp_title')">
                <p class="mb-4 text-sm text-slate-600">{{ __('whatsapp.otp_help', ['phone' => '']) }}<bdi dir="ltr" class="font-semibold">{{ $masked }}</bdi></p>
                <form data-ajax-form action="{{ $action }}" method="POST" class="space-y-4" novalidate>
                    @csrf
                    <x-input name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="{{ $length }}" :label="__('whatsapp.fields.code')" required autofocus dir="ltr" />
                    <button type="submit" class="btn btn-primary w-full">{{ __('whatsapp.verify') }}</button>
                </form>
                <div class="mt-4 flex items-center justify-between text-sm">
                    <button type="button" id="otp-resend" class="font-semibold text-brand-600 disabled:text-slate-400" data-url="{{ $resend }}" data-left="{{ $cooldown }}"
                        data-label="{{ __('whatsapp.resend') }}" data-wait="{{ __('whatsapp.resend_in') }}" disabled>{{ __('whatsapp.resend') }}</button>
                    <a href="{{ $back }}" class="text-slate-500">{{ __('whatsapp.back') }}</a>
                </div>
            </x-card>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var b = document.getElementById('otp-resend'), left = parseInt(b.dataset.left, 10) || 0, t;
            function tick() {
                if (left > 0) { b.disabled = true; b.textContent = b.dataset.wait.replace(':seconds', left--); t = setTimeout(tick, 1000); }
                else { b.disabled = false; b.textContent = b.dataset.label; }
            }
            tick();
            b.addEventListener('click', function () {
                b.disabled = true;
                window.$.post(b.dataset.url).done(function (r) { window.toastr.success(r.message); left = (r.data && r.data.cooldown) || 60; tick(); })
                    .fail(function (x) { var e = x.responseJSON && x.responseJSON.errors; window.toastr.error(e ? Object.values(e)[0][0] : 'Error'); left = 5; tick(); });
            });
        });
    </script>
</x-layouts.base>
