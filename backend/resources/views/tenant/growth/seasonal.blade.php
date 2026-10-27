<x-layouts.admin guard="tenant" :title="__('growth_hub.seasonal.title')">
    @php $s = fn ($k) => __('growth_hub.seasonal.'.$k); $inp = 'block w-full rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-sm'; $csrf = csrf_token(); @endphp
    <div class="space-y-4" id="sc">
        <p class="text-sm text-slate-600">{{ $s('hint') }}</p>
        <div class="flex flex-wrap items-end gap-3">
            <form method="GET" class="flex items-end gap-2"><label class="text-sm text-slate-600">{{ $s('year') }}<input type="number" name="year" value="{{ $year }}" class="{{ $inp }}"></label><x-button type="submit">OK</x-button></form>
            @can('growth.manage')<form data-ajax-form action="{{ route('tenant.growth.seasonal.year') }}" method="POST" novalidate>@csrf<input type="hidden" name="year" value="{{ $year }}"><x-button type="submit">{{ $s('create_year') }}</x-button></form>@endcan
        </div>
        @forelse ($events as $e)
            <x-card :title="$e->name().' ('.$e->starts_on->format('Y-m-d').' - '.$e->ends_on->format('Y-m-d').')'">
                <div class="grid gap-4 lg:grid-cols-2">
                    <div>
                        <b class="text-sm">{{ $s('readiness') }}</b>
                        @foreach ($e->readiness['checks'] as $c)
                            <p class="text-sm"><span class="badge {{ $c['status'] === 'ok' ? 'badge-success' : ($c['status'] === 'warn' ? 'badge-warning' : 'badge-danger') }}">{{ $c['status'] }}</span> {{ $s('checks.'.$c['key']) }}
                                <span class="text-xs text-slate-500">@foreach ($c['evidence'] as $k => $v){{ $v }} {{ __('growth_hub.seasonal.ev.'.$k) }}@unless ($loop->last), @endunless @endforeach</span></p>
                        @endforeach
                        <b class="mt-3 block text-sm">{{ $s('tasks') }}</b>
                        @foreach ($e->tasks as $t)
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" data-task="{{ $t->id }}" @checked($t->done_at) @cannot('growth.manage') disabled @endcannot> {{ $t->title }} <span class="text-xs text-slate-400">{{ $t->due_on?->format('Y-m-d') }}</span></label>
                        @endforeach
                        @if ($e->last)<p class="mt-3 rounded bg-slate-50 p-2 text-xs text-slate-600"><b>{{ $s('last') }}:</b> {{ $e->last->lessons }} {{ $e->last->start_timing }}</p>@endif
                    </div>
                    <div>
                        @can('growth.manage')
                            @if ($e->status !== 'closed')
                                <form data-ajax-form action="{{ route('tenant.growth.seasonal.update', $e->id) }}" method="POST" class="grid gap-2 sm:grid-cols-3" novalidate>
                                    @csrf @method('PUT')
                                    <label class="text-xs text-slate-600">{{ $s('fields.starts_on') }}<input type="date" name="starts_on" value="{{ $e->starts_on->format('Y-m-d') }}" class="{{ $inp }}"></label>
                                    <label class="text-xs text-slate-600">{{ $s('fields.ends_on') }}<input type="date" name="ends_on" value="{{ $e->ends_on->format('Y-m-d') }}" class="{{ $inp }}"></label>
                                    <label class="text-xs text-slate-600">{{ $s('fields.lead_time_days') }}<input type="number" name="lead_time_days" value="{{ $e->lead_time_days }}" class="{{ $inp }}"></label>
                                    <label class="text-xs text-slate-600 sm:col-span-3">{{ $s('categories') }}<select name="category_ids[]" multiple class="{{ $inp }}">@foreach ($categories as $c)<option value="{{ $c->id }}" @selected(in_array($c->id, (array) $e->category_ids))>{{ $c->name }}</option>@endforeach</select></label>
                                    <label class="text-xs text-slate-600 sm:col-span-3">{{ $s('fields.notes') }}<input name="notes" value="{{ $e->notes }}" class="{{ $inp }}"></label>
                                    <div class="flex gap-3 sm:col-span-3"><x-button type="submit">{{ $s('save') }}</x-button>
                                        @if (app(\App\Services\Growth\GrowthFeatures::class)->active('bundle_engine'))<button type="button" data-bundles="{{ $e->id }}" class="text-sm text-brand-700 underline">{{ $s('propose') }}</button>@endif</div>
                                </form>
                                @if ($e->ends_on->isPast())
                                    <form data-ajax-form action="{{ route('tenant.growth.seasonal.close', $e->id) }}" method="POST" class="mt-4 grid gap-2" novalidate>@csrf
                                        @foreach (['content_formats', 'start_timing', 'objections', 'lessons'] as $f)<label class="text-xs text-slate-600">{{ $s('fields.'.$f) }}<input name="{{ $f }}" class="{{ $inp }}"></label>@endforeach
                                        <div><x-button type="submit">{{ $s('close') }}</x-button></div></form>
                                @endif
                            @endif
                        @endcan
                        @if ($e->memory)
                            <div class="mt-3 rounded bg-slate-50 p-2 text-xs text-slate-700"><b>{{ $s('memory') }}</b><p>{{ $e->memory->lessons }}</p><p>{{ $e->memory->start_timing }} {{ $e->memory->content_formats }} {{ $e->memory->objections }}</p>
                                <p>{{ $s('best') }}: @foreach ($e->memory->performance['best_products'] ?? [] as $p)#{{ $p['product_id'] }} ({{ $p['revenue'] }}) @endforeach</p></div>
                        @endif
                    </div>
                </div>
            </x-card>
        @empty<p class="text-sm text-slate-500">{{ $s('none') }}</p>@endforelse
    </div>
    <script>
        const H = {'Accept': 'application/json', 'X-CSRF-TOKEN': @json($csrf)};
        document.querySelectorAll('#sc [data-task]').forEach(c => c.addEventListener('change', () => fetch(@json(url('/admin/growth/seasonal/tasks')) + '/' + c.dataset.task + '/toggle', {method: 'POST', headers: H})));
        document.querySelectorAll('#sc [data-bundles]').forEach(b => b.addEventListener('click', async () => {
            const r = await fetch(@json(url('/admin/growth/seasonal')) + '/' + b.dataset.bundles + '/bundles', {method: 'POST', headers: H});
            const j = await r.json().catch(() => ({})); r.ok ? (location.href = j.redirect) : alert(j.message || 'Error');
        }));
    </script>
</x-layouts.admin>
