@php
    $selects = [
        'mail_mailer' => ['smtp' => 'SMTP', 'log' => __('admin.system_settings.mailer_log')],
        'mail_scheme' => ['' => __('admin.system_settings.scheme_starttls'), 'smtps' => __('admin.system_settings.scheme_ssl')],
    ];
    $label = fn (string $k) => \Illuminate\Support\Facades\Lang::has("admin.system_settings.fields.$k") ? __("admin.system_settings.fields.$k") : \Illuminate\Support\Str::headline($k);
@endphp
<x-layouts.admin :title="__('admin.settings_system')">
    <x-slot:header><x-ui.page-header :title="__('admin.settings_system')" /></x-slot:header>
    <form data-ajax-form data-method="PUT" data-validate-url="{{ route('admin.system-settings.validate') }}"
        action="{{ route('admin.system-settings.update') }}" method="POST" class="mx-auto max-w-5xl space-y-5" novalidate>
        @csrf
        <p class="text-sm text-panel-text-muted">{{ __('admin.system_settings.intro') }}</p>
        @foreach ($schema as $group => $items)
            <x-ui.form-section :title="__('admin.system_settings.groups.'.$group)" :cols="1">
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ($items as $name => [$type])
                        @if ($type === 'bool')
                            <div class="sm:col-span-2"><x-ui.checkbox :name="$name" :label="$label($name)" :checked="$values[$name]" /></div>
                        @elseif ($type === 'secret')
                            <div>
                                <x-ui.input :name="$name" type="password" autocomplete="new-password" :label="$label($name)" value="" :placeholder="$secretSet[$name] ? __('admin.system_settings.secret_keep') : __('admin.system_settings.secret_empty')" />
                            </div>
                        @elseif (isset($selects[$name]))
                            <div><x-ui.select :name="$name" :label="$label($name)" :options="$selects[$name]" :value="$name === 'mail_scheme' && $values[$name] === 'smtp' ? '' : (string) $values[$name]" /></div>
                        @elseif ($type === 'int' || $type === 'float')
                            <div><x-ui.input :name="$name" type="number" min="0" :step="$type === 'float' ? '0.01' : '1'" :label="$label($name)" :value="$values[$name]" required /></div>
                        @else
                            <div><x-ui.input :name="$name" :label="$label($name)" :value="$values[$name]" /></div>
                        @endif
                    @endforeach
                </div>
                @if ($group === 'mail')
                    {{-- test e-mail: uses the SAVED settings; not part of the form submit (no name attribute) --}}
                    <div class="mt-5 border-t border-panel-border pt-4" data-mail-test data-url="{{ route('admin.system-settings.test-mail') }}">
                        <p class="mb-2 text-sm text-panel-text-muted">{{ __('admin.system_settings.mail_test_hint') }}</p>
                        <div class="flex flex-wrap items-end gap-2">
                            <div class="min-w-60 flex-1"><label for="mail-test-to" class="form-label">{{ __('admin.system_settings.mail_test_to') }}</label>
                                <input id="mail-test-to" type="email" class="form-control" dir="ltr" value="{{ auth('admin')->user()->email }}"></div>
                            <button type="button" class="btn btn-secondary" data-mail-test-send>{{ __('admin.system_settings.mail_test_button') }}</button>
                        </div>
                    </div>
                @endif
            </x-ui.form-section>
        @endforeach
        <x-ui.save-bar :cancel="route('admin.dashboard')" />
    </form>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var box = document.querySelector('[data-mail-test]');
            if (!box) return;
            var btn = box.querySelector('[data-mail-test-send]');
            btn.addEventListener('click', function () {
                btn.disabled = true;
                fetch(box.dataset.url, {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json', Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content}, body: JSON.stringify({to: document.getElementById('mail-test-to').value})})
                    .then(function (r) { return r.json().then(function (d) { return [r.ok, d]; }); })
                    .then(function (x) { var d = x[1], msg = d.message || (d.errors && Object.values(d.errors)[0][0]) || ''; x[0] ? window.toastr.success(msg) : window.toastr.error(msg); })
                    .catch(function () { window.toastr.error(window.__t.error); })
                    .finally(function () { btn.disabled = false; });
            });
        });
    </script>
</x-layouts.admin>
