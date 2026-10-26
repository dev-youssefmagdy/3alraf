<x-layouts.admin guard="tenant" :title="__('ai.trends.title')">
    @php
        $t = fn ($k, $a = []) => __('ai.trends.'.$k, $a);
        $tr = ['restock' => $t('restock'), 'new' => $t('new'), 'none' => $t('no_data'), 'noitems' => $t('no_items'), 'never' => $t('never'), 'generated' => $t('generated', ['t' => ':t']), 'open' => $t('open_product'), 'po' => $t('create_po'),
            'dismiss' => $t('dismiss'), 'conf' => $t('confidence'), 'src' => $t('sources'), 'price' => $t('price_range'), 'kw' => $t('keywords'), 'exists' => $t('exists_zero'), 'failed' => $t('job_failed'), 'needsup' => $t('need_supplier'), 'refreshing' => $t('refreshing')];
    @endphp
    <div id="tr" class="space-y-4" data-own="{{ route('tenant.ai.trends.own') }}" data-market="{{ route('tenant.ai.trends.market') }}" data-refresh="{{ route('tenant.ai.trends.refresh') }}"
         data-dismiss="{{ route('tenant.ai.trends.dismiss') }}" data-draft="{{ route('tenant.ai.trends.draft') }}" data-export="{{ route('tenant.ai.trends.export') }}" data-edit="{{ url('admin/catalog') }}"
         data-t="{{ json_encode($tr, JSON_UNESCAPED_UNICODE) }}">
        <div class="flex flex-wrap items-center gap-2 border-b border-slate-200">
            <button type="button" data-tab="own" class="tr-tab -mb-px border-b-2 border-brand-600 px-3 py-2 text-sm font-medium text-brand-700">{{ __('ai.trends.tab_own') }}</button>
            <button type="button" data-tab="market" class="tr-tab -mb-px border-b-2 border-transparent px-3 py-2 text-sm font-medium text-slate-500 hover:text-slate-700">{{ __('ai.trends.tab_market') }}</button>
            <a id="tr-export" href="#" class="ms-auto rounded-lg border border-slate-300 px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50">{{ __('ai.trends.export') }}</a>
        </div>

        {{-- OWN --}}
        <section id="pane-own" class="space-y-3">
            <div class="flex flex-wrap items-end gap-3 text-sm">
                <label class="text-slate-600">{{ __('ai.trends.period') }}
                    <select id="f-period" class="mt-1 block rounded-lg border border-slate-300 bg-white px-2 py-1.5">@foreach ([7, 30, 90] as $d)<option value="{{ $d }}" @selected($d === 30)>{{ __('ai.trends.days', ['n' => $d]) }}</option>@endforeach</select></label>
                <label class="text-slate-600">{{ __('ai.trends.category') }}
                    <select id="f-cat" class="mt-1 block rounded-lg border border-slate-300 bg-white px-2 py-1.5"><option value="">{{ __('ai.trends.all_categories') }}</option>@foreach ($categories as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></label>
                <label class="text-slate-600">{{ __('ai.trends.sort') }}
                    <select id="f-sort" class="mt-1 block rounded-lg border border-slate-300 bg-white px-2 py-1.5">@foreach (__('ai.trends.sorts') as $k => $n)<option value="{{ $k }}">{{ $n }}</option>@endforeach</select></label>
                <label class="flex items-center gap-2 pb-1.5 text-slate-600"><input type="checkbox" id="f-nar" class="rounded border-slate-300">{{ __('ai.trends.narrative') }}</label>
                <div class="ms-auto inline-flex overflow-hidden rounded-lg border border-slate-300 text-xs">
                    <button type="button" data-view="cards" class="tr-view bg-slate-100 px-3 py-1.5">{{ __('ai.trends.cards') }}</button>
                    <button type="button" data-view="table" class="tr-view px-3 py-1.5">{{ __('ai.trends.table') }}</button>
                </div>
            </div>
            <div id="own-nar" class="hidden whitespace-pre-line rounded-xl border border-brand-200 bg-brand-50 p-3 text-sm text-slate-700"></div>
            <div id="own-body" class="min-h-24"></div>
        </section>

        {{-- MARKET --}}
        <section id="pane-market" class="hidden space-y-3">
            <div class="flex flex-wrap items-center gap-3 text-sm">
                <p class="text-xs text-slate-500">{{ __('ai.trends.estimate', ['c' => $country]) }}</p>
                <span id="mk-time" class="text-xs text-slate-500"></span>
                <button type="button" id="mk-refresh" @disabled(! $webModel) class="ms-auto rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-brand-700 disabled:opacity-50">{{ __('ai.trends.refresh') }}</button>
            </div>
            @unless ($webModel)<p class="rounded-lg bg-amber-50 p-3 text-sm text-amber-800">{{ __('ai.trends.no_web_model') }}</p>@endunless
            @if ($canPurchase)
                <details class="rounded-xl border border-slate-200 bg-white p-3 text-sm">
                    <summary class="cursor-pointer font-medium text-slate-700">{{ __('ai.trends.po_settings') }}</summary>
                    <div class="mt-3 flex flex-wrap gap-3">
                        <label class="text-slate-600">{{ __('ai.trends.supplier') }}<select id="po-sup" class="mt-1 block rounded-lg border border-slate-300 bg-white px-2 py-1.5">@foreach ($suppliers as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></label>
                        <label class="text-slate-600">{{ __('ai.trends.branch') }}<select id="po-br" class="mt-1 block rounded-lg border border-slate-300 bg-white px-2 py-1.5">@foreach ($branches as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></label>
                    </div>
                </details>
            @endif
            <p id="mk-status" class="hidden text-sm"></p>
            <div id="mk-body" class="grid min-h-24 gap-3 md:grid-cols-2 xl:grid-cols-3"></div>
        </section>
    </div>

    <script>
    (() => {
        const L = {{ Js::from(__('ai.trends')) }}, root = document.getElementById('tr'), T = JSON.parse(root.dataset.t), $ = id => document.getElementById(id);
        const csrf = document.querySelector('meta[name=csrf-token]').content, canPo = {{ $canPurchase ? 'true' : 'false' }};
        const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        const toast = (ok, m) => (window.toastr ? toastr[ok ? 'success' : 'error'](m) : alert(m));
        let tab = 'own', view = 'cards', poll = null, rows = [];
        const q = () => new URLSearchParams({ period: $('f-period').value, category_id: $('f-cat').value, sort: $('f-sort').value });
        const post = (url, body) => fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify(body) }).then(async r => ({ ok: r.ok, d: await r.json().catch(() => ({})) }));
        const badge = (t, c) => `<span class="rounded-full px-2 py-0.5 text-[11px] font-medium ${c}">${esc(t)}</span>`;
        const pct = v => v === null ? '-' : `<span class="${v >= 0 ? 'text-emerald-600' : 'text-rose-600'}">${v > 0 ? '+' : ''}${v}%</span>`;

        function setTab(t) {
            tab = t;
            document.querySelectorAll('.tr-tab').forEach(b => { const on = b.dataset.tab === t; b.classList.toggle('border-brand-600', on); b.classList.toggle('text-brand-700', on); b.classList.toggle('border-transparent', !on); b.classList.toggle('text-slate-500', !on); });
            $('pane-own').classList.toggle('hidden', t !== 'own'); $('pane-market').classList.toggle('hidden', t !== 'market');
            t === 'own' ? loadOwn() : loadMarket(); exportLink();
        }
        function exportLink() { const p = q(); p.set('tab', tab); $('tr-export').href = root.dataset.export + '?' + p; }

        async function loadOwn() {
            exportLink(); $('own-body').innerHTML = '<div class="h-24 animate-pulse rounded-xl bg-slate-100"></div>';
            const p = q(); if ($('f-nar').checked) p.set('narrative', 1);
            const r = await fetch(root.dataset.own + '?' + p, { headers: { Accept: 'application/json' } }); const j = await r.json().catch(() => null);
            if (!r.ok || !j) { $('own-body').innerHTML = ''; return toast(false, T.failed); }
            rows = j.data.rows; const n = $('own-nar');
            n.classList.toggle('hidden', !(j.data.narrative || j.data.narrative_error)); n.textContent = j.data.narrative || j.data.narrative_error || '';
            renderOwn();
        }
        function renderOwn() {
            if (!rows.length) { $('own-body').innerHTML = `<p class="rounded-xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500">${esc(T.none)}</p>`; return; }
            const flag = r => (r.restock ? badge(T.restock, 'bg-amber-100 text-amber-800') : '') + (r.is_new ? badge(T.new, 'bg-sky-100 text-sky-800') : '');
            const link = r => r.tp_id ? `<a class="text-brand-700 hover:underline" href="${root.dataset.edit}/${r.tp_id}/edit">${esc(r.name)}</a>` : esc(r.name);
            if (view === 'table') {
                $('own-body').innerHTML = `<div class="overflow-x-auto rounded-xl border border-slate-200 bg-white"><table class="w-full text-sm"><thead class="bg-slate-50 text-xs text-slate-500"><tr>${['product', 'units', 'previous', 'velocity', 'growth', 'revenue', 'margin', 'available', 'days_left'].map(k => `<th class="px-3 py-2 text-start">${esc(L[k])}</th>`).join('')}</tr></thead><tbody>${rows.map(r =>
                    `<tr class="border-t border-slate-100"><td class="px-3 py-2">${link(r)} ${flag(r)}</td><td class="px-3 py-2">${r.qty}</td><td class="px-3 py-2">${r.prev_qty}</td><td class="px-3 py-2">${r.velocity}</td><td class="px-3 py-2">${pct(r.growth_pct)}</td><td class="px-3 py-2">${esc(r.revenue)}</td><td class="px-3 py-2">${r.margin_pct === null ? '-' : r.margin_pct + '%'}</td><td class="px-3 py-2">${r.available}</td><td class="px-3 py-2">${r.days_of_stock ?? '-'}</td></tr>`).join('')}</tbody></table></div>`;
                return;
            }
            $('own-body').innerHTML = `<div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">${rows.map(r => `<div class="rounded-xl border border-slate-200 bg-white p-4 text-sm"><div class="flex items-start justify-between gap-2"><div class="font-medium">${link(r)}</div><div class="flex gap-1">${flag(r)}</div></div>
                <div class="mt-1 text-xs text-slate-500">${esc(r.sku)}</div>
                <dl class="mt-3 grid grid-cols-3 gap-2 text-center text-xs"><div><dd class="text-base font-semibold">${r.qty}</dd><dt class="text-slate-500">${esc(L.units)}</dt></div><div><dd class="text-base font-semibold">${pct(r.growth_pct)}</dd><dt class="text-slate-500">${esc(L.growth)}</dt></div><div><dd class="text-base font-semibold">${r.margin_pct === null ? '-' : r.margin_pct + '%'}</dd><dt class="text-slate-500">${esc(L.margin)}</dt></div></dl>
                <div class="mt-3 text-xs text-slate-600">${esc(L.available)}: ${r.available} - ${esc(L.days_left)}: ${r.days_of_stock ?? '-'}</div>
                ${canPo && r.restock ? `<button type="button" data-po="${r.product_id}" data-qty="${r.suggested_qty}" class="mt-3 rounded-lg border border-brand-300 px-2.5 py-1 text-xs text-brand-700 hover:bg-brand-50">${esc(T.po)} (${r.suggested_qty})</button>` : ''}</div>`).join('')}</div>`;
        }

        async function loadMarket() {
            exportLink();
            const r = await fetch(root.dataset.market, { headers: { Accept: 'application/json' } }); const j = (await r.json().catch(() => null))?.data; if (!j) return;
            $('mk-time').textContent = j.generated_at ? T.generated.replace(':t', new Date(j.generated_at).toLocaleString()) : T.never;
            const st = $('mk-status'); st.classList.toggle('hidden', !(j.running || j.error)); st.className = 'text-sm ' + (j.error ? 'text-rose-600' : 'text-slate-600'); st.textContent = j.running ? T.refreshing : (j.error || '');
            $('mk-refresh').disabled = !j.has_model || j.running;
            clearTimeout(poll); if (j.running && tab === 'market') poll = setTimeout(loadMarket, 4000);
            if (!j.items.length) { $('mk-body').innerHTML = j.generated_at ? `<p class="col-span-full rounded-xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500">${esc(T.noitems)}</p>` : ''; return; }
            $('mk-body').innerHTML = j.items.map(i => `<div class="flex flex-col rounded-xl border border-slate-200 bg-white p-4 text-sm" data-key="${esc(i.key)}">
                <div class="flex items-start justify-between gap-2"><div class="font-medium">${esc(i.name)}</div>${i.restock ? badge(T.restock, 'bg-amber-100 text-amber-800') : ''}</div>
                ${i.restock ? `<div class="mt-1 text-xs text-amber-700">${esc(T.exists)}: ${esc(i.product_name)}</div>` : ''}
                <p class="mt-2 text-slate-600">${esc(i.why)}</p>
                <div class="mt-2 text-xs text-slate-500">${esc(T.price)}: ${i.price_min ?? '-'} - ${i.price_max ?? '-'} | ${esc(T.conf)}: ${i.confidence === null ? '-' : Math.round(i.confidence * 100) + '%'}</div>
                ${i.keywords.length ? `<div class="mt-1 text-xs text-slate-500">${esc(T.kw)}: ${i.keywords.map(esc).join(', ')}</div>` : ''}
                ${i.sources.length ? `<div class="mt-1 text-xs">${esc(T.src)}: ${i.sources.map((u, n) => `<a class="text-brand-700 hover:underline" rel="noopener noreferrer nofollow" target="_blank" href="${esc(u)}">${n + 1}</a>`).join(' ')}</div>` : ''}
                <div class="mt-3 flex flex-wrap gap-2 pt-1">
                    ${i.restock && i.tp_id ? `<a class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs hover:bg-slate-50" href="${root.dataset.edit}/${i.tp_id}/edit">${esc(T.open)}</a>` : ''}
                    ${i.restock && canPo ? `<button type="button" data-po="${i.product_id}" data-qty="10" class="rounded-lg bg-brand-600 px-2.5 py-1 text-xs text-white hover:bg-brand-700">${esc(T.po)}</button>` : ''}
                    <button type="button" data-dismiss="${esc(i.key)}" class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs text-slate-600 hover:bg-slate-50">${esc(T.dismiss)}</button></div></div>`).join('');
        }

        document.addEventListener('click', async e => {
            const b = e.target.closest('button'); if (!b) return;
            if (b.dataset.po) {
                const sup = $('po-sup')?.value, br = $('po-br')?.value; if (!sup || !br) return toast(false, T.needsup);
                const { ok, d } = await post(root.dataset.draft, { supplier_id: sup, branch_id: br, items: [{ product_id: +b.dataset.po, quantity: +b.dataset.qty }] });
                if (ok) { toast(true, d.message); setTimeout(() => location.href = d.url, 700); } else toast(false, d.message || Object.values(d.errors || {}).flat()[0] || T.failed);
            } else if (b.dataset.dismiss) {
                const { ok } = await post(root.dataset.dismiss, { key: b.dataset.dismiss }); if (ok) b.closest('[data-key]').remove();
            } else if (b.id === 'mk-refresh') {
                const { ok, d } = await post(root.dataset.refresh, { force: 1 }); if (!ok) toast(false, d.message || T.failed); loadMarket();
            } else if (b.classList.contains('tr-tab')) setTab(b.dataset.tab);
            else if (b.classList.contains('tr-view')) { view = b.dataset.view; document.querySelectorAll('.tr-view').forEach(x => x.classList.toggle('bg-slate-100', x === b)); renderOwn(); }
        });
        ['f-period', 'f-cat', 'f-sort', 'f-nar'].forEach(id => $(id).addEventListener('change', loadOwn));
        // first visit to the market tab auto-queues a refresh when nothing fresh is cached (cache hit = no job)
        const first = loadMarket; loadOwn();
        document.querySelector('[data-tab=market]').addEventListener('click', async () => { await post(root.dataset.refresh, {}); first(); }, { once: true });
    })();
    </script>
</x-layouts.admin>
