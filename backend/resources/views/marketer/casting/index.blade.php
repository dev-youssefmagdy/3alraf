@php
    $t = fn ($k, $r = []) => __('casting.'.$k, $r);
    $me = auth('marketer')->user();
    $tone = ['generating' => 'info', 'ready' => 'success', 'completed' => 'accent', 'failed' => 'danger', 'draft' => 'neutral'];
@endphp
<x-layouts.marketer :title="$t('title')">
    <x-slot:header>
        <x-ui.page-header :title="$t('title')">
            <a class="btn btn-secondary btn-md" href="{{ route('marketer.casting.models.index') }}">{{ $t('models.title') }}</a>
            <a class="btn btn-secondary btn-md" href="{{ route('marketer.casting.studio') }}">{{ $t('studio.title') }}</a>
        </x-ui.page-header>
    </x-slot:header>
    <div class="space-y-4" data-casting-index>
        <x-ui.alert tone="warning">{{ $t('internal_notice') }}</x-ui.alert>
        <x-ui.alert :tone="$health['ok'] ? 'success' : 'danger'" data-engine-health>{{ $health['ok'] ? $t('engine.ok') : $t('engine.down') }} — {{ $health['message'] }}</x-ui.alert>

        @can('ai_casting.create')
            <x-ui.card :title="$t('sessions.new')">
                <form id="casting-start" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" novalidate>
                    <x-ui.select name="product_id" class="lg:col-span-2" :label="$t('sessions.product')" :options="$products->mapWithKeys(fn ($p) => [$p->id => $p->name.' — '.$p->sku])->all()" searchable :placeholder="$t('live.none')" />
                    @include('marketer.casting._live_select')
                    <x-ui.select name="count" :label="$t('sessions.count')" :options="array_combine(range(4, 6), range(4, 6))" :value="4" />
                    <x-ui.select name="provider" :label="$t('sessions.provider')" :options="array_combine($providers, $providers)" :value="$defaultProvider" />
                    <x-ui.textarea name="notes" class="sm:col-span-2 lg:col-span-4" :label="$t('sessions.notes')" rows="2" maxlength="1000" />
                    <div class="sm:col-span-2 lg:col-span-4"><button type="submit" class="btn btn-primary btn-md" data-start>{{ $t('sessions.start') }}</button> <span class="text-sm text-panel-danger-fg" data-err></span></div>
                </form>
            </x-ui.card>
        @endcan

        @if ($sessions->isEmpty())
            <x-ui.empty-state :title="$t('sessions.title')" :description="$t('sessions.empty')" />
        @else
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($sessions as $s)
                    <a href="{{ route('marketer.casting.show', $s->id) }}" class="block rounded-2xl border border-panel-border bg-panel-surface p-4 shadow-panel-xs transition hover:border-panel-accent">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0"><p class="truncate font-semibold text-panel-text">{{ $s->productLabel() }}</p><p class="text-xs text-panel-text-subtle">#{{ $s->id }} · {{ $s->created_at->diffForHumans() }}</p></div>
                            <x-ui.badge :tone="$tone[$s->status] ?? 'neutral'" dot>{{ $t('status.'.$s->status) }}</x-ui.badge>
                        </div>
                        <p class="mt-2 text-sm text-panel-text-muted">{{ $t('sessions.candidates', ['n' => $s->candidates_count]) }} · {{ $s->provider }}</p>
                    </a>
                @endforeach
            </div>
            {{ $sessions->links() }}
        @endif
    </div>
    @include('marketer.casting._js')
    <script>
    document.getElementById('casting-start')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const f = e.target, btn = f.querySelector('[data-start]'), err = f.querySelector('[data-err]');
        err.textContent = ''; btn.disabled = true;
        try { const j = await casting.req(@js(route('marketer.casting.sessions.store')), new FormData(f)); location.href = j.url; }
        catch (x) { err.textContent = x.message; btn.disabled = false; }
    });
    </script>
</x-layouts.marketer>
