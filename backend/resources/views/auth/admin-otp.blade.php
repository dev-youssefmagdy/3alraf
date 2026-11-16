<x-panel.auth :panel="$guard" :title="__('whatsapp.otp_title')">
            <span class="inline-flex rounded-full bg-panel-soft px-3 py-1 text-xs font-bold text-panel-text">{{ $panelLabel ?? __('admin.admin_panel') }}</span>
            <h1 class="mt-3 text-2xl font-extrabold text-slate-950">{{ __('whatsapp.otp_title') }}</h1>
            <p class="mb-1 mt-1 text-sm text-slate-500">{{ __('admin.step_of', ['step' => 2, 'total' => 2]) }}</p>
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
</x-panel.auth>
