<x-layouts.admin guard="tenant" :title="__('growth_hub.brand.title')">
    @php $b = fn ($k) => __('growth_hub.brand.'.$k); $inp = 'block w-full rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-sm'; @endphp
    <div class="space-y-4" id="bm">
        <p class="text-sm text-slate-600">{{ $b('hint') }}</p>
        @can('growth.manage')
            <x-card :title="$b('add')">
                <form data-ajax-form action="{{ route('tenant.growth.brand-memory.store') }}" method="POST" class="grid gap-3 sm:grid-cols-2" novalidate>
                    @csrf
                    <label class="text-sm text-slate-600">{{ $b('fields.kind') }}<select name="kind" class="{{ $inp }}">@foreach ($kinds as $k)<option value="{{ $k }}">{{ __('growth_hub.brand.kinds.'.$k) }}</option>@endforeach</select></label>
                    <x-input name="title" :label="$b('fields.title')" maxlength="160" />
                    <label class="text-sm text-slate-600 sm:col-span-2">{{ $b('fields.body') }}<textarea name="body" rows="3" maxlength="4000" class="{{ $inp }}"></textarea></label>
                    <div class="sm:col-span-2"><x-button type="submit">{{ __('admin.save') }}</x-button></div>
                </form>
            </x-card>
        @endcan
        @foreach ($kinds as $k)
            @if (isset($entries[$k]))
                <x-card :title="__('growth_hub.brand.kinds.'.$k)">
                    <ul class="divide-y divide-slate-100">
                        @foreach ($entries[$k] as $e)
                            <li class="py-2 text-sm" data-id="{{ $e->id }}">
                                <div class="flex flex-wrap items-center gap-2"><b>{{ $e->title }}</b><span class="badge {{ $e->is_active ? 'badge-success' : 'badge-muted' }}">v{{ $e->version }}</span>
                                    <button type="button" data-hist class="ms-auto text-xs text-brand-700 underline">{{ $b('versions') }}</button>
                                    @can('growth.manage')<button type="button" data-edit class="text-xs text-brand-700 underline">{{ __('admin.edit') }}</button><button type="button" data-del class="text-xs text-red-600 underline">{{ __('admin.delete') }}</button>@endcan</div>
                                <p class="mt-1 whitespace-pre-line text-slate-600" data-body>{{ $e->body }}</p>
                                <div class="hidden mt-1 rounded bg-slate-50 p-2 text-xs" data-hist-box></div>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif
        @endforeach

        <x-card :title="$b('claims_title')">
            <p class="mb-3 text-sm text-slate-600">{{ $b('claims_hint') }}</p>
            @can('growth.manage')
                <form data-ajax-form action="{{ route('tenant.growth.claims.store') }}" method="POST" class="mb-4 grid gap-3 sm:grid-cols-4" novalidate>
                    @csrf
                    <label class="text-sm text-slate-600">{{ $b('fields.product_id') }}<select name="product_id" class="{{ $inp }}">@foreach ($products as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></label>
                    <label class="text-sm text-slate-600">{{ $b('fields.type') }}<select name="type" class="{{ $inp }}">@foreach ($claimTypes as $t)<option value="{{ $t }}">{{ __('growth_hub.brand.claim_types.'.$t) }}</option>@endforeach</select></label>
                    <x-input name="claim" class="sm:col-span-2" :label="$b('fields.claim')" maxlength="300" />
                    <div><x-button type="submit">{{ __('admin.save') }}</x-button></div>
                </form>
            @endcan
            @foreach ($claims as $pid => $list)
                <div class="mb-2"><b class="text-sm">{{ $products[$pid] ?? '#'.$pid }}</b>
                    <ul class="ms-4 list-disc text-sm text-slate-600">@foreach ($list as $c)<li data-claim="{{ $c->id }}">[{{ __('growth_hub.brand.claim_types.'.$c->type) }}] {{ $c->claim }} @can('growth.manage')<button type="button" data-delclaim class="text-xs text-red-600 underline">{{ __('admin.delete') }}</button>@endcan</li>@endforeach</ul></div>
            @endforeach
        </x-card>
    </div>
    <script>
        (() => {
            const hdr = {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content};
            const call = async (u, m, b) => { const r = await fetch(u, {method: m, headers: hdr, body: b ? JSON.stringify(b) : undefined}); return [r, await r.json().catch(() => ({}))]; };
            const base = @json(url('/admin/growth/brand-memory'));
            const say = (j, ok) => { const m = j.message || Object.values(j.errors || {}).flat().join(' '); window.toastr ? (ok ? toastr.success : toastr.error)(m || '') : alert(m || ''); };
            document.querySelectorAll('#bm [data-del]').forEach(b => b.addEventListener('click', async () => { if (!confirm(@json($b('confirm_delete')))) return; const [r, j] = await call(base + '/' + b.closest('li').dataset.id, 'DELETE'); r.ok ? location.reload() : say(j, false); }));
            document.querySelectorAll('#bm [data-delclaim]').forEach(b => b.addEventListener('click', async () => { const [r, j] = await call(@json(url('/admin/growth/claims')) + '/' + b.closest('li').dataset.claim, 'DELETE'); r.ok ? location.reload() : say(j, false); }));
            document.querySelectorAll('#bm [data-edit]').forEach(b => b.addEventListener('click', async () => {
                const li = b.closest('li'), body = prompt(@json($b('fields.body')), li.querySelector('[data-body]').textContent.trim()); if (body === null) return;
                const [r, j] = await call(base + '/' + li.dataset.id, 'PUT', {title: li.querySelector('b').textContent, body}); r.ok ? location.reload() : say(j, false);
            }));
            document.querySelectorAll('#bm [data-hist]').forEach(b => b.addEventListener('click', async () => {
                const li = b.closest('li'), box = li.querySelector('[data-hist-box]'); const [r, j] = await call(base + '/' + li.dataset.id + '/versions', 'GET');
                box.textContent = (j.data?.versions || []).map(v => 'v' + v.version + ' (' + v.at + '): ' + v.body).join('\n') || '-'; box.classList.toggle('hidden'); box.style.whiteSpace = 'pre-line';
            }));
        })();
    </script>
</x-layouts.admin>
