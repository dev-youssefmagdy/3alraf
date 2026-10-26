<x-layouts.admin guard="tenant" :title="__('ai.market.title')">
    @php
        $m = fn ($k, $a = []) => __('ai.market.'.$k, $a);
        $inp = 'mt-1 block rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-sm';
    @endphp
    <div id="mk" class="space-y-4" data-data="{{ route('tenant.ai.market-trends.data') }}" data-refresh="{{ route('tenant.ai.market-trends.refresh') }}" data-dismiss="{{ route('tenant.ai.market-trends.dismiss') }}"
         data-export="{{ route('tenant.ai.market-trends.export') }}" data-draft="{{ route('tenant.ai.trends.draft') }}" data-edit="{{ url('admin/catalog') }}" data-studio="{{ route('tenant.ai.studio.index') }}">
        <p class="text-sm text-slate-600">{{ $m('subtitle') }} <span class="text-xs text-slate-500">{{ $m('estimate') }}</span></p>

        <form id="mk-form" class="space-y-3 rounded-xl border border-slate-200 bg-white p-4 text-sm" autocomplete="off">
            <fieldset>
                <legend class="text-slate-600">{{ $m('countries') }}</legend>
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach ($countries as $code => $name)
                        <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-full border border-slate-300 px-2.5 py-1 text-xs has-[:checked]:border-brand-600 has-[:checked]:bg-brand-50 has-[:checked]:text-brand-700">
                            <input type="checkbox" name="countries[]" value="{{ $code }}" class="sr-only" @checked($code === $defaultCountry)>{{ country_flag($code) }} {{ $name }}</label>
                    @endforeach
                </div>
            </fieldset>
            <div class="flex flex-wrap items-end gap-3">
                <label class="text-slate-600">{{ $m('period') }}<select name="period" class="{{ $inp }}">@foreach ([7, 30, 90] as $d)<option value="{{ $d }}" @selected($d === 30)>{{ $m('days', ['n' => $d]) }}</option>@endforeach</select></label>
                <label class="text-slate-600">{{ $m('keyword') }}<input name="keyword" list="mk-cats" maxlength="80" placeholder="{{ $m('keyword_ph') }}" class="{{ $inp }} w-56"><datalist id="mk-cats">@foreach ($categories as $c)<option value="{{ $c }}">@endforeach</datalist></label>
                <label class="text-slate-600">{{ $m('count') }}<select name="count" class="{{ $inp }}">@foreach ([10, 20, 30] as $n)<option>{{ $n }}</option>@endforeach</select></label>
                <label class="text-slate-600">{{ $m('model') }}<select name="ai_model_id" class="{{ $inp }}">@foreach ($webModels as $wm)<option value="{{ $wm->id }}" @selected($webModel?->id === $wm->id)>{{ $wm->displayName() }}</option>@endforeach</select></label>
                <button type="submit" id="mk-go" @disabled(! $webModel) class="rounded-lg bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50">{{ $m('search') }}</button>
                <button type="button" id="mk-refresh" @disabled(! $webModel) class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50 disabled:opacity-50">{{ $m('refresh') }}</button>
                <a id="mk-export" href="#" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">{{ $m('export') }}</a>
            </div>
            @unless ($webModel)<p class="rounded-lg bg-amber-50 p-3 text-amber-800">{{ __('ai.trends.no_web_model') }}</p>@endunless
            @if ($canPurchase)
                <details class="rounded-lg border border-slate-200 p-3">
                    <summary class="cursor-pointer font-medium text-slate-700">{{ $m('po_settings') }}</summary>
                    <div class="mt-3 flex flex-wrap gap-3">
                        <label class="text-slate-600">{{ $m('supplier') }}<select id="po-sup" class="{{ $inp }}">@foreach ($suppliers as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></label>
                        <label class="text-slate-600">{{ $m('branch') }}<select id="po-br" class="{{ $inp }}">@foreach ($branches as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></label>
                    </div>
                </details>
            @endif
        </form>

        <div class="flex flex-wrap items-center gap-2 text-xs">
            @foreach (['all', 'in_stock', 'out_of_stock', 'not_in_catalog'] as $st)
                <button type="button" data-status="{{ $st }}" class="mk-st rounded-full border border-slate-300 px-3 py-1 {{ $st === 'all' ? 'bg-slate-100 font-medium' : '' }}">{{ $m('statuses.'.$st) }}</button>
            @endforeach
            <span id="mk-time" class="ms-auto text-slate-500"></span>
        </div>
        <p id="mk-status" class="hidden text-sm"></p>
        <div id="mk-body" class="grid min-h-24 gap-3 md:grid-cols-2 xl:grid-cols-3"></div>
    </div>

    <script>
    (() => {
        const L = {{ Js::from(__('ai.market')) }}, root = document.getElementById('mk'), form = document.getElementById('mk-form'), $ = id => document.getElementById(id);
        const csrf = document.querySelector('meta[name=csrf-token]').content, canPo = {{ $canPurchase ? 'true' : 'false' }}, canStudio = {{ $canStudio ? 'true' : 'false' }};
        const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        const toast = (ok, m) => (window.toastr ? toastr[ok ? 'success' : 'error'](m) : alert(m));
        const names = {{ Js::from($countries) }};
        const cls = { in_stock: 'bg-emerald-100 text-emerald-800', out_of_stock: 'bg-amber-100 text-amber-800', not_in_catalog: 'bg-slate-100 text-slate-700' };
        let items = [], status = 'all', poll = null;
        const params = () => { const p = new URLSearchParams(new FormData(form)); p.delete('ai_model_id'); const id = form.ai_model_id.value; if (id) p.set('ai_model_id', id); return p; };
        const selected = () => [...form.querySelectorAll('[name="countries[]"]:checked')];
        const post = (url, body) => fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify(body) }).then(async r => ({ ok: r.ok, d: await r.json().catch(() => ({})) }));
        const body = (extra = {}) => ({ countries: selected().map(c => c.value), period: +form.period.value, keyword: form.keyword.value, count: +form.count.value, ai_model_id: form.ai_model_id.value || null, ...extra });
        const err = d => d.message || Object.values(d.errors || {}).flat()[0] || L.failed;
        const exportLink = () => { $('mk-export').href = root.dataset.export + '?' + params(); };

        form.addEventListener('change', e => { if (e.target.name === 'countries[]' && selected().length > 5) { e.target.checked = false; toast(false, L.max_countries); } exportLink(); });
        form.addEventListener('submit', async e => { e.preventDefault(); if (!valid()) return; await start(false); });
        $('mk-refresh').addEventListener('click', async () => { if (valid()) await start(true); });
        function valid() { if (!selected().length) { toast(false, L.pick_country); return false; } return true; }
        async function start(force) {
            const { ok, d } = await post(root.dataset.refresh, body({ force: force ? 1 : 0 }));
            if (!ok) toast(false, err(d)); load();
        }
        async function load() {
            exportLink(); if (!selected().length) return;
            const r = await fetch(root.dataset.data + '?' + params(), { headers: { Accept: 'application/json' } }); const j = (await r.json().catch(() => null))?.data;
            if (!r.ok || !j) return toast(false, L.failed);
            items = j.items; $('mk-time').textContent = j.generated_at ? L.generated.replace(':t', new Date(j.generated_at).toLocaleString()) : L.never;
            const st = $('mk-status'); st.classList.toggle('hidden', !(j.running || j.error)); st.className = 'text-sm ' + (j.error ? 'text-rose-600' : 'text-slate-600'); st.textContent = j.running ? L.refreshing : (j.error || '');
            clearTimeout(poll); if (j.running) poll = setTimeout(load, 4000);
            render(j.generated_at);
        }
        function price(i) { return Object.entries(i.prices || {}).map(([c, p]) => `${esc(c)} ${p.min ?? '-'} - ${p.max ?? '-'} ${esc(p.currency)}`).join(' | ') || '-'; }
        function render(generated) {
            const list = items.filter(i => status === 'all' || i.match.status === status);
            if (!list.length) { $('mk-body').innerHTML = generated ? `<p class="col-span-full rounded-xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500">${esc(L.no_items)}</p>` : ''; return; }
            $('mk-body').innerHTML = list.map(i => { const mt = i.match, has = mt.status !== 'not_in_catalog'; return `<div class="flex flex-col rounded-xl border border-slate-200 bg-white p-4 text-sm" data-key="${esc(i.key)}">
                <div class="flex items-start justify-between gap-2"><div class="font-medium">${esc(i.name)}</div><span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium ${cls[mt.status]}">${esc(L.statuses[mt.status])}</span></div>
                <p class="mt-2 text-slate-600">${esc(i.why)}</p>
                <div class="mt-2 text-xs text-slate-500">${esc(L.trending_in)}: ${i.countries.map(c => esc(names[c] || c)).join(', ')}</div>
                <div class="mt-1 text-xs text-slate-500">${esc(L.strength)}: ${i.strength ?? '-'}/100 | ${esc(L.confidence)}: ${i.confidence === null ? '-' : Math.round(i.confidence * 100) + '%'}</div>
                <div class="mt-1 text-xs text-slate-500">${esc(L.price)}: ${price(i)}</div>
                ${i.keywords.length ? `<div class="mt-1 text-xs text-slate-500">${esc(L.keywords)}: ${i.keywords.map(esc).join(', ')}</div>` : ''}
                ${i.sources.length ? `<div class="mt-1 text-xs">${esc(L.sources)}: ${i.sources.map((u, n) => `<a class="text-brand-700 hover:underline" rel="noopener noreferrer nofollow" target="_blank" href="${esc(u)}">${n + 1}</a>`).join(' ')}</div>` : ''}
                <div class="mt-2 rounded-lg bg-slate-50 p-2 text-xs text-slate-600">${has ? `${esc(L.matched)}: <a class="text-brand-700 hover:underline" href="${root.dataset.edit}/${mt.tp_id}/edit">${esc(mt.name)}</a> | ${esc(L.our_stock)}: ${mt.qty} | ${esc(L.our_price)}: ${esc(mt.price)}` : esc(L.no_match)}</div>
                <div class="mt-3 flex flex-wrap gap-2 pt-1">
                    ${has ? `<a class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs hover:bg-slate-50" href="${root.dataset.edit}/${mt.tp_id}/edit">${esc(L.open_product)}</a>` : ''}
                    ${has && mt.status === 'out_of_stock' && canPo ? `<button type="button" data-po="${mt.product_id}" class="rounded-lg bg-brand-600 px-2.5 py-1 text-xs text-white hover:bg-brand-700">${esc(L.create_po)}</button>` : ''}
                    ${has && canStudio ? `<a class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs hover:bg-slate-50" href="${root.dataset.studio}?product_ids[]=${mt.product_id}">${esc(L.write_post)}</a>` : ''}
                    ${i.keywords.length ? `<button type="button" data-kw="${esc(i.keywords.join(', '))}" class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs text-slate-600 hover:bg-slate-50">${esc(L.copy_keywords)}</button>` : ''}
                    <button type="button" data-dismiss="${esc(i.key)}" class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs text-slate-600 hover:bg-slate-50">${esc(L.dismiss)}</button></div></div>`; }).join('');
        }
        document.addEventListener('click', async e => {
            const b = e.target.closest('button'); if (!b) return;
            if (b.classList.contains('mk-st')) { status = b.dataset.status; document.querySelectorAll('.mk-st').forEach(x => x.classList.toggle('bg-slate-100', x === b)); render(true); }
            else if (b.dataset.kw) { try { await navigator.clipboard.writeText(b.dataset.kw); toast(true, L.copied); } catch { toast(false, L.failed); } }
            else if (b.dataset.dismiss) { const { ok } = await post(root.dataset.dismiss, body({ key: b.dataset.dismiss })); if (ok) { items = items.filter(i => i.key !== b.dataset.dismiss); render(true); } }
            else if (b.dataset.po) {
                const sup = $('po-sup')?.value, br = $('po-br')?.value; if (!sup || !br) return toast(false, L.need_supplier);
                const { ok, d } = await post(root.dataset.draft, { supplier_id: sup, branch_id: br, items: [{ product_id: +b.dataset.po, quantity: 10 }] });
                if (ok) { toast(true, d.message); setTimeout(() => location.href = d.url, 700); } else toast(false, err(d));
            }
        });
        // first visit: serve the cache, or queue one run when nothing fresh exists (cache hit = no job)
        exportLink(); if (selected().length && {{ $webModel ? "true" : "false" }}) start(false); else load();
    })();
    </script>
</x-layouts.admin>
