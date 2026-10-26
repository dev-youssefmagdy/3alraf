<x-layouts.admin guard="tenant" :title="__('ai.studio.title')">
    @php
        $s = fn ($k, $a = []) => __('ai.studio.'.$k, $a);
        $inp = 'mt-1 block w-full rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-sm';
        $lbl = 'block text-sm text-slate-600';
    @endphp
    <div id="st" class="space-y-4" data-store="{{ route('tenant.ai.studio.store') }}" data-history="{{ route('tenant.ai.studio.history') }}" data-base="{{ url('admin/ai/studio/generations') }}" data-platforms="{{ json_encode($platforms, JSON_UNESCAPED_UNICODE) }}">
        <div class="flex flex-wrap items-center gap-2 border-b border-slate-200">
            @foreach (['social' => 'tab_posts', 'marketing' => 'tab_campaigns', 'history' => 'tab_history'] as $k => $t)
                <button type="button" data-tab="{{ $k }}" class="st-tab -mb-px border-b-2 {{ $loop->first ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500' }} px-3 py-2 text-sm font-medium">{{ $s($t) }}</button>
            @endforeach
            <p class="ms-auto text-xs text-slate-500">{{ $s('no_publish') }}</p>
        </div>

        <div id="pane-form" class="grid gap-4 lg:grid-cols-2">
            <form id="st-form" class="space-y-4 rounded-xl border border-slate-200 bg-white p-4" autocomplete="off">
                <input type="hidden" name="mode" value="social">
                <h3 class="font-semibold text-slate-800">{{ $s('step_subject') }}</h3>
                <p class="text-xs text-slate-500">{{ $s('subject_hint') }}</p>
                <label class="{{ $lbl }}">{{ $s('fields.product_ids') }}
                    <select name="product_ids[]" multiple size="5" class="{{ $inp }}">@foreach ($products as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></label>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="{{ $lbl }}">{{ $s('fields.bundle_id') }}<select name="bundle_id" class="{{ $inp }}"><option value="">-</option>@foreach ($bundles as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></label>
                    <label class="{{ $lbl }}">{{ $s('fields.flash_sale_id') }}<select name="flash_sale_id" class="{{ $inp }}"><option value="">-</option>@foreach ($sales as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></label>
                </div>
                <label class="{{ $lbl }}">{{ $s('fields.brief') }} ({{ $s('optional') }})<textarea name="brief" rows="3" maxlength="1500" class="{{ $inp }}"></textarea></label>

                <div data-only="marketing" class="hidden space-y-3 rounded-lg bg-slate-50 p-3">
                    <div class="grid gap-3 sm:grid-cols-3">
                        <label class="{{ $lbl }}">{{ $s('fields.campaign_type') }}<select name="campaign_type" class="{{ $inp }}">@foreach ($campaigns as $c)<option value="{{ $c }}">{{ $s('campaigns.'.$c) }}</option>@endforeach</select></label>
                        <label class="{{ $lbl }}">{{ $s('fields.event') }}<select name="event" class="{{ $inp }}"><option value="">{{ $s('no_event') }}</option>@foreach ($events as $e)<option value="{{ $e['key'] }}">{{ $e['name'] }} ({{ $e['date'] }})</option>@endforeach</select></label>
                        <label class="{{ $lbl }}">{{ $s('fields.variants') }} (A/B)<select name="variants" class="{{ $inp }}">@foreach ([1, 2, 3] as $n)<option>{{ $n }}</option>@endforeach</select></label>
                    </div>
                    <p class="text-xs text-slate-500">{{ $s('event_hint') }}</p>
                </div>

                <h3 class="pt-2 font-semibold text-slate-800">{{ $s('step_channel') }}</h3>
                <div class="grid gap-3 sm:grid-cols-3">
                    <label class="{{ $lbl }}">{{ $s('fields.platform') }}<select name="platform" id="st-platform" class="{{ $inp }}">@foreach ($platforms as $k => $p)<option value="{{ $k }}" @selected($k === ($defaultPlatforms[0] ?? 'instagram'))>{{ $p['label'] }}</option>@endforeach</select></label>
                    <label class="{{ $lbl }}">{{ $s('fields.goal') }}<input name="goal" list="st-goals" value="{{ $s('goals.sales') }}" maxlength="200" class="{{ $inp }}"><datalist id="st-goals">@foreach (__('ai.studio.goals') as $g)<option value="{{ $g }}">@endforeach</datalist></label>
                    <label class="{{ $lbl }}">{{ $s('fields.tone') }}<input name="tone" list="st-tones" value="{{ $s('tones.friendly') }}" maxlength="60" class="{{ $inp }}"><datalist id="st-tones">@foreach (__('ai.studio.tones') as $g)<option value="{{ $g }}">@endforeach</datalist></label>
                </div>
                <div class="{{ $lbl }}">{{ $s('fields.languages') }}
                    <div class="mt-1 flex flex-wrap gap-3">@foreach ($locales as $l)<label class="flex items-center gap-1 text-sm"><input type="checkbox" name="languages[]" value="{{ $l }}" @checked($loop->first) class="rounded border-slate-300">{{ strtoupper($l) }}</label>@endforeach</div></div>
                <label class="{{ $lbl }}">{{ $s('fields.cta') }} ({{ $s('optional') }})<input name="cta" maxlength="120" class="{{ $inp }}"></label>

                <h3 class="pt-2 font-semibold text-slate-800">{{ $s('step_output') }}</h3>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="{{ $lbl }}">{{ $s('fields.format') }}
                        <select name="format" id="st-format" class="{{ $inp }}">
                            <option value="text">{{ $s('formats.text') }}</option>
                            @if ($imageModels->count())<option value="image">{{ $s('formats.image') }}</option>@endif
                            @if ($videoModels->count())<option value="video">{{ $s('formats.video') }}</option>@endif
                            @if ($imageModels->count() && $videoModels->count())<option value="all">{{ $s('formats.all') }}</option>@endif
                        </select></label>
                    <label class="{{ $lbl }}">{{ $s('fields.ai_model_id') }}<select data-search name="ai_model_id" class="{{ $inp }}"><option value=""></option>@foreach ($textModels as $m)<option value="{{ $m->id }}">{{ $m->label }}</option>@endforeach</select></label>
                    @if ($imageModels->count())<label class="{{ $lbl }}">{{ $s('fields.image_model_id') }}<select data-search name="image_model_id" class="{{ $inp }}"><option value=""></option>@foreach ($imageModels as $m)<option value="{{ $m->id }}">{{ $m->label }}</option>@endforeach</select></label>@endif
                    @if ($videoModels->count())<label class="{{ $lbl }}">{{ $s('fields.video_model_id') }}<select data-search name="video_model_id" class="{{ $inp }}"><option value=""></option>@foreach ($videoModels as $m)<option value="{{ $m->id }}">{{ $m->label }}</option>@endforeach</select></label>@endif
                </div>
                <p id="st-err" class="hidden rounded-lg bg-red-50 p-2 text-sm text-red-700"></p>
                <button type="submit" id="st-go" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50">{{ $s('generate') }}</button>
            </form>

            <div class="space-y-3">
                <div id="st-preview" class="rounded-xl border border-slate-200 bg-white p-4"></div>
                <div id="st-result" class="space-y-3"></div>
            </div>
        </div>

        <section id="pane-history" class="hidden space-y-3"><div id="hist" class="grid gap-3 md:grid-cols-2"></div></section>
    </div>

    <script>
    (() => {
        const L = {{ Js::from(__('ai.studio')) }}, root = document.getElementById('st'), P = JSON.parse(root.dataset.platforms), $ = id => document.getElementById(id);
        const csrf = document.querySelector('meta[name=csrf-token]').content, form = $('st-form'), hdr = {'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json'};
        const el = (tag, cls, txt) => { const e = document.createElement(tag); if (cls) e.className = cls; if (txt != null) e.textContent = txt; return e; };
        const fmt = (s, a) => Object.entries(a || {}).reduce((t, [k, v]) => t.replaceAll(':' + k, v), s);
        let poll = null;

        function renderPreview() {
            const p = P[$('st-platform').value], box = $('st-preview'); box.replaceChildren();
            box.append(el('p', 'text-sm font-semibold text-slate-700', L.preview + ' - ' + p.label));
            box.append(el('p', 'text-xs text-slate-500', fmt(L.rules, {c: p.chars, h: p.hashtags, r: p.aspect})));
            const [w, h] = p.aspect.split(':').map(Number), frame = el('div', 'mx-auto mt-3 flex max-w-[220px] items-center justify-center rounded-lg border border-dashed border-slate-300 bg-slate-50 text-xs text-slate-400', p.aspect);
            frame.style.aspectRatio = w + '/' + h; box.append(frame);
        }

        function card(g) {
            const c = el('article', 'space-y-3 rounded-xl border border-slate-200 bg-white p-4');
            const top = el('div', 'flex flex-wrap items-center gap-2 text-xs text-slate-500');
            top.append(el('span', 'rounded bg-slate-100 px-2 py-0.5', g.status_label), el('span', '', (P[g.inputs.platform] || {}).label || ''), el('span', '', new Date(g.created_at).toLocaleString()));
            c.append(top);
            if (g.status === 'queued') c.append(el('p', 'text-sm text-slate-600', L.working));
            if (g.status === 'failed') c.append(el('p', 'rounded-lg bg-red-50 p-2 text-sm text-red-700', L.failed + (g.error ? ': ' + g.error : '')));
            const out = g.output || {}, media = g.media || [], plat = P[g.inputs.platform] || {};
            media.forEach(m => {
                if (m.state === 'ready' && m.type === 'image') {
                    const a = el('a', 'block'); a.href = m.url; a.download = ''; const i = el('img', 'mx-auto max-h-72 rounded-lg'); i.src = m.url; i.alt = ''; a.append(i); c.append(a, dl(m.url, L.dl_image));
                } else if (m.state === 'ready' && m.type === 'video') {
                    const v = el('video', 'mx-auto max-h-72 rounded-lg'); v.controls = true; v.src = m.url; c.append(v, dl(m.url, L.dl_video));
                } else if (m.state === 'pending') c.append(el('p', 'text-sm text-slate-500', L.video_pending));
                else if (m.state === 'failed') c.append(el('p', 'rounded-lg bg-amber-50 p-2 text-sm text-amber-800', fmt(L.media_failed, {m: m.error || ''})));
            });
            (out.variants || []).forEach(v => {
                const b = el('div', 'rounded-lg border border-slate-200 p-3'), h = el('div', 'mb-2 flex items-center gap-2 text-xs text-slate-500');
                h.append(el('span', 'font-semibold text-slate-700', L.variant + ' ' + v.label), el('span', '', v.language.toUpperCase()), el('span', v.length > plat.chars ? 'text-red-600' : '', v.length + '/' + plat.chars + ' ' + L.chars));
                const t = el('p', 'whitespace-pre-line text-sm text-slate-800', v.text); t.dir = 'auto';
                const bar = el('div', 'mt-2 flex flex-wrap gap-2'), copy = el('button', 'rounded border border-slate-300 px-2 py-1 text-xs hover:bg-slate-50', L.copy); copy.type = 'button';
                copy.onclick = async () => { try { await navigator.clipboard.writeText(v.text); copy.textContent = L.copied; setTimeout(() => copy.textContent = L.copy, 1500); } catch (e) {} };
                const dt = el('button', 'rounded border border-slate-300 px-2 py-1 text-xs hover:bg-slate-50', L.dl_text); dt.type = 'button';
                dt.onclick = () => { const a = el('a'); a.href = URL.createObjectURL(new Blob([v.text], {type: 'text/plain;charset=utf-8'})); a.download = 'post-' + g.id + v.label + '-' + v.language + '.txt'; a.click(); URL.revokeObjectURL(a.href); };
                bar.append(copy, dt); b.append(h, t, bar); c.append(b);
            });
            if ((out.schedule || []).length) { const s = el('div', 'text-xs text-slate-600'); s.append(el('p', 'font-semibold', L.schedule)); out.schedule.forEach(x => s.append(el('p', '', x.day + ' ' + x.time + ' - ' + x.reason))); c.append(s); }
            if ((out.warnings || []).length) { const w = el('ul', 'list-disc ps-5 text-xs text-amber-700'); out.warnings.forEach(x => w.append(el('li', '', fmt(L['w_' + x.code] || x.code, {v: x.value || ''})))); c.append(w); }
            const act = el('div', 'flex gap-2'); 
            if (g.status !== 'queued') { const r = el('button', 'rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-medium text-white', L.regenerate); r.type = 'button'; r.onclick = () => regen(g.id); act.append(r); }
            const d = el('button', 'rounded-lg border border-red-200 px-3 py-1.5 text-xs text-red-700', L.delete); d.type = 'button'; d.onclick = () => del(g.id); act.append(d);
            c.append(act);
            return c;
        }
        const dl = (u, t) => { const a = el('a', 'inline-block rounded border border-slate-300 px-2 py-1 text-xs hover:bg-slate-50', t); a.href = u; a.download = ''; return a; };

        async function api(url, method, body) {
            const r = await fetch(url, {method, headers: hdr, body: body ? JSON.stringify(body) : undefined}), j = await r.json().catch(() => ({}));
            if (!r.ok) throw Object.assign(new Error(j.message || L.failed), {errors: j.errors});
            return j;
        }
        function show(g) { $('st-result').replaceChildren(card(g)); if (g.pending) schedule(g.id); else { clearTimeout(poll); $('st-go').disabled = false; } }
        function schedule(id) { clearTimeout(poll); poll = setTimeout(async () => { try { show((await api(root.dataset.base + '/' + id, 'GET')).data); } catch (e) { $('st-go').disabled = false; } }, 3000); }
        async function regen(id) { try { show((await api(root.dataset.base + '/' + id + '/regenerate', 'POST')).data); } catch (e) { err(e); } }
        async function del(id) { if (!confirm(L.confirm_delete)) return; await api(root.dataset.base + '/' + id, 'DELETE'); $('st-result').replaceChildren(); loadHistory(); }
        function err(e) { const b = $('st-err'); b.textContent = (e.errors ? Object.values(e.errors).flat().join(' ') : e.message); b.classList.remove('hidden'); $('st-go').disabled = false; }

        form.addEventListener('submit', async ev => {
            ev.preventDefault(); $('st-err').classList.add('hidden'); $('st-go').disabled = true;
            const fd = new FormData(form), body = {};
            for (const [k, v] of fd.entries()) { if (k.endsWith('[]')) (body[k.slice(0, -2)] ||= []).push(v); else body[k] = v; }
            try { show((await api(root.dataset.store, 'POST', body)).data); } catch (e) { err(e); }
        });

        async function loadHistory() {
            const j = await api(root.dataset.history, 'GET'), h = $('hist'); h.replaceChildren();
            if (!j.data.length) h.append(el('p', 'text-sm text-slate-500', L.history_empty));
            j.data.forEach(g => { if (g.pending) schedule(g.id); h.append(card(g)); });
        }
        document.querySelectorAll('.st-tab').forEach(b => b.addEventListener('click', () => {
            const t = b.dataset.tab;
            document.querySelectorAll('.st-tab').forEach(x => { const on = x === b; x.classList.toggle('border-brand-600', on); x.classList.toggle('text-brand-700', on); x.classList.toggle('border-transparent', !on); x.classList.toggle('text-slate-500', !on); });
            $('pane-history').classList.toggle('hidden', t !== 'history'); $('pane-form').classList.toggle('hidden', t === 'history');
            if (t === 'history') loadHistory(); else { form.elements.mode.value = t; form.querySelectorAll('[data-only=marketing]').forEach(e => e.classList.toggle('hidden', t !== 'marketing')); }
        }));
        $('st-platform').addEventListener('change', renderPreview); renderPreview();
    })();
    </script>
</x-layouts.admin>
