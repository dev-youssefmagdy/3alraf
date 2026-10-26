<x-layouts.admin guard="tenant" :title="__('ai.menu.assistant')">
    @php($pct = $quota['quota'] ? min(100, round($quota['used'] / $quota['quota'] * 100)) : null)
    <div id="ai-chat" class="flex h-[calc(100vh-9rem)] min-h-[28rem] gap-4"
         data-stream="{{ route('tenant.ai.chat.stream') }}" data-conv="{{ url('admin/ai/chat/conversations') }}">
        <aside class="hidden w-64 shrink-0 flex-col rounded-xl border border-slate-200 bg-white md:flex">
            <div class="flex items-center justify-between border-b border-slate-200 p-3">
                <span class="text-sm font-semibold text-slate-700">{{ __('ai.chat.conversations') }}</span>
                <button type="button" id="ai-new" class="rounded-lg bg-brand-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-brand-700">+ {{ __('ai.chat.new_chat') }}</button>
            </div>
            <ul id="ai-list" class="flex-1 space-y-1 overflow-y-auto p-2 text-sm"></ul>
        </aside>

        <section class="flex min-w-0 flex-1 flex-col rounded-xl border border-slate-200 bg-white">
            <header class="flex flex-wrap items-center gap-3 border-b border-slate-200 p-3 text-sm">
                <label class="flex items-center gap-2 text-slate-600">{{ __('ai.chat.model') }}
                    <select id="ai-model" data-search class="rounded-lg border border-slate-300 bg-white px-2 py-1 text-sm">
                        @foreach ($models as $m)<option value="{{ $m->id }}" @selected($current?->id === $m->id)>{{ $m->label }}</option>@endforeach
                    </select>
                </label>
                <button type="button" id="ai-new-m" class="rounded-lg border border-slate-300 px-2 py-1 text-xs md:hidden">+ {{ __('ai.chat.new_chat') }}</button>
                <select id="ai-list-m" class="rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs md:hidden"></select>
                <div class="ms-auto flex items-center gap-2 text-xs text-slate-500" title="{{ __('ai.chat.quota') }}">
                    <span id="ai-quota">{{ number_format($quota['used']) }} / {{ $quota['quota'] === null ? __('ai.unlimited') : number_format($quota['quota']) }}</span>
                    <span class="h-1.5 w-20 rounded bg-slate-200"><span id="ai-quota-bar" class="block h-1.5 rounded bg-brand-600" style="width: {{ $pct ?? 0 }}%"></span></span>
                </div>
                <span id="ai-actions" class="hidden gap-2">
                    <button type="button" id="ai-rename" class="text-xs text-slate-500 hover:text-brand-700">{{ __('ai.chat.rename') }}</button>
                    <button type="button" id="ai-delete" class="text-xs text-red-500 hover:text-red-700">{{ __('ai.chat.delete') }}</button>
                </span>
            </header>

            <div id="ai-msgs" class="flex-1 space-y-4 overflow-y-auto p-4" aria-live="polite"></div>

            <div id="ai-suggest" class="flex flex-wrap gap-2 px-4 pb-2">
                @foreach (__('ai.chat.suggestions') as $s)
                    <button type="button" data-suggest class="rounded-full border border-slate-300 px-3 py-1 text-xs text-slate-600 hover:border-brand-500 hover:text-brand-700">{{ $s }}</button>
                @endforeach
            </div>

            <form id="ai-form" class="flex items-end gap-2 border-t border-slate-200 p-3">
                <textarea id="ai-input" rows="2" maxlength="4000" placeholder="{{ __('ai.chat.placeholder') }}" @disabled($models->isEmpty())
                          class="min-h-[2.75rem] flex-1 resize-none rounded-lg border border-slate-300 p-2 text-sm focus:border-brand-500 focus:outline-none"></textarea>
                <button type="submit" id="ai-send" @disabled($models->isEmpty()) class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50">{{ __('ai.chat.send') }}</button>
                <button type="button" id="ai-stop" class="hidden rounded-lg bg-slate-700 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">{{ __('ai.chat.stop') }}</button>
            </form>
            @if ($models->isEmpty())<p class="px-4 pb-3 text-xs text-red-600">{{ __('ai.chat.no_models') }}</p>@endif
        </section>
    </div>

    <script>
    (function () {
        const root = document.getElementById('ai-chat');
        const T = @json(__('ai.chat'));
        const LIST = @json($conversations);
        const csrf = document.querySelector('meta[name=csrf-token]').content;
        const $ = (id) => document.getElementById(id);
        const msgs = $('ai-msgs'), input = $('ai-input'), listEl = $('ai-list'), listM = $('ai-list-m');
        let convId = null, ctrl = null, convs = LIST.slice();

        // ---- sanitised markdown: escape everything first, then add a small whitelist of tags ----
        const esc = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        function inline(s) {
            return s.replace(/`([^`]+)`/g, '<code class="rounded bg-slate-100 px-1">$1</code>')
                .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>').replace(/(^|[\s(])\*([^*\s][^*]*)\*/g, '$1<em>$2</em>')
                .replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer nofollow" class="text-brand-700 underline">$1</a>');
        }
        function md(src) {
            const lines = esc(src).split('\n'); let out = '', list = null, i = 0, code = false, buf = [];
            const close = () => { if (list) { out += '</' + list + '>'; list = null; } };
            for (; i < lines.length; i++) {
                const l = lines[i];
                if (l.trim().startsWith('```')) { if (code) { out += '<pre class="my-2 overflow-x-auto rounded-lg bg-slate-900 p-3 text-xs text-slate-100" dir="ltr"><code>' + buf.join('\n') + '</code></pre>'; buf = []; code = false; } else { close(); code = true; } continue; }
                if (code) { buf.push(l); continue; }
                if (/^\s*\|.*\|\s*$/.test(l) && /^\s*\|[\s:|-]+\|\s*$/.test(lines[i + 1] || '')) {
                    close(); const cells = (r) => r.trim().replace(/^\||\|$/g, '').split('|').map((c) => inline(c.trim()));
                    let t = '<div class="my-2 overflow-x-auto"><table class="min-w-full text-xs"><thead><tr>' + cells(l).map((c) => '<th class="border-b px-2 py-1 text-start">' + c + '</th>').join('') + '</tr></thead><tbody>';
                    for (i += 2; i < lines.length && /^\s*\|.*\|\s*$/.test(lines[i]); i++) t += '<tr>' + cells(lines[i]).map((c) => '<td class="border-b border-slate-100 px-2 py-1">' + c + '</td>').join('') + '</tr>';
                    i--; out += t + '</tbody></table></div>'; continue;
                }
                let m;
                if ((m = l.match(/^(#{1,4})\s+(.*)$/))) { close(); out += '<p class="mt-2 font-semibold">' + inline(m[2]) + '</p>'; }
                else if ((m = l.match(/^\s*[-*]\s+(.*)$/))) { if (list !== 'ul') { close(); out += '<ul class="ms-5 list-disc">'; list = 'ul'; } out += '<li>' + inline(m[1]) + '</li>'; }
                else if ((m = l.match(/^\s*\d+[.)]\s+(.*)$/))) { if (list !== 'ol') { close(); out += '<ol class="ms-5 list-decimal">'; list = 'ol'; } out += '<li>' + inline(m[1]) + '</li>'; }
                else if (l.trim() === '') { close(); }
                else { close(); out += '<p>' + inline(l) + '</p>'; }
            }
            if (code) out += '<pre class="my-2 overflow-x-auto rounded-lg bg-slate-900 p-3 text-xs text-slate-100" dir="ltr"><code>' + buf.join('\n') + '</code></pre>';
            close(); return out;
        }

        // ---- UI helpers ----
        const el = (tag, cls, text) => { const e = document.createElement(tag); if (cls) e.className = cls; if (text != null) e.textContent = text; return e; };
        const scroll = () => { msgs.scrollTop = msgs.scrollHeight; };
        function bubble(role) {
            const wrap = el('div', 'flex ' + (role === 'user' ? 'justify-end' : 'justify-start'));
            const b = el('div', 'max-w-[85%] rounded-2xl px-4 py-2 text-sm leading-relaxed ' + (role === 'user' ? 'bg-brand-600 text-white whitespace-pre-wrap' : 'bg-slate-100 text-slate-800'));
            const chips = el('div', 'mb-1 flex flex-wrap gap-1'); const body = el('div'); b.append(chips, body); wrap.append(b); msgs.append(wrap);
            return { wrap, b, chips, body, text: '' };
        }
        function chip(h, name) { h.chips.append(el('span', 'rounded-full bg-white px-2 py-0.5 text-[11px] text-slate-500 ring-1 ring-slate-200', '⚙ ' + ((T.tools || {})[name] || name))); }
        function finish(h) {
            h.body.innerHTML = md(h.text);
            const btn = el('button', 'mt-1 block text-[11px] text-slate-400 hover:text-brand-700', T.copy); btn.type = 'button';
            btn.onclick = () => navigator.clipboard.writeText(h.text).then(() => { btn.textContent = T.copied; setTimeout(() => (btn.textContent = T.copy), 1500); });
            h.b.append(btn);
        }
        function addMsg(m) {
            const h = bubble(m.role);
            if (m.role === 'user') h.body.textContent = m.content;
            else { (m.tools || []).forEach((n) => chip(h, n)); if (m.status === 'error') { h.b.classList.add('text-red-700'); h.body.textContent = m.content; } else { h.text = m.content || ''; finish(h); } }
            return h;
        }
        function renderList() {
            [listEl, listM].forEach((x) => (x.innerHTML = ''));
            convs.forEach((c) => {
                const b = el('button', 'block w-full truncate rounded-lg px-2 py-1.5 text-start ' + (c.id === convId ? 'bg-brand-50 font-medium text-brand-700' : 'text-slate-600 hover:bg-slate-50'), c.title || T.new_chat);
                b.type = 'button'; b.onclick = () => open(c.id); const li = el('li'); li.append(b); listEl.append(li);
                const o = el('option', null, c.title || T.new_chat); o.value = c.id; if (c.id === convId) o.selected = true; listM.append(o);
            });
            const none = el('option', null, T.conversations); none.value = ''; if (!convId) none.selected = true; listM.prepend(none);
            $('ai-actions').classList.toggle('hidden', !convId); $('ai-actions').classList.toggle('flex', !!convId);
        }
        function fresh() { convId = null; msgs.innerHTML = ''; msgs.append(el('p', 'm-auto max-w-md text-center text-sm text-slate-400', T.empty)); $('ai-suggest').classList.remove('hidden'); renderList(); }
        async function open(id) {
            const r = await fetch($('ai-chat').dataset.conv + '/' + id, { headers: { Accept: 'application/json' } });
            if (!r.ok) return; const d = (await r.json()).data; convId = d.id; msgs.innerHTML = '';
            d.messages.forEach(addMsg); if (d.ai_model_id) { const sel = $('ai-model'); sel.tomselect ? sel.tomselect.setValue(d.ai_model_id, true) : sel.value = d.ai_model_id; }
            $('ai-suggest').classList.add('hidden'); renderList(); scroll();
        }
        function setQuota(u) {
            if (!u) return; $('ai-quota').textContent = Number(u.used).toLocaleString() + ' / ' + (u.quota == null ? '∞' : Number(u.quota).toLocaleString());
            $('ai-quota-bar').style.width = (u.quota ? Math.min(100, u.used / u.quota * 100) : 0) + '%';
        }
        function busy(on) { $('ai-send').classList.toggle('hidden', on); $('ai-stop').classList.toggle('hidden', !on); input.disabled = on; if (!on) input.focus(); }

        // ---- send + SSE reader ----
        async function send(text) {
            text = (text || '').trim(); if (!text || ctrl) return;
            if (!convId && !msgs.querySelector('.justify-end')) msgs.innerHTML = '';
            $('ai-suggest').classList.add('hidden'); addMsg({ role: 'user', content: text }); input.value = '';
            const h = bubble('assistant'); h.body.textContent = T.thinking; h.body.classList.add('text-slate-400'); scroll();
            ctrl = new AbortController(); busy(true);
            let ok = false;
            try {
                const r = await fetch($('ai-chat').dataset.stream, { method: 'POST', signal: ctrl.signal, headers: { 'Content-Type': 'application/json', Accept: 'text/event-stream', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ message: text, conversation_id: convId, ai_model_id: $('ai-model').value || null }) });
                if (!r.ok) { const j = await r.json().catch(() => ({})); throw new Error(j.message || T.error); }
                const rd = r.body.getReader(), dec = new TextDecoder(); let buf = '';
                for (;;) {
                    const { value, done } = await rd.read(); if (done) break; buf += dec.decode(value, { stream: true });
                    let idx; while ((idx = buf.indexOf('\n\n')) >= 0) {
                        const raw = buf.slice(0, idx); buf = buf.slice(idx + 2);
                        const ev = (raw.match(/^event: (.*)$/m) || [])[1], dt = (raw.match(/^data: (.*)$/m) || [])[1]; if (!ev || !dt) continue; const p = JSON.parse(dt);
                        if (ev === 'meta') { convId = p.conversation_id; if (!convs.find((c) => c.id === convId)) convs.unshift({ id: convId, title: p.title }); renderList(); }
                        else if (ev === 'tool') chip(h, p.name);
                        else if (ev === 'delta') { if (!h.text) { h.body.textContent = ''; h.body.classList.remove('text-slate-400'); } h.text += p.text; h.body.textContent = h.text; }
                        else if (ev === 'error') { h.body.classList.remove('text-slate-400'); h.b.classList.add('text-red-700'); h.body.textContent = p.message; window.toastr && toastr.error(p.message); }
                        else if (ev === 'done') { ok = true; setQuota(p.usage); }
                        scroll();
                    }
                }
                if (ok) finish(h);
            } catch (e) {
                h.body.classList.remove('text-slate-400');
                if (e.name === 'AbortError') { h.body.textContent = (h.text ? h.text + '\n\n' : '') + T.stopped; } else { h.b.classList.add('text-red-700'); h.body.textContent = e.message || T.error; window.toastr && toastr.error(e.message || T.error); }
            } finally { ctrl = null; busy(false); const c = convs.find((x) => x.id === convId); if (c) { renderList(); } scroll(); }
        }

        $('ai-form').addEventListener('submit', (e) => { e.preventDefault(); send(input.value); });
        input.addEventListener('keydown', (e) => { if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); send(input.value); } });
        $('ai-stop').onclick = () => ctrl && ctrl.abort();
        document.querySelectorAll('[data-suggest]').forEach((b) => (b.onclick = () => send(b.textContent)));
        ['ai-new', 'ai-new-m'].forEach((id) => ($(id).onclick = () => { if (!ctrl) fresh(); }));
        listM.onchange = () => (listM.value ? open(+listM.value) : fresh());
        $('ai-rename').onclick = async () => {
            const cur = convs.find((c) => c.id === convId); const t = prompt(T.rename_prompt, cur && cur.title || ''); if (!t) return;
            const r = await fetch($('ai-chat').dataset.conv + '/' + convId, { method: 'PUT', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify({ title: t }) });
            if (r.ok) { cur.title = t; renderList(); }
        };
        $('ai-delete').onclick = async () => {
            if (!confirm(T.confirm_delete)) return;
            const r = await fetch($('ai-chat').dataset.conv + '/' + convId, { method: 'DELETE', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf } });
            if (r.ok) { convs = convs.filter((c) => c.id !== convId); fresh(); }
        };
        fresh();
    })();
    </script>
</x-layouts.admin>
