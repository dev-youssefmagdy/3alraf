@php($intro = __('whatsapp.otp_help', ['phone' => "\u{2066}".$masked."\u{2069}"]))
<x-panel.auth :panel="$guard" :title="__('whatsapp.otp_title')" :heading="__('whatsapp.otp_title')" :step="__('admin.step_of', ['step' => 2, 'total' => 2])" :panelLabel="$panelLabel ?? null"
    :intro="$intro">
    <form data-ajax-form action="{{ $action }}" method="POST" class="space-y-5" novalidate>
        @csrf
        <x-ui.otp-input name="code" :length="$length" :label="__('whatsapp.fields.code')" />
        <x-ui.button type="submit" size="lg" class="w-full">{{ __('whatsapp.verify') }}</x-ui.button>
    </form>
    <div class="mt-5 flex items-center justify-between gap-3 text-sm">
        <button type="button" id="otp-resend" class="inline-flex min-h-11 items-center gap-1.5 font-semibold text-panel-accent-text disabled:text-panel-text-subtle" data-url="{{ $resend }}" data-left="{{ $cooldown }}"
            data-label="{{ __('whatsapp.resend') }}" data-wait="{{ __('whatsapp.resend_in') }}" disabled><x-ui.icon name="refresh" class="size-4" /><span data-otp-resend-label>{{ __('whatsapp.resend') }}</span></button>
        <a href="{{ $back }}" class="inline-flex min-h-11 items-center gap-1.5 text-panel-text-muted hover:text-panel-text"><x-ui.icon name="back" class="size-4" />{{ __('whatsapp.back') }}</a>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var b = document.getElementById('otp-resend'), l = b.querySelector('[data-otp-resend-label]'), left = parseInt(b.dataset.left, 10) || 0, t;
            function tick() {
                if (left > 0) { b.disabled = true; l.textContent = b.dataset.wait.replace(':seconds', left--); t = setTimeout(tick, 1000); }
                else { b.disabled = false; l.textContent = b.dataset.label; }
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
