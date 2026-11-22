@php
    $t = fn ($k, $r = []) => __('casting.'.$k, $r);
    $loc = app()->getLocale();
    $tone = ['succeeded' => 'success', 'failed' => 'danger', 'queued' => 'info', 'running' => 'info'];
@endphp
<x-layouts.marketer :title="$t('studio.title')">
    <x-slot:header><x-ui.page-header :title="$t('studio.title')" :breadcrumbs="[[$t('title'), route('marketer.casting.index')]]" /></x-slot:header>
    <div class="space-y-4" data-studio>
        <x-ui.alert tone="warning">{{ $t('internal_notice') }}</x-ui.alert>
        @unless (!empty($caps['reference_image']))<x-ui.alert tone="warning" data-fidelity-note>{{ $t('engine.no_reference') }}</x-ui.alert>@endunless
        @unless (!empty($caps['video']))<x-ui.alert tone="info" data-video-note>{{ $t('engine.no_video') }}</x-ui.alert>@endunless

        @if ($models->isEmpty())
            <x-ui.empty-state :title="$t('studio.title')" :description="$t('studio.no_models')" />
        @else
        <x-ui.card :title="$t('studio.generate')">
            <form id="studio-form" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3" novalidate>
                <x-ui.select name="model_id" :label="$t('studio.model')" :options="$models->mapWithKeys(fn ($m) => [$m->id => $m->name.' · '.$m->age_range])->all()" :value="$pick['model']" required />
                <x-ui.select name="product_id" :label="$t('studio.product')" :options="$products->mapWithKeys(fn ($p) => [$p->id => $p->name.' — '.$p->sku])->all()" :value="$pick['product']" searchable :placeholder="$t('live.none')" />
                @include('marketer.casting._live_select')
                <x-ui.select name="format" :label="$t('studio.format')" :options="array_combine($formats, $formats)" value="4:5" />
                <x-ui.input name="concept" class="lg:col-span-3" :label="$t('studio.concept')" maxlength="300" />
                <x-ui.input name="scene" :label="$t('studio.scene')" maxlength="200" />
                <x-ui.input name="outfit" :label="$t('studio.outfit')" maxlength="200" />
                <x-ui.input name="background" :label="$t('studio.background')" maxlength="200" />
                <x-ui.select name="pose" :label="$t('studio.pose')" :options="$poses->mapWithKeys(fn ($p) => [$p->key => $p->labelFor($loc)])->all()" :placeholder="$t('studio.none')" />
                <x-ui.select name="camera" :label="$t('studio.camera')" :options="$cameras->mapWithKeys(fn ($p) => [$p->key => $p->labelFor($loc)])->all()" :placeholder="$t('studio.none')" />
                <x-ui.select name="provider" :label="$t('studio.provider')" :options="array_combine($providers, $providers)" :value="$caps['provider']" />
                <x-ui.input name="campaign" :label="$t('studio.campaign')" maxlength="120" />
                <div class="sm:col-span-2 lg:col-span-3"><button class="btn btn-primary btn-md" type="submit" data-go>{{ $t('studio.generate') }}</button> <span class="text-sm" data-msg></span></div>
            </form>
        </x-ui.card>
        @endif

        <x-ui.card :title="$t('studio.recent')">
            <div class="grid grid-cols-2 gap-3 md:grid-cols-4" id="gen-grid">
                @foreach ($history as $h)
                    <div class="overflow-hidden rounded-xl border border-panel-border" data-gen="{{ $h->id }}" data-status="{{ $h->status }}" data-kind="{{ $h->kind }}">
                        <div class="aspect-[4/5] bg-panel-surface-sunken" data-media>
                            @if ($h->status === 'succeeded')
                                @if ($h->kind === 'ad_video')<video controls muted class="size-full object-cover" src="{{ route('marketer.casting.generations.image', $h->id) }}"></video>
                                @else<img loading="lazy" class="size-full object-cover" alt="" src="{{ route('marketer.casting.generations.image', $h->id) }}">@endif
                            @else<div class="grid size-full place-items-center p-3 text-center text-xs text-panel-text-muted" data-ph>{{ $h->status === 'failed' ? $h->error : $t('studio.queued') }}</div>@endif
                        </div>
                        <div class="space-y-1 p-2 text-xs">
                            <div class="flex items-center justify-between gap-1"><span class="truncate font-semibold">{{ $h->virtualModel?->name }} · {{ $h->productLabel() }}</span><x-ui.badge :tone="$tone[$h->status] ?? 'neutral'" size="sm" data-badge>{{ $t('status.'.$h->status) }}</x-ui.badge></div>
                            @if (($h->params['product_fidelity'] ?? '') === 'not_guaranteed')<p class="text-panel-warning-fg">{{ $t('studio.fidelity_warn') }}</p>@endif
                            @if ($h->status === 'succeeded')
                                <div class="flex flex-wrap gap-1">
                                    @if ($h->kind === 'ad_image' && !empty($caps['video']))<button class="btn btn-secondary btn-sm" data-act="video">{{ $t('studio.video') }}</button>@endif
                                    @if ($canApprove && $h->product_id && ! $h->social_media_asset_id)<button class="btn btn-primary btn-sm" data-act="library">{{ $t('studio.to_library') }}</button>@endif
                                    @if ($canApprove && $h->live_product_id && ! isset($h->params['live_asset_id']) && $livePush)<button class="btn btn-primary btn-sm" data-act="push-live">{{ $t('live.push') }}</button>@endif
                                @if (isset($h->params['live_asset_id']))<span class="text-panel-success-fg">{{ $t('live.pushed') }}</span>@endif
                                @if ($h->social_media_asset_id)<span class="text-panel-success-fg">{{ $t('studio.sent') }}</span>@endif
                                </div>
                            @endif
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
        const base = @js(url('marketer/casting/generations'));
        const form = document.getElementById('studio-form'), msg = form?.querySelector('[data-msg]');
        form?.addEventListener('submit', async (e) => {
            e.preventDefault(); msg.textContent = ''; form.querySelector('[data-go]').disabled = true;
            try { await casting.req(@js(route('marketer.casting.studio.store')), new FormData(form)); location.reload(); }
            catch (x) { msg.textContent = x.message; msg.className = 'text-sm text-panel-danger-fg'; form.querySelector('[data-go]').disabled = false; }
        });
        document.getElementById('gen-grid').addEventListener('click', async (e) => {
            const b = e.target.closest('[data-act]'); if (!b) return;
            const el = b.closest('[data-gen]'), err = el.querySelector('[data-error]'); err.textContent = ''; b.disabled = true;
            try { await casting.req(`${base}/${el.dataset.gen}/${b.dataset.act}`); location.reload(); } catch (x) { err.textContent = x.message; b.disabled = false; }
        });
        const pending = () => [...document.querySelectorAll('[data-gen]')].filter(e => ['queued', 'running'].includes(e.dataset.status));
        async function poll() {
            let reload = false;
            for (const el of pending()) { try { const d = await casting.get(`${base}/${el.dataset.gen}/status`); if (d.status !== el.dataset.status) reload = true; } catch (e) {} }
            if (reload) return location.reload();
            if (pending().length) setTimeout(poll, 4000);
        }
        if (pending().length) setTimeout(poll, 3000);
    })();
    </script>
</x-layouts.marketer>
