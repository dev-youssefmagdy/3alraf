<x-layouts.admin guard="tenant" :title="__('growth_hub.bundles.title')">
    @php $b = fn ($k, $r = []) => __('growth_hub.bundles.'.$k, $r); $inp = 'block w-full rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-sm'; @endphp
    <div class="space-y-4" id="be">
        <p class="text-sm text-slate-600">{{ $b('hint') }}</p>
        @can('growth.manage')
            <x-card :title="$b('propose')">
                <form data-ajax-form action="{{ route('tenant.growth.bundles.propose') }}" method="POST" class="grid gap-3 sm:grid-cols-4" novalidate>
                    @csrf
                    <label class="text-sm text-slate-600">{{ $b('fields.mode') }}<select name="mode" class="{{ $inp }}">@foreach ($modes as $m)<option value="{{ $m }}">{{ $b('modes.'.$m) }}</option>@endforeach</select></label>
                    <label class="text-sm text-slate-600">{{ $b('budgets_hint') }}<input name="budgets" class="{{ $inp }}" placeholder="250, 500, 1000"></label>
                    <label class="text-sm text-slate-600">{{ $b('fields.event_id') }}<select name="event_id" class="{{ $inp }}"><option value="">-</option>@foreach ($events as $e)<option value="{{ $e->id }}">{{ $e->name() }} {{ $e->year }}</option>@endforeach</select></label>
                    <div class="self-end"><x-button type="submit">{{ $b('propose') }}</x-button></div>
                </form>
            </x-card>
        @endcan
        <x-card :title="$b('pending')">
            @forelse ($pending as $a)
                <div class="flex flex-wrap items-center gap-2 border-t border-slate-100 py-2 text-sm"><b>{{ $a->title }}</b><span class="text-slate-500">{{ $a->payload['price'] ?? '' }} / {{ __('growth_hub.bundles.cols.margin') }} {{ $a->payload['pricing']['margin_percent'] ?? '?' }}%</span>
                    <a class="ms-auto text-xs text-brand-700 underline" href="{{ route('tenant.growth.approvals.index') }}">{{ __('growth_hub.menu.approvals') }}</a></div>
            @empty<p class="text-sm text-slate-500">{{ $b('none_pending') }}</p>@endforelse
        </x-card>
        <x-card :title="$b('performance')">
            <div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr class="text-start text-slate-500">@foreach (['bundle', 'orders', 'units', 'revenue', 'margin', 'uplift', 'sell', 'indiv'] as $c)<th class="p-1 text-start">{{ $b('cols.'.$c) }}</th>@endforeach</tr></thead><tbody>
                @foreach ($report as $r)
                    <tr class="border-t border-slate-100"><td class="p-1">{{ $r['name'] }} @unless ($r['is_active'])<span class="badge badge-muted">draft</span>@endunless</td><td class="p-1">{{ $r['orders'] }}</td><td class="p-1">{{ $r['units'] }}</td><td class="p-1">{{ number_format($r['revenue'], 2) }}</td>
                        <td class="p-1">{{ $r['line_margin']['value'] === null ? $b('insufficient') : number_format($r['line_margin']['value'], 2) }}</td><td class="p-1">{{ $r['aov_uplift_percent'] === null ? '-' : $r['aov_uplift_percent'].'%' }}</td>
                        <td class="p-1">{{ $r['sell_through'] === null ? '-' : $r['sell_through'].'%' }}</td><td class="p-1">{{ $r['individual_component_units'] }}</td></tr>
                @endforeach
            </tbody></table></div>
        </x-card>
        @if ($abOn)
            <x-card :title="$b('ab_title')">
                <p class="mb-3 text-sm text-slate-600">{{ $b('ab_hint') }}</p>
                @can('growth.manage')
                    <form data-ajax-form action="{{ route('tenant.growth.bundles.ab.start') }}" method="POST" enctype="multipart/form-data" class="mb-4 grid gap-3 sm:grid-cols-3" novalidate>
                        @csrf
                        <label class="text-sm text-slate-600">{{ $b('fields.bundle_id') }}<select name="bundle_id" class="{{ $inp }}">@foreach ($bundles as $bd)<option value="{{ $bd->id }}">{{ $bd->name }}</option>@endforeach</select></label>
                        @foreach ((array) current_tenant()->locales as $l)
                            <label class="text-sm text-slate-600">{{ $b('fields.name') }} ({{ $l }})<input name="name[{{ $l }}]" class="{{ $inp }}"></label>
                            <label class="text-sm text-slate-600 sm:col-span-2">{{ $b('fields.description') }} ({{ $l }})<input name="description[{{ $l }}]" class="{{ $inp }}"></label>
                        @endforeach
                        <label class="text-sm text-slate-600">{{ $b('fields.image') }}<input type="file" name="image" accept="image/*" class="{{ $inp }}"></label>
                        <label class="text-sm text-slate-600">{{ $b('fields.split_b') }}<input type="number" name="split_b" value="50" class="{{ $inp }}"></label>
                        <label class="text-sm text-slate-600">{{ $b('fields.min_sample') }}<input type="number" name="min_sample" value="100" class="{{ $inp }}"></label>
                        <div class="self-end"><x-button type="submit">{{ $b('ab_start') }}</x-button></div>
                    </form>
                @endcan
                @foreach ($abTests as $t)
                    <div class="border-t border-slate-100 py-2 text-sm" data-test="{{ $t->id }}">
                        <div class="flex flex-wrap items-center gap-2"><b>{{ $t->bundle?->name }}</b><span class="badge {{ $t->status === 'running' ? 'badge-warning' : 'badge-muted' }}">{{ $b($t->status) }}</span>
                            <span class="text-slate-500">{{ $b('recs.'.$t->results['recommendation']) }}</span>@if ($t->winner)<span class="badge badge-success">{{ $t->winner }}</span>@endif</div>
                        @foreach ($t->results['variants'] as $v)<p class="text-slate-600">{{ $v['label'] }}: {{ $b('exposures') }} {{ $v['exposures'] }}, {{ $b('carts') }} {{ $v['carts'] }}, {{ $b('rate') }} {{ $v['cart_rate'] ?? '-' }}%, {{ __('growth_gaps.ab_orders') }} {{ $v['orders'] }}, {{ __('growth_gaps.ab_order_rate') }} {{ $v['order_rate'] ?? '-' }}%</p>@endforeach
                        @can('growth.manage')@if ($t->status === 'running')<button type="button" data-win="A" class="text-xs text-brand-700 underline">{{ $b('ab_keep_a') }}</button> <button type="button" data-win="B" class="text-xs text-brand-700 underline">{{ $b('ab_use_b') }}</button>@endif @endcan
                    </div>
                @endforeach
            </x-card>
        @endif
    </div>
    <script>
        document.querySelectorAll('#be [data-win]').forEach(btn => btn.addEventListener('click', async () => {
            const fd = new FormData(); fd.append('winner', btn.dataset.win);
            const r = await fetch(@json(url('/admin/growth/bundles/ab')) + '/' + btn.closest('[data-test]').dataset.test + '/conclude', {method: 'POST', body: fd, headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content}});
            r.ok ? location.reload() : alert((await r.json().catch(() => ({}))).message || 'Error');
        }));
    </script>
</x-layouts.admin>
