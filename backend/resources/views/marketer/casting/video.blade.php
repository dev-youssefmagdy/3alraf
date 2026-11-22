@php
    $t = fn ($k, $r = []) => __('casting.'.$k, $r);
    $loc = app()->getLocale();
    $tone = ['succeeded' => 'success', 'failed' => 'danger', 'queued' => 'info', 'running' => 'info'];
    $sizes = collect($status['sizes'])->mapWithKeys(fn ($wh, $k) => [$k => $k.' — '.$wh[0].'×'.$wh[1]])->all();
    $durations = collect($status['durations'])->mapWithKeys(fn ($s, $n) => [$n => $t('video.seconds', ['s' => $s])])->all();
    $label = fn ($p) => $p->labelFor($loc);
@endphp
<x-layouts.marketer :title="$t('video.title')">
    <x-slot:header><x-ui.page-header :title="$t('video.title')" :breadcrumbs="[[$t('title'), route('marketer.casting.index')]]" /></x-slot:header>
    <div class="space-y-4" data-video-studio>
        <x-ui.alert tone="warning">{{ $t('internal_notice') }}</x-ui.alert>
        @if ($status['ready'])
            <x-ui.alert tone="success" data-video-status="ready">{{ $t('video.status_ready', ['label' => $status['label'], 'fps' => $status['fps']]) }} {{ $t('video.one_at_a_time') }}</x-ui.alert>
        @else
            <x-ui.alert tone="danger" data-video-status="off">{{ $t('video.status_off', ['why' => implode('; ', $status['missing'])]) }}</x-ui.alert>
        @endif

        <x-ui.card :title="$t('video.generate')">
            <form id="video-form" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3" novalidate>
                <x-ui.select name="mode" :label="$t('video.mode')" :options="collect(['model_ad', 'product_showcase'])->mapWithKeys(fn ($m) => [$m => $t('video.modes.'.$m)])->all()" value="{{ $pick ? 'model_ad' : 'product_showcase' }}" />
                <div data-for="model_ad" class="lg:col-span-2">
                    <x-ui.select name="generation_id" :label="$t('video.generation')" :options="$images->mapWithKeys(fn ($g) => [$g->id => '#'.$g->id.' · '.$g->virtualModel?->name.' · '.$g->productLabel()])->all()" :value="$pick" :placeholder="$t('video.none')" />
                </div>
                <div data-for="product_showcase" class="grid gap-3 sm:grid-cols-2 lg:col-span-2">
                    <x-ui.select name="product_id" :label="$t('video.product')" :options="$products->mapWithKeys(fn ($p) => [$p->id => $p->name.' — '.$p->sku])->all()" searchable :placeholder="$t('video.none')" />
                    @include('marketer.casting._live_select')
                </div>
                <x-ui.select name="motion" :label="$t('video.motion')" :options="$motions->mapWithKeys(fn ($p) => [$p->key => $label($p)])->all()" :placeholder="$t('video.none')" />
                <x-ui.select name="pmove" :label="$t('video.pmove')" :options="$pmoves->mapWithKeys(fn ($p) => [$p->key => $label($p)])->all()" :placeholder="$t('video.none')" />
                <x-ui.select name="scene" :label="$t('video.scene')" :options="$scenes->mapWithKeys(fn ($p) => [$p->key => $label($p)])->all()" :placeholder="$t('video.none')" />
                <x-ui.select name="length" :label="$t('video.duration')" :options="$durations" />
                <x-ui.select name="format" :label="$t('video.size')" :options="$sizes" />
                <x-ui.input name="seed" type="number" min="0" :label="$t('video.seed')" />
                <x-ui.input name="note" class="lg:col-span-3" :label="$t('video.note')" maxlength="200" />
                <div class="sm:col-span-2 lg:col-span-3">
                    <x-ui.textarea name="prompt" :label="$t('video.prompt')" rows="4" maxlength="900" :help="$t('video.prompt_hint')" />
                    <button type="button" class="btn btn-secondary btn-sm mt-1" id="rebuild-prompt">{{ $t('video.rebuild') }}</button>
                </div>
                <div class="sm:col-span-2 lg:col-span-3"><button class="btn btn-primary btn-md" type="submit" data-go @disabled(! $status['ready'])>{{ $t('video.generate') }}</button> <span class="text-sm" data-msg></span></div>
            </form>
        </x-ui.card>

        <x-ui.card :title="$t('video.recent')">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4" id="video-grid">
                @foreach ($history as $h)
                    <div class="overflow-hidden rounded-xl border border-panel-border" data-gen="{{ $h->id }}" data-status="{{ $h->status }}">
                        <div class="aspect-[9/16] max-h-[26rem] w-full bg-panel-surface-sunken" data-media>
                            @if ($h->status === 'succeeded')
                                <video controls muted playsinline preload="metadata" class="size-full object-contain" src="{{ route('marketer.casting.generations.image', $h->id) }}" poster="{{ route('marketer.casting.generations.source', $h->id) }}"></video>
                            @else
                                <div class="relative grid size-full place-items-center p-3 text-center text-xs text-panel-text-muted">
                                    <img alt="" class="absolute inset-0 size-full object-contain opacity-30" src="{{ route('marketer.casting.generations.source', $h->id) }}" loading="lazy">
                                    <div class="relative space-y-2"><div data-ph>{{ $h->status === 'failed' ? $h->error : $t('studio.queued') }}</div>
                                        @if (in_array($h->status, ['queued', 'running'], true))<div class="mx-auto h-1.5 w-32 overflow-hidden rounded-full bg-panel-border"><div class="h-full w-1/3 animate-pulse rounded-full bg-panel-accent"></div></div><div data-elapsed></div>@endif
                                    </div>
                                </div>
                            @endif
                        </div>
                        <div class="space-y-1 p-2 text-xs">
                            <div class="flex items-center justify-between gap-1"><span class="truncate font-semibold">{{ $h->productLabel() ?: $h->virtualModel?->name }} · {{ ($h->params['length'] ?? 0) && ($h->params['fps'] ?? 0) ? round($h->params['length'] / $h->params['fps'], 1).'s' : '' }}</span>
                                <x-ui.badge :tone="$tone[$h->status] ?? 'neutral'" size="sm" data-badge>{{ $t('status.'.$h->status) }}</x-ui.badge></div>
                            <p class="line-clamp-2 text-panel-text-muted" title="{{ $h->prompt }}">{{ $h->prompt }}</p>
                            <div class="flex flex-wrap gap-1">
                                @if ($h->status === 'failed')<button class="btn btn-secondary btn-sm" data-act="retry">{{ $t('video.retry') }}</button>@endif
                                @if ($h->status === 'succeeded' && $canApprove && $h->product_id && ! $h->social_media_asset_id)<button class="btn btn-primary btn-sm" data-act="library">{{ $t('video.approve') }}</button>@endif
                                @if ($canApprove && $h->live_product_id && ! isset($h->params['live_asset_id']) && $livePush)<button class="btn btn-primary btn-sm" data-act="push-live">{{ $t('live.push') }}</button>@endif
                                @if (isset($h->params['live_asset_id']))<span class="text-panel-success-fg">{{ $t('live.pushed') }}</span>@endif
                                @if ($h->social_media_asset_id)<span class="text-panel-success-fg">{{ $t('studio.sent') }}</span>@endif
                            </div>
                            <p class="text-panel-danger-fg" data-error></p>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-ui.card>
    </div>
    @include('marketer.casting._js')
    <script>
    (() => {
        const base = @js(url('marketer/casting/generations')), form = document.getElementById('video-form'), msg = form.querySelector('[data-msg]'), ta = form.querySelector('[name=prompt]');
        let dirty = false;
        const modeSel = form.querySelector('[name=mode]');
        const syncMode = () => form.querySelectorAll('[data-for]').forEach(e => e.classList.toggle('hidden', e.dataset.for !== modeSel.value));
        async function rebuild(force) {
            if (dirty && !force) return;
            try { const j = await casting.req(@js(route('marketer.casting.video.prompt')), Object.fromEntries(new FormData(form))); ta.value = j.prompt; if (force) dirty = false; } catch (e) {}
        }
        ta.addEventListener('input', () => dirty = true);
        form.addEventListener('change', (e) => { if (e.target === modeSel) syncMode(); if (e.target.name !== 'prompt' && e.target.name !== 'seed' && e.target.name !== 'length' && e.target.name !== 'format') rebuild(false); });
        document.getElementById('rebuild-prompt').addEventListener('click', () => rebuild(true));
        syncMode(); rebuild(false);
        form.addEventListener('submit', async (e) => {
            e.preventDefault(); msg.textContent = ''; const go = form.querySelector('[data-go]'); go.disabled = true;
            try { await casting.req(@js(route('marketer.casting.video.store')), new FormData(form)); location.reload(); }
            catch (x) { msg.textContent = x.message; msg.className = 'text-sm text-panel-danger-fg'; go.disabled = false; }
        });
        document.getElementById('video-grid').addEventListener('click', async (e) => {
            const b = e.target.closest('[data-act]'); if (!b) return;
            const el = b.closest('[data-gen]'), err = el.querySelector('[data-error]'); err.textContent = ''; b.disabled = true;
            try { await casting.req(`${base}/${el.dataset.gen}/${b.dataset.act}`); location.reload(); } catch (x) { err.textContent = x.message; b.disabled = false; }
        });
        const pending = () => [...document.querySelectorAll('[data-gen]')].filter(e => ['queued', 'running'].includes(e.dataset.status));
        async function poll() {
            let reload = false;
            for (const el of pending()) {
                try {
                    const d = await casting.get(`${base}/${el.dataset.gen}/status`);
                    if (d.status !== el.dataset.status && d.status !== 'queued') reload = true;
                    const t = el.querySelector('[data-elapsed]'); if (t && d.elapsed != null) t.textContent = @js($t('video.elapsed', ['s' => ':s'])).replace(':s', d.elapsed);
                } catch (e) {}
            }
            if (reload) return location.reload();
            if (pending().length) setTimeout(poll, 3000);
        }
        if (pending().length) setTimeout(poll, 2000);
    })();
    </script>
</x-layouts.marketer>
