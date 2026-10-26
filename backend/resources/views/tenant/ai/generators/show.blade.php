<x-layouts.admin guard="tenant" :title="__('ai.gen.title')">
    @php
        $s = fn ($k, $a = []) => __('ai.gen.'.$k, $a);
        $inp = 'block w-full rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-sm disabled:bg-slate-50';
        $lbl = 'block text-sm text-slate-600';
        $d = $g['draft'] ?? [];
        $kind = $g['kind'];
        $open = $g['open'];
        $dis = $open ? '' : 'disabled';
        $img = collect($g['media'])->firstWhere('state', 'ready');
        $target = $g['target_type'] && $g['target_id'] ? match ($kind) {
            'banner' => route('tenant.banners.edit', $g['target_id']), 'flash_sale' => route('tenant.flash-sales.edit', $g['target_id']),
            'discount_code' => route('tenant.discount-codes.edit', $g['target_id']), 'bundle' => route('tenant.bundles.edit', $g['target_id']), default => null } : null;
    @endphp
    <div id="gv" class="space-y-4" data-id="{{ $g['id'] }}" data-base="{{ route('tenant.ai.generators.show', $g['id']) }}" data-pending="{{ $g['pending'] ? 1 : 0 }}" data-open="{{ $open ? 1 : 0 }}">
        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('tenant.ai.generators.index') }}" class="text-sm text-brand-700">&larr; {{ $s('back') }}</a>
            <h2 class="text-lg font-semibold text-slate-800">#{{ $g['id'] }} {{ $kind ? $s('kinds.'.$kind) : '' }}</h2>
            <span class="badge {{ $g['status'] === 'approved' ? 'badge-success' : ($g['status'] === 'draft' && ! $g['expired'] ? 'badge-warning' : 'badge-muted') }}">{{ $g['expired'] ? $s('expired_badge') : $g['status_label'] }}</span>
            @if ($target)<a href="{{ $target }}" class="ms-auto text-sm text-brand-700 underline">{{ $s('open_target') }}</a>@endif
        </div>

        @if ($g['pending'])<p class="rounded-lg bg-slate-50 p-3 text-sm text-slate-600">{{ $s('pending') }}</p>@endif
        @if ($g['error'])<p class="rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ $g['error'] }}</p>@endif
        @if ($g['status'] === 'rejected' && $g['reject_reason'])<p class="rounded-lg bg-slate-50 p-3 text-sm text-slate-600">{{ $s('rejected_reason', ['r' => $g['reject_reason'] === 'expired' ? $s('expired_badge') : $g['reject_reason']]) }}</p>@endif
        @if ($g['expired'])<p class="rounded-lg bg-amber-50 p-3 text-sm text-amber-800">{{ $s('expired') }}</p>@endif
        <div id="gv-problems">
            @if ($g['errors'])<div class="rounded-lg bg-red-50 p-3 text-sm text-red-700"><b>{{ $s('problems') }}</b><ul class="list-disc ps-5">@foreach ($g['errors'] as $m)<li>{{ $m }}</li>@endforeach</ul></div>@endif
            @if ($g['warnings'])<div class="mt-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-800"><b>{{ $s('warnings') }}</b><ul class="list-disc ps-5">@foreach ($g['warnings'] as $m)<li>{{ $m }}</li>@endforeach</ul></div>@endif
        </div>

        @if ($d)
        <form id="gv-form" class="space-y-4 rounded-xl border border-slate-200 bg-white p-4" autocomplete="off">
            @if ($kind === 'banner')
                <div class="grid gap-4 lg:grid-cols-2">
                    <div>
                        <p class="{{ $lbl }}">{{ $s('image') }}</p>
                        @if ($img)<img src="{{ $img['url'] }}" alt="" class="mt-1 max-h-64 rounded-lg border border-slate-200">@else<p class="mt-1 text-sm text-slate-500">{{ $s('no_image') }}</p>@endif
                    </div>
                    <div class="space-y-3">
                        <label class="{{ $lbl }}">{{ __('ai.gen.fields.placement') }}<select data-k="placement" {{ $dis }} class="{{ $inp }}">@foreach ($placements as $k => $n)<option value="{{ $k }}" @selected(($d['placement'] ?? '') === $k)>{{ $n }}</option>@endforeach</select></label>
                        @foreach ($locales as $l)<label class="{{ $lbl }}">{{ $s('title_field') }} ({{ strtoupper($l) }})<input data-k="title.{{ $l }}" value="{{ $d['title'][$l] ?? '' }}" maxlength="120" {{ $dis }} class="{{ $inp }}"></label>@endforeach
                        <label class="{{ $lbl }}">{{ $s('link') }}<input data-k="link_url" value="{{ $d['link_url'] ?? '' }}" maxlength="500" {{ $dis }} class="{{ $inp }}"></label>
                    </div>
                </div>
            @endif

            @if (in_array($kind, ['flash_sale', 'bundle']))
                <div class="grid gap-3 sm:grid-cols-2">@foreach ($locales as $l)<label class="{{ $lbl }}">{{ $s('name') }} ({{ strtoupper($l) }})<input data-k="name.{{ $l }}" value="{{ $d['name'][$l] ?? '' }}" maxlength="120" {{ $dis }} class="{{ $inp }}"></label>@endforeach</div>
            @endif
            @if ($kind === 'bundle')
                <div class="grid gap-3 sm:grid-cols-2">@foreach ($locales as $l)<label class="{{ $lbl }}">{{ $s('description') }} ({{ strtoupper($l) }})<textarea data-k="description.{{ $l }}" rows="2" maxlength="1000" {{ $dis }} class="{{ $inp }}">{{ $d['description'][$l] ?? '' }}</textarea></label>@endforeach</div>
                <label class="{{ $lbl }} max-w-xs">{{ $s('price') }} ({{ current_tenant()->currency_code }})<input type="number" step="0.01" data-k="price" value="{{ $d['price'] ?? '' }}" {{ $dis }} class="{{ $inp }}"></label>
            @endif

            @if ($kind === 'discount_code')
                <div class="grid gap-3 sm:grid-cols-3">
                    <label class="{{ $lbl }}">{{ $s('code') }}<input data-k="code" value="{{ $d['code'] ?? '' }}" maxlength="20" {{ $dis }} class="{{ $inp }} uppercase"></label>
                    <label class="{{ $lbl }}">{{ __('admin.fields.type') }}<select data-k="type" {{ $dis }} class="{{ $inp }}"><option value="percent" @selected(($d['type'] ?? '') === 'percent')>{{ $s('type_percent') }}</option><option value="fixed" @selected(($d['type'] ?? '') === 'fixed')>{{ __('ai.gen.type_price') }}</option></select></label>
                    <label class="{{ $lbl }}">{{ $s('value') }}<input type="number" step="0.01" data-k="value" value="{{ $d['value'] ?? '' }}" {{ $dis }} class="{{ $inp }}"></label>
                    <label class="{{ $lbl }}">{{ $s('min_order') }}<input type="number" step="0.01" data-k="min_order" value="{{ $d['min_order'] ?? '' }}" {{ $dis }} class="{{ $inp }}"></label>
                    <label class="{{ $lbl }}">{{ $s('max_discount') }}<input type="number" step="0.01" data-k="max_discount" value="{{ $d['max_discount'] ?? '' }}" {{ $dis }} class="{{ $inp }}"></label>
                    <label class="{{ $lbl }}">{{ $s('usage_limit') }}<input type="number" data-k="usage_limit" value="{{ $d['usage_limit'] ?? '' }}" {{ $dis }} class="{{ $inp }}"></label>
                    <label class="{{ $lbl }}">{{ $s('per_customer') }}<input type="number" data-k="per_customer_limit" value="{{ $d['per_customer_limit'] ?? '' }}" {{ $dis }} class="{{ $inp }}"></label>
                    <label class="{{ $lbl }}">{{ $s('scope') }}<select data-k="scope" {{ $dis }} class="{{ $inp }}"><option value="all" @selected(($d['scope'] ?? '') === 'all')>{{ $s('scope_all') }}</option><option value="products" @selected(($d['scope'] ?? '') === 'products')>{{ $s('scope_products') }}</option></select></label>
                    <label class="flex items-center gap-2 pt-6 text-sm text-slate-600"><input type="checkbox" data-k="free_shipping" data-bool @checked(! empty($d['free_shipping'])) {{ $dis }}>{{ $s('free_shipping') }}</label>
                </div>
                <input type="hidden" data-k="product_ids" data-json value='@json($d['product_ids'] ?? [])'>
                @if (($g['preview'][0]['headroom_pct'] ?? null) !== null)<p class="text-sm text-slate-600">{{ $s('headroom') }}: <b>{{ $g['preview'][0]['headroom_pct'] }}%</b></p>@endif
            @endif

            @if ($kind !== 'discount_code' || true)
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="{{ $lbl }}">{{ __('ai.gen.fields.starts_at') }}<input type="datetime-local" data-k="starts_at" value="{{ ! empty($d['starts_at']) ? \Illuminate\Support\Carbon::parse($d['starts_at'])->format('Y-m-d\TH:i') : '' }}" {{ $dis }} class="{{ $inp }}"></label>
                    <label class="{{ $lbl }}">{{ __('ai.gen.fields.ends_at') }}<input type="datetime-local" data-k="ends_at" value="{{ ! empty($d['ends_at']) ? \Illuminate\Support\Carbon::parse($d['ends_at'])->format('Y-m-d\TH:i') : '' }}" {{ $dis }} class="{{ $inp }}"></label>
                </div>
            @endif

            @if ($kind === 'flash_sale')
                <div class="overflow-x-auto"><table class="w-full text-sm" id="gv-items">
                    <thead><tr class="text-start text-slate-500"><th class="p-1">{{ $s('item') }}</th><th class="p-1">{{ $s('price') }}</th><th class="p-1">{{ __('admin.fields.type') }}</th><th class="p-1">{{ $s('value') }}</th><th class="p-1">{{ $s('new_price') }}</th><th class="p-1">{{ $s('margin') }}</th><th class="p-1">{{ $s('stock') }}</th><th class="p-1">{{ $s('limit') }}</th><th></th></tr></thead>
                    <tbody>@foreach (($d['items'] ?? []) as $i => $row) @php $p = $g['preview'][$i] ?? []; @endphp
                        <tr data-row data-item="{{ $row['item'] }}" class="border-t border-slate-100">
                            <td class="p-1">{{ $items[$row['item']] ?? $row['item'] }}</td><td class="p-1">{{ $p['price'] ?? '' }}</td>
                            <td class="p-1"><select data-f="discount_type" {{ $dis }} class="{{ $inp }}"><option value="percent" @selected($row['discount_type'] === 'percent')>{{ $s('type_percent') }}</option><option value="price" @selected($row['discount_type'] === 'price')>{{ $s('type_price') }}</option></select></td>
                            <td class="p-1"><input type="number" step="0.01" data-f="value" value="{{ $row['value'] }}" {{ $dis }} class="{{ $inp }}"></td>
                            <td class="p-1">{{ $p['sell'] ?? '' }}</td><td class="p-1 {{ isset($p['margin']) && $p['margin'] < ($g['inputs']['margin_floor'] ?? 0) ? 'text-red-600' : '' }}">{{ $p['margin'] ?? '-' }}%</td><td class="p-1">{{ $p['available'] ?? '∞' }}</td>
                            <td class="p-1"><input type="number" data-f="qty_limit" value="{{ $row['qty_limit'] }}" {{ $dis }} class="{{ $inp }}"></td>
                            <td class="p-1">@if ($open)<button type="button" data-remove class="btn-sm btn-danger">{{ $s('remove') }}</button>@endif</td></tr>
                    @endforeach</tbody></table></div>
            @endif

            @if ($kind === 'bundle')
                <div class="overflow-x-auto"><table class="w-full text-sm" id="gv-items">
                    <thead><tr class="text-start text-slate-500"><th class="p-1">{{ $s('item') }}</th><th class="p-1">{{ $s('qty') }}</th><th class="p-1">{{ $s('price') }}</th><th class="p-1">{{ $s('cost') }}</th><th></th></tr></thead>
                    <tbody>@foreach (($d['items'] ?? []) as $i => $row) @php $p = collect($g['preview'])->firstWhere('name', $items[$row['item']] ?? null) ?? ($g['preview'][$i] ?? []); @endphp
                        <tr data-row data-item="{{ $row['item'] }}" class="border-t border-slate-100"><td class="p-1">{{ $items[$row['item']] ?? $row['item'] }}</td>
                            <td class="p-1"><input type="number" min="1" data-f="quantity" value="{{ $row['quantity'] }}" {{ $dis }} class="{{ $inp }}"></td><td class="p-1">{{ $p['price'] ?? '' }}</td><td class="p-1">{{ $p['cost'] ?? '' }}</td>
                            <td class="p-1">@if ($open)<button type="button" data-remove class="btn-sm btn-danger">{{ $s('remove') }}</button>@endif</td></tr>
                    @endforeach</tbody></table></div>
                @if ($g['summary'])<p class="text-sm text-slate-600">{{ $s('sum_price') }}: <b>{{ $g['summary']['sum_price'] }}</b> | {{ $s('cost') }}: <b>{{ $g['summary']['sum_cost'] }}</b> | {{ $s('margin') }}: <b>{{ $g['summary']['margin'] }}%</b> | {{ $s('saving') }}: <b>{{ $g['summary']['saving'] }}</b></p>@endif
            @endif
        </form>
        @endif

        @if ($open)
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" id="gv-save" class="btn btn-secondary">{{ $s('save') }}</button>
                @if ($canApprove)
                    <button type="button" id="gv-approve" class="btn btn-primary" @disabled($g['errors'])>{{ $s('approve') }}</button>
                    <button type="button" id="gv-reject" class="btn btn-danger">{{ $s('reject') }}</button>
                @else<span class="text-xs text-slate-500">{{ $s('no_approve') }}</span>@endif
                <button type="button" id="gv-regen" class="btn btn-secondary ms-auto">{{ $s('regenerate') }}</button>
            </div>
        @endif
    </div>
    <script>
        (() => {
            const root = document.getElementById('gv'), base = root.dataset.base, hdr = {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content};
            const call = async (url, method, body) => { const r = await fetch(url, {method, headers: hdr, body: body ? JSON.stringify(body) : undefined}); return [r, await r.json().catch(() => ({}))]; };
            const say = (j, ok) => { const m = j.message || Object.values(j.errors || {}).flat().join(' '); if (window.toastr) (ok ? toastr.success : toastr.error)(m || ''); else alert(m || ''); };
            if (root.dataset.pending === '1') setTimeout(() => location.reload(), 3000);
            const form = document.getElementById('gv-form');
            const collect = () => {
                const d = {};
                form.querySelectorAll('[data-k]').forEach(e => {
                    const path = e.dataset.k.split('.'); let o = d;
                    path.slice(0, -1).forEach(p => o = (o[p] ??= {}));
                    o[path.at(-1)] = e.dataset.bool !== undefined ? e.checked : (e.dataset.json !== undefined ? JSON.parse(e.value || '[]') : e.value);
                });
                const rows = [...form.querySelectorAll('[data-row]')];
                if (rows.length || document.getElementById('gv-items')) d.items = rows.map(tr => { const r = {item: tr.dataset.item}; tr.querySelectorAll('[data-f]').forEach(i => r[i.dataset.f] = i.value); return r; });
                return d;
            };
            const save = async () => { const [r, j] = await call(base, 'PUT', {draft: collect()}); if (!r.ok) { say(j, false); return false; } location.reload(); return true; };
            document.querySelectorAll('[data-remove]').forEach(b => b.addEventListener('click', () => b.closest('tr').remove()));
            document.getElementById('gv-save')?.addEventListener('click', save);
            document.getElementById('gv-approve')?.addEventListener('click', async () => {
                if (!confirm(@json($s('confirm_approve')))) return;
                const [r, j] = await call(base + '/approve', 'POST'); if (!r.ok) { say(j, false); return; } say(j, true); setTimeout(() => location.reload(), 600);
            });
            document.getElementById('gv-reject')?.addEventListener('click', async () => {
                const reason = prompt(@json($s('reject_prompt'))); if (!reason) return;
                const [r, j] = await call(base + '/reject', 'POST', {reason}); if (!r.ok) { say(j, false); return; } location.reload();
            });
            document.getElementById('gv-regen')?.addEventListener('click', async () => { const [r, j] = await call(base + '/regenerate', 'POST'); if (r.ok && j.url) location.href = j.url; else say(j, false); });
        })();
    </script>
</x-layouts.admin>
