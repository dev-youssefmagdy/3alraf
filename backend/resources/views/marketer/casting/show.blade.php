@php
    $t = fn ($k, $r = []) => __('casting.'.$k, $r);
    $b = $s->product_brief ?? [];
    $tone = ['ready' => 'success', 'approved' => 'accent', 'failed' => 'danger', 'rejected' => 'neutral', 'queued' => 'info', 'generating' => 'info', 'planned' => 'neutral'];
    $img = fn ($c) => route('marketer.casting.candidates.image', $c->id).'?v='.$c->updated_at->timestamp;
@endphp
<x-layouts.marketer :title="$t('gallery.title').' #'.$s->id">
    <x-slot:header>
        <x-ui.page-header :title="$s->productLabel()" :breadcrumbs="[[$t('sessions.title'), route('marketer.casting.index')]]" :subtitle="$t('gallery.title').' #'.$s->id">
            <button type="button" class="btn btn-secondary btn-md" id="compare-toggle">{{ $t('gallery.compare') }}</button>
        </x-ui.page-header>
    </x-slot:header>
    <div class="space-y-4" data-session="{{ $s->id }}" data-status-url="{{ route('marketer.casting.status', $s->id) }}">
        <x-ui.alert tone="warning">{{ $t('internal_notice') }}</x-ui.alert>
        @if (($caps['identity'] ?? 'prompt_seed') === 'prompt_seed')<x-ui.alert tone="info" data-identity-note>{{ $t('engine.identity_prompt_seed') }}</x-ui.alert>@endif

        <x-ui.card :title="$t('gallery.product_brief')">
            <div class="flex flex-wrap gap-2 text-sm">
                <x-ui.badge>{{ $t('gallery.segment') }}: {{ $b['segment'] ?? '—' }}</x-ui.badge>
                <x-ui.badge>{{ $t('gallery.audience') }}: {{ str_replace('_', ' ', $b['audience']['group'] ?? '—') }}</x-ui.badge>
                <x-ui.badge>{{ $t('gallery.tone') }}: {{ $b['audience']['tone'] ?? '—' }}</x-ui.badge>
                <x-ui.badge>{{ $b['price_tier'] ?? '' }}</x-ui.badge>
            </div>
        </x-ui.card>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" id="cand-grid">
            @foreach ($s->candidates as $c)
                <article class="flex flex-col overflow-hidden rounded-2xl border border-panel-border bg-panel-surface shadow-panel-xs" data-cand="{{ $c->id }}" data-status="{{ $c->status }}">
                    <div class="relative aspect-[3/4] bg-panel-surface-sunken" data-media>
                        @if ($c->getRawOriginal('portrait_path'))
                            <img src="{{ $img($c) }}" alt="{{ $c->name }}" loading="lazy" class="size-full object-cover" data-img>
                        @else
                            <div class="grid size-full place-items-center p-4 text-center text-sm text-panel-text-muted" data-placeholder>{{ $c->status === 'failed' ? $c->error : $t('gallery.waiting') }}</div>
                        @endif
                        <label class="absolute start-2 top-2 hidden rounded-lg bg-panel-surface/90 px-2 py-1 text-xs" data-cmp><input type="checkbox" class="me-1" data-cmp-box>{{ $t('gallery.compare') }}</label>
                    </div>
                    <div class="flex flex-1 flex-col gap-2 p-4">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="text-lg font-bold text-panel-text" data-name>{{ $c->name }}</h3>
                            <x-ui.badge :tone="$tone[$c->status] ?? 'neutral'" dot data-badge>{{ $t('status.'.$c->status) }}</x-ui.badge>
                        </div>
                        <p class="text-sm text-panel-text-muted">{{ $c->gender === 'male' ? $t('fields.male') : $t('fields.female') }} · {{ $c->age_range }} · {{ $c->ad_role }}</p>
                        <dl class="space-y-1 text-sm" data-details>
                            <div><dt class="inline font-semibold">{{ $t('fields.appearance') }}:</dt> <dd class="inline">{{ $c->appearance }}</dd></div>
                            <div><dt class="inline font-semibold">{{ $t('fields.hairstyle') }}:</dt> <dd class="inline">{{ $c->hairstyle }}</dd></div>
                            <div><dt class="inline font-semibold">{{ $t('fields.clothing') }}:</dt> <dd class="inline">{{ $c->clothing }}</dd></div>
                            <div><dt class="inline font-semibold">{{ $t('fields.personality') }}:</dt> <dd class="inline">{{ $c->personality }}</dd></div>
                            <div><dt class="inline font-semibold">{{ $t('gallery.poses') }}:</dt> <dd class="inline">{{ collect($c->suggested_poses)->map(fn ($k) => str_replace('_', ' ', $k))->implode('، ') }}</dd></div>
                            <div><dt class="inline font-semibold">{{ $t('gallery.locations') }}:</dt> <dd class="inline">{{ collect($c->suggested_locations)->implode('، ') }}</dd></div>
                            <div class="text-panel-text-muted"><dt class="inline font-semibold">{{ $t('gallery.why') }}:</dt> <dd class="inline">{{ $c->rationale }}</dd></div>
                        </dl>
                        <p class="text-xs text-panel-danger-fg" data-error>{{ $c->status === 'failed' ? $c->error : '' }}</p>
                        <div class="mt-auto flex flex-wrap gap-2 pt-2" data-actions>
                            @if ($canEdit && ! in_array($c->status, ['approved'], true))
                                <button type="button" class="btn btn-secondary btn-sm" data-act="regenerate" @disabled($c->isBusy())>{{ $t('gallery.regenerate') }}</button>
                                <button type="button" class="btn btn-secondary btn-sm" data-act="edit">{{ $t('gallery.edit') }}</button>
                                @if ($c->status !== 'rejected')<button type="button" class="btn btn-secondary btn-sm" data-act="reject">{{ $t('gallery.reject') }}</button>@endif
                            @endif
                            @if ($canApprove && $c->status === 'ready')<button type="button" class="btn btn-primary btn-sm" data-act="approve">{{ $t('gallery.approve') }}</button>@endif
                            @if ($c->virtual_model_id)<a class="btn btn-primary btn-sm" href="{{ route('marketer.casting.models.show', $c->virtual_model_id) }}">{{ $t('models.title') }}</a>@endif
                        </div>
                        <form class="hidden space-y-2 border-t border-panel-border pt-3" data-edit-form>
                            @foreach (['name' => 'name', 'age_range' => 'age_range', 'appearance' => 'appearance', 'hairstyle' => 'hairstyle', 'clothing' => 'clothing', 'personality' => 'personality', 'ad_role' => 'ad_role'] as $f => $k)
                                <label class="block text-xs font-semibold">{{ $t('fields.'.$k) }}<input class="form-control mt-1" name="{{ $f }}" value="{{ $c->{$f} }}" maxlength="600"></label>
                            @endforeach
                            <div class="flex gap-2"><button class="btn btn-secondary btn-sm" type="submit" data-regen="0">{{ $t('gallery.save') }}</button><button class="btn btn-primary btn-sm" type="submit" data-regen="1">{{ $t('gallery.save_regen') }}</button></div>
                        </form>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
    @include('marketer.casting._js')
    <script>
    (() => {
        const root = document.querySelector('[data-session]'), base = @js(url('marketer/casting/candidates'));
        const labels = @js(collect(['planned', 'queued', 'generating', 'ready', 'failed', 'approved', 'rejected'])->mapWithKeys(fn ($k) => [$k => $t('status.'.$k)]));
        const tones = {ready: 'success', approved: 'accent', failed: 'danger', rejected: 'neutral', queued: 'info', generating: 'info', planned: 'neutral'};
        const card = (id) => root.querySelector(`[data-cand="${id}"]`);
        let timer;
        async function poll() {
            try {
                const d = await casting.get(root.dataset.statusUrl);
                let busy = false;
                d.candidates.forEach(c => {
                    const el = card(c.id); if (!el) return;
                    const changed = el.dataset.status !== c.status;
                    if (['queued', 'generating'].includes(c.status)) busy = true;
                    if (changed) {
                        el.dataset.status = c.status;
                        el.querySelector('[data-badge]').lastChild.textContent = labels[c.status] ?? c.status;
                        if (['ready', 'failed', 'approved'].includes(c.status)) location.reload();
                    }
                    el.querySelector('[data-error]').textContent = c.error || '';
                    const ph = el.querySelector('[data-placeholder]');
                    if (ph && c.status === 'generating') ph.textContent = @js($t('gallery.generating')) + (c.attempts > 1 ? ' ' + @js($t('gallery.retry_note', ['n' => ':n'])).replace(':n', c.attempts) : '');
                });
                if (busy) timer = setTimeout(poll, 4000);
            } catch (e) { timer = setTimeout(poll, 10000); }
        }
        if ([...root.querySelectorAll('[data-cand]')].some(e => ['planned', 'queued', 'generating'].includes(e.dataset.status))) timer = setTimeout(poll, 2500);

        root.addEventListener('click', async (e) => {
            const btn = e.target.closest('[data-act]'); if (!btn) return;
            const el = btn.closest('[data-cand]'), id = el.dataset.cand, act = btn.dataset.act, err = el.querySelector('[data-error]');
            err.textContent = '';
            try {
                if (act === 'edit') { el.querySelector('[data-edit-form]').classList.toggle('hidden'); return; }
                if (act === 'reject' && !confirm(@js($t('gallery.confirm_reject')))) return;
                if (act === 'approve' && !confirm(@js($t('gallery.confirm_approve')))) return;
                btn.disabled = true;
                const j = await casting.req(`${base}/${id}/${act}`);
                if (act === 'approve') { location.href = j.url; } else { location.reload(); }
            } catch (x) { err.textContent = x.message; btn.disabled = false; }
        });
        root.addEventListener('submit', async (e) => {
            const f = e.target.closest('[data-edit-form]'); if (!f) return; e.preventDefault();
            const el = f.closest('[data-cand]'), regen = e.submitter?.dataset.regen === '1';
            const data = Object.fromEntries(new FormData(f)); data.regenerate = regen;
            try { await casting.req(`${base}/${el.dataset.cand}`, data, 'PUT'); location.reload(); }
            catch (x) { el.querySelector('[data-error]').textContent = x.message; }
        });
        // compare: show only the ticked candidates side by side
        const tog = document.getElementById('compare-toggle'); let cmp = false;
        tog.addEventListener('click', () => {
            cmp = !cmp;
            root.querySelectorAll('[data-cmp]').forEach(l => l.classList.toggle('hidden', !cmp));
            if (!cmp) root.querySelectorAll('[data-cand]').forEach(c => c.classList.remove('hidden'));
        });
        root.addEventListener('change', (e) => {
            if (!e.target.matches('[data-cmp-box]')) return;
            const ticked = [...root.querySelectorAll('[data-cmp-box]:checked')];
            root.querySelectorAll('[data-cand]').forEach(c => c.classList.toggle('hidden', ticked.length > 1 && !c.querySelector('[data-cmp-box]').checked));
        });
    })();
    </script>
</x-layouts.marketer>
