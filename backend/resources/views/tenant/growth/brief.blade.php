<x-layouts.admin guard="tenant" :title="__('growth_hub.brief.title')">
    @php $t = __('growth_hub.brief'); @endphp
    <x-card>
        <form id="brief-form" class="flex flex-wrap items-end gap-3">
            <label class="text-sm">{{ $t['title'] }}
                <select name="type" class="mt-1 block rounded border border-slate-300 px-2 py-1.5"><option value="daily">{{ $t['daily'] }}</option><option value="weekly">{{ $t['weekly'] }}</option></select></label>
            <label class="text-sm">{{ $t['date'] }}<input type="date" name="date" class="mt-1 block rounded border border-slate-300 px-2 py-1.5"></label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="ai" value="1"> {{ $t['ai'] }}</label>
            <x-button type="submit">{{ $t['load'] }}</x-button>
        </form>
    </x-card>
    <div id="brief-out" class="mt-4 space-y-4"></div>
    <script>
    (function () {
        const T = @json($t), url = @json(route('tenant.growth.brief.data')), decideUrl = @json(route('tenant.growth.brief.decision')), canManage = @json(auth('tenant')->user()?->can('growth.manage') ?? false);
        const out = document.getElementById('brief-out');
        const el = (tag, text, cls) => { const e = document.createElement(tag); if (text !== undefined && text !== null) e.textContent = text; if (cls) e.className = cls; return e; };
        const metric = (m) => { if (!m) return '-'; if (m.value === null) return T.insufficient + (m.missing && m.missing.length ? ' (' + T.missing + ': ' + m.missing.join(', ') + ')' : ''); return m.value + ' [' + T[m.confidence] + ']'; };
        const card = (title) => { const c = el('div', null, 'rounded-lg border border-slate-200 bg-white p-4'); c.appendChild(el('h3', title, 'mb-2 font-semibold')); out.appendChild(c); return c; };
        const line = (c, text) => c.appendChild(el('p', text, 'text-sm text-slate-700'));
        function render(b, names) {
            out.replaceChildren();
            const s = card(T.sales);
            line(s, T.orders + ': ' + metric(b.sales.orders_completed)); line(s, T.revenue + ': ' + metric(b.sales.revenue)); line(s, T.aov + ': ' + metric(b.sales.aov));
            const m = card(T.margin); line(m, T.margin + ': ' + metric(b.contribution_margin.value)); line(m, T.margin_pct + ': ' + metric(b.contribution_margin.percent)); line(m, T.margin_note);
            if (b.products_gaining_interest) { const c = card(T.interest); if (!b.products_gaining_interest.length) line(c, T.none); b.products_gaining_interest.forEach(p => line(c, (names[p.product_id] || '#' + p.product_id) + ': ' + p.units + ' (avg ' + p.avg_prev_7d + ')')); }
            if (b.inventory_issues) { const c = card(T.inventory); if (!b.inventory_issues.length) line(c, T.none); b.inventory_issues.forEach(p => line(c, (names[p.product_id] || '#' + p.product_id) + ': ' + p.on_hand + ' ' + T.on_hand + ', ' + (p.days_of_cover ?? '-') + ' ' + T.cover)); }
            if (b.conversations_and_complaints) line(card(T.conversations.split(':')[0]), T.conversations);
            if (b.channel_profitability) { const c = card(T.channels); Object.entries(b.channel_profitability).forEach(([k, v]) => line(c, k + ': ' + T.orders + ' ' + metric(v.orders_completed) + ' | ' + T.margin + ' ' + metric(v.contribution_margin))); }
            if (b.bundle_performance) { const c = card(T.bundles); if (!b.bundle_performance.length) line(c, T.none); b.bundle_performance.forEach(x => line(c, x.name + ': ' + x.units + ' / ' + x.revenue + ' | ' + metric(x.line_margin))); }
            if (b.why_customers_do_not_buy) { const w = b.why_customers_do_not_buy, c = card(T.reasons); line(c, T.cancel + ': ' + metric(w.cancel_rate)); line(c, T['return'] + ': ' + metric(w.return_rate)); w.return_reasons.forEach(r => line(c, r.reason + ': ' + r.count)); }
            if (b.reorder_candidates) { const c = card(T.reorder); if (!b.reorder_candidates.length) line(c, T.none); b.reorder_candidates.forEach(p => line(c, (names[p.product_id] || '#' + p.product_id) + ': ' + (p.days_of_cover ?? '-') + ' ' + T.cover)); }
            if (b.experiment_results) { const c = card(T.experiments); if (!b.experiment_results.length) line(c, T.none); b.experiment_results.forEach(x => line(c, x.name + ' [' + x.status + '] ' + (x.outcome || ''))); }
            if (b.data_quality && b.data_quality.length) { const c = card(T.data_quality); b.data_quality.forEach(q => line(c, (T.dq[q.key] || q.key) + ': ' + q.count + (q.ids.length ? ' (#' + q.ids.join(', #') + ')' : ''))); }
            const r = card(T.recs); if (!b.recommendations.length) line(r, T.none);
            b.recommendations.forEach(x => { const d = el('div', null, 'mb-2 rounded bg-slate-50 p-2'); d.appendChild(el('p', (T.kind[x.kind] || x.kind) + (x.params.product_id ? ': ' + (names[x.params.product_id] || '#' + x.params.product_id) : ''), 'text-sm font-medium'));
                d.appendChild(el('p', T.impact + ' ' + x.impact + '/5, ' + T.effort + ' ' + x.effort + '/5', 'text-xs text-slate-500'));
                x.evidence.forEach(e => d.appendChild(el('p', T.evidence + ': ' + e.source + '.' + e.metric + ' = ' + e.value + ' (' + e.period + ')', 'text-xs text-slate-500')));
                if (x.decision) d.appendChild(el('p', T.decision + ': ' + (T.decision_status[x.decision.status] || x.decision.status) + (x.decision.experiment_id ? ' #' + x.decision.experiment_id : ''), 'text-xs font-semibold text-emerald-700'));
                if (canManage) { const bar = el('div', null, 'mt-1 flex flex-wrap gap-2'); [['accepted', T.accept, false], ['accepted', T.accept_experiment, true], ['done', T.mark_done, false], ['dismissed', T.dismiss, false]].forEach(([st, label, exp]) => { const bt = el('button', label, 'btn-sm btn-secondary'); bt.type = 'button'; bt.addEventListener('click', () => decide(x, st, exp)); bar.appendChild(bt); }); d.appendChild(bar); }
                r.appendChild(d); });
            if (b.ai_summary) { const c = card(T.ai_summary); c.appendChild(el('p', b.ai_summary, 'whitespace-pre-line text-sm')); }
        }
        async function decide(x, status, exp) {
            let note = null, after = null;
            if (status !== 'dismissed') { note = prompt(T.note_prompt) || null; }
            if (status === 'done') { after = prompt(T.after_prompt); }
            const res = await fetch(decideUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                body: JSON.stringify({ recommendation_key: x.key, kind: x.kind, status, note, metric_after: after === '' ? null : after, create_experiment: exp, evidence: x.evidence }) });
            const j = await res.json().catch(() => ({}));
            if (!res.ok) { toastr.error(j.message || 'Error'); return; }
            toastr.success(j.message); document.getElementById('brief-form').requestSubmit();
        }
        document.getElementById('brief-form').addEventListener('submit', async (e) => {
            e.preventDefault(); out.replaceChildren(el('p', T.loading));
            const q = new URLSearchParams(new FormData(e.target)); for (const [k, v] of [...q]) if (v === '') q.delete(k);
            try { const res = await fetch(url + '?' + q, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }); if (!res.ok) throw 0; const j = await res.json(); render(j.data.brief, j.data.names); }
            catch (_) { out.replaceChildren(el('p', T.error, 'text-red-600')); }
        });
        document.getElementById('brief-form').requestSubmit();
    })();
    </script>
</x-layouts.admin>
