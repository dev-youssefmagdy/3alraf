<x-layouts.storefront :title="__('growth_hub.gift.title')">
    @php $g = fn ($k) => __('growth_hub.gift.'.$k); $inp = 'block w-full rounded-lg border border-slate-300 bg-white px-3 py-2'; @endphp
    <div id="gf" class="mx-auto max-w-3xl space-y-6">
        <h1 class="text-2xl font-extrabold">{{ $g('title') }}</h1>
        <form id="gf-form" class="grid gap-3 sm:grid-cols-2" novalidate>
            @csrf
            <label class="text-sm">{{ $g('fields.budget') }} ({{ current_tenant()->currency_symbol }})<input type="number" name="budget" min="1" step="any" required class="{{ $inp }}"></label>
            <label class="text-sm">{{ $g('fields.occasion') }}<select name="occasion" class="{{ $inp }}"><option value="">{{ $g('any') }}</option>@foreach ($occasions as $o)<option value="{{ $o }}">{{ $g('occasions.'.$o) }}</option>@endforeach</select></label>
            <label class="text-sm">{{ $g('fields.recipient') }}<select name="recipient" class="{{ $inp }}"><option value="">{{ $g('any') }}</option>@foreach ($recipients as $o)<option value="{{ $o }}">{{ $g('recipients.'.$o) }}</option>@endforeach</select></label>
            <label class="text-sm">{{ $g('fields.category_id') }}<select name="category_id" class="{{ $inp }}"><option value="">{{ $g('any') }}</option>@foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></label>
            <label class="text-sm sm:col-span-2">{{ $g('fields.restrictions') }}<input name="restrictions" maxlength="200" class="{{ $inp }}"></label>
            <div class="sm:col-span-2"><button type="submit" class="btn btn-primary !px-8">{{ $g('find') }}</button></div>
        </form>
        <div id="gf-out" class="space-y-4"></div>
    </div>
    <script>
        (() => {
            const f = document.getElementById('gf-form'), out = document.getElementById('gf-out'), cur = @json(current_tenant()->currency_symbol), tok = @json(csrf_token());
            const T = {results: @json($g('results')), none: @json($g('none')), near: @json($g('near')), bundles: @json($g('bundles')), add: @json($g('add_all'))};
            const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
            const card = r => `<div class="flex gap-3 rounded-xl border border-slate-100 p-3">${r.image ? `<img src="${esc(r.image)}" class="size-16 rounded object-cover" alt="">` : ''}<div class="flex-1"><a class="font-semibold hover:text-brand-600" href="${@json(url(app()->getLocale().'/product'))}/${encodeURIComponent(r.slug)}">${esc(r.name)}</a><p class="text-sm"><b>${esc(r.price.toFixed(2))}</b> ${esc(cur)}</p>${r.explanation ? `<p class="text-sm text-slate-600">${esc(r.explanation)}</p>` : ''}</div></div>`;
            f.addEventListener('submit', async e => {
                e.preventDefault();
                const r = await fetch(location.pathname, {method: 'POST', body: new FormData(f), headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': tok}});
                const j = await r.json().catch(() => ({}));
                if (!r.ok) { out.innerHTML = `<p class="text-red-600">${esc(Object.values(j.errors || {}).flat()[0] || j.message || 'Error')}</p>`; return; }
                const d = j.data; let h = `<h2 class="font-bold">${T.results}</h2>`;
                h += d.results.length ? d.results.map(card).join('') : `<p class="text-slate-500">${T.none}</p>`;
                if (d.results.some(x => x.simple)) h += `<button id="gf-cart" class="btn btn-primary">${T.add}</button>`;
                if (d.bundles.length) h += `<h2 class="font-bold">${T.bundles}</h2>` + d.bundles.map(b => `<a class="block rounded-xl border border-slate-100 p-3" href="${@json(url(app()->getLocale().'/bundle'))}/${encodeURIComponent(b.slug)}">${esc(b.name)} - ${esc(b.price)} ${esc(cur)}</a>`).join('');
                if (d.near_budget.length) h += `<h2 class="font-bold text-amber-700">${T.near}</h2>` + d.near_budget.map(card).join('');
                out.innerHTML = h;
                document.getElementById('gf-cart')?.addEventListener('click', async () => {
                    const c = await fetch(location.pathname + '/' + d.token + '/cart', {method: 'POST', headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': tok}});
                    const cj = await c.json().catch(() => ({})); if (c.ok && cj.redirect) location.href = cj.redirect;
                });
            });
        })();
    </script>
</x-layouts.storefront>
