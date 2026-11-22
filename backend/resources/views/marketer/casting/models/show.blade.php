@php
    $t = fn ($k, $r = []) => __('casting.'.$k, $r);
    $me = auth('marketer')->user();
    $canEdit = $me->can('ai_casting.edit');
    $post = fn ($route) => route('marketer.casting.models.'.$route, $m->id);
    $tone = ['approved' => 'success', 'draft' => 'neutral', 'archived' => 'warning', 'succeeded' => 'success', 'failed' => 'danger', 'queued' => 'info', 'running' => 'info'];
@endphp
<x-layouts.marketer :title="$m->name">
    <x-slot:header>
        <x-ui.page-header :title="$m->name" :breadcrumbs="[[$t('models.title'), route('marketer.casting.models.index')]]">
            @if ($m->isUsable())<a class="btn btn-primary btn-md" href="{{ route('marketer.casting.studio', ['model' => $m->id]) }}">{{ $t('models.use') }}</a>@endif
            @can('ai_casting.create')<form method="POST" action="{{ $post('duplicate') }}">@csrf<button class="btn btn-secondary btn-md">{{ $t('models.duplicate') }}</button></form>@endcan
            @if ($canEdit)<a class="btn btn-secondary btn-md" href="#edit">{{ $t('gallery.edit') }}</a>@endif
            @can('ai_casting.delete')
                @if ($m->status === 'archived')<form method="POST" action="{{ $post('restore') }}">@csrf<button class="btn btn-secondary btn-md">{{ $t('models.restore') }}</button></form>
                @else<form method="POST" action="{{ $post('archive') }}" onsubmit="return confirm(@js($t('models.confirm_archive')))">@csrf<button class="btn btn-danger btn-md">{{ $t('models.archive') }}</button></form>@endif
            @endcan
        </x-ui.page-header>
    </x-slot:header>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center gap-2">
            <x-ui.badge :tone="$tone[$m->status] ?? 'neutral'" dot>{{ $t('status.'.$m->status) }}</x-ui.badge>
            <x-ui.badge>{{ $t('models.identity') }}: {{ $t('models.identity_mode.'.$m->identity_mode) }}</x-ui.badge>
            @if ($m->status === 'draft')
                <span class="text-sm text-panel-text-muted">{{ $t('models.draft_hint') }}</span>
                @if ($canApprove)<form method="POST" action="{{ $post('approve') }}">@csrf<button class="btn btn-primary btn-sm">{{ $t('models.approve') }}</button></form>@endif
            @endif
        </div>
        @if ($m->identity_mode === 'prompt_seed')<x-ui.alert tone="info">{{ $t('engine.identity_prompt_seed') }}</x-ui.alert>@endif
        @if ($errors->any())<x-ui.alert tone="danger">{{ $errors->first() }}</x-ui.alert>@endif

        <div class="grid gap-4 lg:grid-cols-3">
            <x-ui.card :title="$t('models.references')" class="lg:col-span-2">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    @forelse ($m->references as $r)
                        <figure class="overflow-hidden rounded-xl border border-panel-border">
                            <img loading="lazy" class="aspect-[3/4] w-full object-cover" src="{{ route('marketer.casting.models.references.image', [$m->id, $r->id]) }}" alt="{{ $t('models.kinds.'.$r->kind) }}">
                            <figcaption class="flex items-center justify-between gap-1 p-2 text-xs"><span>{{ $t('models.kinds.'.$r->kind) }}{{ $r->is_primary ? ' ★' : '' }}</span>
                                @if ($canEdit)<form method="POST" action="{{ route('marketer.casting.models.references.destroy', [$m->id, $r->id]) }}" onsubmit="return confirm('?')">@csrf @method('DELETE')<button class="text-panel-danger-fg hover:underline">{{ $t('models.delete') }}</button></form>@endif</figcaption>
                        </figure>
                    @empty<p class="col-span-full text-sm text-panel-text-muted">{{ $t('models.none') }}</p>@endforelse
                </div>
                @if ($canEdit)
                    <form id="ref-upload" class="mt-4 flex flex-wrap items-end gap-2" enctype="multipart/form-data">
                        <x-ui.select name="kind" :label="$t('models.kind')" :options="collect(['face', 'full_body', 'reference', 'pose'])->mapWithKeys(fn ($k) => [$k => $t('models.kinds.'.$k)])->all()" />
                        <div><label class="form-label" for="f-file">{{ $t('models.upload') }}</label><input id="f-file" type="file" name="file" class="form-control" accept="image/jpeg,image/png,image/webp" required></div>
                        <button class="btn btn-secondary btn-md" type="submit">{{ $t('models.upload') }}</button>
                        <p class="w-full text-xs text-panel-text-subtle">{{ $t('models.upload_hint', ['mb' => round(config('casting.upload.max_kb') / 1024)]) }} <span class="text-panel-danger-fg" data-err></span></p>
                    </form>
                @endif
            </x-ui.card>
            <x-ui.card :title="$t('gallery.why')">
                <dl class="space-y-1 text-sm">
                    @foreach (['gender' => $m->gender ? $t('fields.'.$m->gender) : null, 'age_range' => $m->age_range, 'appearance' => $m->appearance, 'hairstyle' => $m->hairstyle, 'clothing_style' => $m->clothing_style, 'personality' => $m->personality, 'ad_role' => $m->ad_role] as $k => $v)
                        @if ($v)<div><dt class="inline font-semibold">{{ $t('fields.'.$k) }}:</dt> <dd class="inline">{{ $v }}</dd></div>@endif
                    @endforeach
                </dl>
            </x-ui.card>
        </div>

        @if ($canEdit)
            <x-ui.card :title="$t('gallery.edit')" id="edit">
                <form method="POST" action="{{ $post('update') }}" class="grid gap-3 sm:grid-cols-2">@csrf @method('PUT')
                    <x-ui.input name="name" :label="$t('fields.name')" :value="$m->name" required />
                    <x-ui.select name="gender" :label="$t('fields.gender')" :options="['female' => $t('fields.female'), 'male' => $t('fields.male')]" :value="$m->gender" />
                    <x-ui.input name="age_range" :label="$t('fields.age_range')" :value="$m->age_range" />
                    <x-ui.input name="hairstyle" :label="$t('fields.hairstyle')" :value="$m->hairstyle" />
                    <x-ui.textarea name="appearance" class="sm:col-span-2" :label="$t('fields.appearance')" :value="$m->appearance" rows="2" />
                    <x-ui.input name="clothing_style" :label="$t('fields.clothing_style')" :value="$m->clothing_style" />
                    <x-ui.input name="personality" :label="$t('fields.personality')" :value="$m->personality" />
                    <x-ui.input name="ad_role" :label="$t('fields.ad_role')" :value="$m->ad_role" />
                    <div class="sm:col-span-2"><button class="btn btn-primary btn-md">{{ $t('models.save') }}</button></div>
                </form>
            </x-ui.card>
        @endif

        <div class="grid gap-4 lg:grid-cols-2">
            <x-ui.card :title="$t('models.history')">
                <ul class="divide-y divide-panel-border text-sm">
                    @forelse ($m->generations as $g)
                        <li class="flex items-center justify-between gap-2 py-2"><span class="truncate">#{{ $g->id }} · {{ $g->kind }} · {{ $g->product?->name }}</span><x-ui.badge :tone="$tone[$g->status] ?? 'neutral'" size="sm">{{ $t('status.'.$g->status) }}</x-ui.badge></li>
                    @empty<li class="py-2 text-panel-text-muted">{{ $t('models.none') }}</li>@endforelse
                </ul>
            </x-ui.card>
            <x-ui.card :title="$t('models.usage')">
                <ul class="divide-y divide-panel-border text-sm">
                    @forelse ($m->usages as $u)
                        <li class="flex items-center justify-between gap-2 py-2"><span class="truncate">{{ $u->product?->name }}{{ $u->campaign ? ' · '.$u->campaign : '' }}</span><span class="text-xs text-panel-text-subtle">{{ $u->created_at->diffForHumans() }}</span></li>
                    @empty<li class="py-2 text-panel-text-muted">{{ $t('models.none') }}</li>@endforelse
                </ul>
            </x-ui.card>
        </div>
    </div>
    @include('marketer.casting._js')
    <script>
    document.getElementById('ref-upload')?.addEventListener('submit', async (e) => {
        e.preventDefault(); const f = e.target, err = f.querySelector('[data-err]'); err.textContent = '';
        try { await casting.req(@js($post('references.store')), new FormData(f)); location.reload(); } catch (x) { err.textContent = x.message; }
    });
    </script>
</x-layouts.marketer>
