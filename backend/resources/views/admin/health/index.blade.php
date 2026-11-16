{{-- System health (Admin → Settings): every check is fetched on its own so a slow service never blocks the page. --}}
<x-layouts.admin :title="__('health.title')">
    <x-slot:header>
        <x-ui.page-header :title="__('health.title')" :subtitle="__('health.hint')">
            <button type="button" class="btn btn-primary" data-health-all>{{ __('health.run_all') }}</button>
        </x-ui.page-header>
    </x-slot:header>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3" data-health>
        @foreach ($checks as $k)
            <div class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-white p-4" data-check="{{ $k }}" data-url="{{ route('admin.health.run', $k) }}">
                <span class="mt-1 size-3 shrink-0 rounded-full bg-slate-300" data-dot></span>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="font-bold text-slate-900">{{ __('health.checks.'.$k) }}</h2>
                        <span class="badge badge-muted" data-badge>…</span>
                    </div>
                    <p class="mt-1 break-words text-sm text-slate-600" data-msg>{{ __('health.checking') }}</p>
                    <p class="mt-1 text-xs text-slate-400" data-ms dir="ltr"></p>
                </div>
                <button type="button" class="btn-sm btn-secondary" data-recheck>{{ __('health.recheck') }}</button>
            </div>
        @endforeach
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var L = {{ \Illuminate\Support\Js::from(collect(['ok', 'warn', 'fail', 'off', 'err'])->mapWithKeys(fn ($k) => [$k => __('health.status.'.$k)])->put('checking', __('health.checking'))) }};
            var C = {ok: ['bg-emerald-500', 'badge-success'], warn: ['bg-amber-500', 'badge-warn'], fail: ['bg-red-500', 'badge-danger'], off: ['bg-slate-300', 'badge-muted']};
            function paint(el, s, msg, ms) {
                var c = C[s] || C.off;
                el.querySelector('[data-dot]').className = 'mt-1 size-3 shrink-0 rounded-full ' + c[0];
                var b = el.querySelector('[data-badge]'); b.className = 'badge ' + c[1]; b.textContent = L[s] || s;
                el.querySelector('[data-msg]').textContent = msg; el.querySelector('[data-ms]').textContent = ms == null ? '' : ms + ' ms';
            }
            function run(el) {
                paint(el, 'off', L.checking, null);
                return fetch(el.dataset.url, {headers: {Accept: 'application/json'}, credentials: 'same-origin'})
                    .then(function (r) { return r.json(); }).then(function (d) { paint(el, d.status, d.message, d.ms); })
                    .catch(function () { paint(el, 'fail', L.err, null); });
            }
            var all = [].slice.call(document.querySelectorAll('[data-check]'));
            function runAll() { return Promise.all(all.map(run)); }
            document.querySelector('[data-health-all]').addEventListener('click', runAll);
            all.forEach(function (el) { el.querySelector('[data-recheck]').addEventListener('click', function () { run(el); }); });
            runAll();
        });
    </script>
</x-layouts.admin>
