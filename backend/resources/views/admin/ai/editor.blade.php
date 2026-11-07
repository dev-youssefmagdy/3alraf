<x-layouts.admin guard="tenant" :title="__('ai.editor.title')">
    @php
        $t = fn ($k, $a = []) => __('ai.editor.'.$k, $a);
        $inp = 'mt-1 block w-full rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-sm';
    @endphp
    <div id="ed" class="space-y-4" data-store="{{ route('tenant.ai.editor.store') }}" data-products="{{ route('tenant.ai.editor.products') }}" data-history="{{ route('tenant.ai.editor.history') }}" data-base="{{ url('admin/ai/editor/generations') }}">
        <p class="text-sm text-slate-600">{{ $t('intro') }}</p>
        <div class="grid gap-4 lg:grid-cols-3">
            <form id="ed-form" class="space-y-4 rounded-xl border border-slate-200 bg-white p-4 lg:col-span-1" autocomplete="off">
                <div>
                    <label class="block text-sm text-slate-600" for="ed-q">{{ $t('search') }}</label>
                    <input id="ed-q" type="search" class="{{ $inp }}" maxlength="80">
                    <ul id="ed-results" class="mt-1 max-h-40 divide-y divide-slate-100 overflow-auto rounded-lg border border-slate-200 text-sm"></ul>
                    <p class="mt-2 text-xs text-slate-500">{{ $t('selected', ['n' => \App\Services\Ai\ProductEditorService::MAX_BULK]) }}</p>
                    <ul id="ed-picked" class="mt-1 flex flex-wrap gap-1"></ul>
                </div>
                <fieldset><legend class="text-sm text-slate-600">{{ $t('fields_t') }}</legend>
                    @foreach ($fields as $f)<label class="me-3 inline-flex items-center gap-1 text-sm"><input type="checkbox" name="fields[]" value="{{ $f }}" @checked(in_array($f, ['name', 'description'])) class="rounded border-slate-300">{{ $t('fields.'.$f) }}</label>@endforeach
                </fieldset>
                <fieldset><legend class="text-sm text-slate-600">{{ $t('locales_t') }}</legend>
                    @foreach ($locales as $l)<label class="me-3 inline-flex items-center gap-1 text-sm"><input type="checkbox" name="locales[]" value="{{ $l }}" @checked($loop->first) class="rounded border-slate-300">{{ strtoupper($l) }}</label>@endforeach
                </fieldset>
                <label class="block text-sm text-slate-600">{{ $t('preset_t') }}
                    <select name="preset" class="{{ $inp }}">@foreach ($presets as $p)<option value="{{ $p }}">{{ $t('presets.'.$p) }}</option>@endforeach</select></label>
                <label class="block text-sm text-slate-600">{{ $t('instruction') }}<textarea name="instruction" rows="3" maxlength="800" class="{{ $inp }}"></textarea></label>
                @if ($text->count() > 1)
                    <label class="block text-sm text-slate-600">{{ __('ai.studio.fields.ai_model_id') }}<select data-search name="ai_model_id" class="{{ $inp }}">@foreach ($text as $m)<option value="{{ $m->id }}">{{ $m->displayName() }}</option>@endforeach</select></label>
                @endif
                <button id="ed-go" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50">{{ $t('generate') }}</button>
                <p class="text-xs text-slate-500">{{ $t('protected') }}</p>
            </form>
            <div class="space-y-3 lg:col-span-2">
                <p id="ed-bulk" class="hidden rounded-lg bg-amber-50 p-2 text-sm text-amber-800">{{ $t('bulk') }}</p>
                <div id="ed-out" class="space-y-3"></div>
                <h3 class="pt-2 text-sm font-semibold text-slate-700">{{ $t('history') }}</h3>
                <div id="ed-hist" class="space-y-3"></div>
            </div>
        </div>
    </div>
    <script>
    (() => {
        const L = {{ Js::from(__('ai.editor')) }}, root = document.getElementById('ed'), $ = id => document.getElementById(id);
        const csrf = document.querySelector('meta[name=csrf-token]').content, hdr = {'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json'};
        const el = (tag, cls, txt) => { const e = document.createElement(tag); if (cls) e.className = cls; if (txt != null) e.textContent = txt; return e; };
        const picked = new Map(), cards = new Map();
        const toast = (ok, m) => { if (window.toastr) (ok ? toastr.success : toastr.error)(m); };
        const call = async (url, method, body) => { const r = await fetch(url, {method, headers: hdr, body: body ? JSON.stringify(body) : undefined}); const j = await r.json().catch(() => ({})); if (!r.ok || j.success === false) { throw new Error(j.message || Object.values(j.errors || {}).flat()[0] || 'Error'); } return j; };
        const textOf = html => { const d = new DOMParser().parseFromString(html || '', 'text/html'); return d.body.textContent || ''; };   // diff shown as text only: AI HTML is never injected into the page

        function renderPicked() {
            const ul = $('ed-picked'); ul.replaceChildren();
            picked.forEach((n, id) => { const li = el('li', 'flex items-center gap-1 rounded bg-slate-100 px-2 py-0.5 text-xs', n), x = el('button', 'text-slate-500', 'x'); x.type = 'button'; x.onclick = () => { picked.delete(id); renderPicked(); }; li.append(x); ul.append(li); });
            $('ed-bulk').classList.toggle('hidden', picked.size < 2);
        }
        let qt; $('ed-q').addEventListener('input', e => { clearTimeout(qt); qt = setTimeout(async () => {
            const j = await (await fetch(root.dataset.products + '?q=' + encodeURIComponent(e.target.value), {headers: {Accept: 'application/json'}})).json(), ul = $('ed-results'); ul.replaceChildren();
            j.data.forEach(p => { const li = el('li', 'cursor-pointer px-2 py-1 hover:bg-slate-50', p.name + (p.sku ? ' (' + p.sku + ')' : '')); li.onclick = () => { picked.set(p.id, p.name); renderPicked(); }; ul.append(li); });
        }, 250); });
        $('ed-q').dispatchEvent(new Event('input'));

        function card(g) {
            const c = el('article', 'space-y-2 rounded-xl border border-slate-200 bg-white p-4'), top = el('div', 'flex flex-wrap items-center gap-2 text-sm');
            top.append(el('span', 'font-semibold text-slate-800', g.product || '#' + g.id), el('span', 'rounded bg-slate-100 px-2 py-0.5 text-xs', g.status_label)); c.append(top);
            if (g.status === 'queued') c.append(el('p', 'text-sm text-slate-600', L.working));
            if (g.status === 'failed') c.append(el('p', 'rounded-lg bg-red-50 p-2 text-sm text-red-700', L.failed + (g.error ? ': ' + g.error : '')));
            const o = g.output || {}, props = o.proposals || {}, boxes = [], draft = g.status === 'draft', applied = o.applied || [];
            (o.warnings || []).forEach(w => c.append(el('p', 'text-xs text-amber-700', w)));
            Object.keys(props).forEach(f => Object.keys(props[f]).forEach(l => {
                const key = f + '.' + l, row = el('div', 'rounded-lg border border-slate-200 p-2'), h = el('label', 'mb-1 flex items-center gap-2 text-xs font-semibold text-slate-600');
                if (draft) { const cb = el('input'); cb.type = 'checkbox'; cb.checked = true; cb.value = key; boxes.push(cb); h.append(cb); }
                h.append(el('span', '', (L.fields[f] || f) + ' / ' + l.toUpperCase()));
                if (applied.includes(key)) h.append(el('span', 'rounded bg-green-100 px-1.5 text-green-700', L.applied));
                const grid = el('div', 'grid gap-2 sm:grid-cols-2'), a = el('div', 'rounded bg-red-50 p-2 text-sm'), b = el('div', 'rounded bg-green-50 p-2 text-sm');
                a.append(el('p', 'text-xs text-slate-500', L.current), el('p', 'whitespace-pre-line', textOf(props[f][l].old))); b.append(el('p', 'text-xs text-slate-500', L.proposed), el('p', 'whitespace-pre-line', textOf(props[f][l].new)));
                a.dir = b.dir = 'auto'; grid.append(a, b); row.append(h, grid); c.append(row);
            }));
            const bar = el('div', 'flex flex-wrap gap-2 pt-1'), btn = (t, cls, fn) => { const b = el('button', 'rounded-lg px-3 py-1.5 text-sm ' + cls, t); b.type = 'button'; b.onclick = async () => { b.disabled = true; try { await fn(); } catch (e) { toast(false, e.message); b.disabled = false; } }; return b; };
            if (draft) {
                bar.append(btn(L.accept_sel, 'bg-brand-600 text-white', async () => { const r = await call(root.dataset.base + '/' + g.id + '/apply', 'POST', {accept: boxes.filter(x => x.checked).map(x => x.value)}); toast(true, L.applied_ok); put(r.data); }),
                    btn(L.reject_all, 'border border-slate-300', async () => { const r = await call(root.dataset.base + '/' + g.id + '/reject', 'POST'); toast(true, L.rejected_ok); put(r.data); }));
            }
            if (g.can_undo) bar.append(btn(L.undo, 'border border-slate-300', async () => { const r = await call(root.dataset.base + '/' + g.id + '/undo', 'POST'); toast(true, L.undo_ok); put(r.data); }));
            if ((o.undone_at)) bar.append(el('span', 'text-xs text-slate-500', L.undone));
            if (bar.children.length) c.append(bar);
            return c;
        }
        function put(g, into) {
            const old = cards.get(g.id), n = card(g); cards.set(g.id, n);
            if (old) old.replaceWith(n); else (into || $('ed-out')).prepend(n);
            if (g.status === 'queued') setTimeout(async () => { try { put((await call(root.dataset.base + '/' + g.id, 'GET')).data); } catch (e) {} }, 2500);
        }
        $('ed-form').addEventListener('submit', async e => {
            e.preventDefault(); const f = new FormData(e.target), go = $('ed-go'); go.disabled = true;
            try {
                const j = await call(root.dataset.store, 'POST', {product_ids: [...picked.keys()], fields: f.getAll('fields[]'), locales: f.getAll('locales[]'), preset: f.get('preset'), instruction: f.get('instruction'), ai_model_id: f.get('ai_model_id') || null});
                j.data.forEach(g => put(g));
            } catch (er) { toast(false, er.message); } finally { go.disabled = false; }
        });
        (async () => { try { (await (await fetch(root.dataset.history, {headers: {Accept: 'application/json'}})).json()).data.forEach(g => { const n = card(g); cards.set(g.id, n); $('ed-hist').append(n); }); } catch (e) {} })();
    })();
    </script>
</x-layouts.admin>
