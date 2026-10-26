<x-layouts.admin guard="tenant" :title="__('ai.gen.title')">
    @php
        $s = fn ($k, $a = []) => __('ai.gen.'.$k, $a);
        $inp = 'mt-1 block w-full rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-sm';
        $lbl = 'block text-sm text-slate-600';
    @endphp
    <div class="space-y-4" id="gen" data-store="{{ route('tenant.ai.generators.store') }}">
        <form id="gen-form" class="space-y-3 rounded-xl border border-slate-200 bg-white p-4" autocomplete="off">
            <h3 class="font-semibold text-slate-800">{{ $s('new') }}</h3>
            <p class="text-xs text-slate-500">{{ $s('hint') }}</p>
            <div class="grid gap-3 sm:grid-cols-3">
                <label class="{{ $lbl }}">{{ $s('fields.kind') }}
                    <select name="kind" id="gen-kind" class="{{ $inp }}">@foreach (['banner', 'flash_sale', 'discount_code', 'bundle'] as $k)<option value="{{ $k }}">{{ $s('kinds.'.$k) }}</option>@endforeach</select></label>
                <label class="{{ $lbl }}">{{ $s('fields.margin_floor') }}<input type="number" name="margin_floor" value="10" min="0" max="95" step="0.5" class="{{ $inp }}"><span class="text-xs text-slate-400">{{ $s('margin_hint') }}</span></label>
                <label class="{{ $lbl }}">{{ $s('fields.season') }} ({{ $s('optional') }})<input name="season" maxlength="80" class="{{ $inp }}"></label>
            </div>
            <label class="{{ $lbl }}">{{ $s('fields.goal') }}<input name="goal" maxlength="300" required class="{{ $inp }}"></label>
            <label class="{{ $lbl }}">{{ $s('fields.product_ids') }} ({{ $s('optional') }})
                <select name="product_ids[]" multiple size="4" class="{{ $inp }}">@foreach ($products as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></label>
            <div class="grid gap-3 sm:grid-cols-3">
                <label class="{{ $lbl }}">{{ $s('fields.starts_at') }} ({{ $s('optional') }})<input type="datetime-local" name="starts_at" class="{{ $inp }}"></label>
                <label class="{{ $lbl }}">{{ $s('fields.ends_at') }} ({{ $s('optional') }})<input type="datetime-local" name="ends_at" class="{{ $inp }}"></label>
                <label class="{{ $lbl }}" data-only="banner">{{ $s('fields.placement') }}<select name="placement" class="{{ $inp }}"><option value="">-</option>@foreach ($placements as $k => $n)<option value="{{ $k }}">{{ $n }}</option>@endforeach</select></label>
            </div>
            <label class="{{ $lbl }}">{{ $s('fields.brief') }} ({{ $s('optional') }})<textarea name="brief" rows="2" maxlength="1500" class="{{ $inp }}"></textarea></label>
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="{{ $lbl }}">{{ $s('fields.ai_model_id') }}<select data-search name="ai_model_id" class="{{ $inp }}"><option value=""></option>@foreach ($textModels as $m)<option value="{{ $m->id }}">{{ $m->label }}</option>@endforeach</select></label>
                <label class="{{ $lbl }}" data-only="banner">{{ $s('fields.image_model_id') }}<select data-search name="image_model_id" class="{{ $inp }}"><option value=""></option>@foreach ($imageModels as $m)<option value="{{ $m->id }}">{{ $m->label }}</option>@endforeach</select></label>
            </div>
            <p id="gen-err" class="hidden rounded-lg bg-red-50 p-2 text-sm text-red-700"></p>
            <button type="submit" id="gen-go" class="btn btn-primary">{{ $s('generate') }}</button>
        </form>

        <x-card :title="$s('queue')">
            <x-datatable :table="$table" :url="route('tenant.ai.generators.datatable')" id="ai-generators-table" />
        </x-card>
    </div>
    <script>
        (() => {
            const f = document.getElementById('gen-form'), kind = document.getElementById('gen-kind'), err = document.getElementById('gen-err'), go = document.getElementById('gen-go');
            const sync = () => f.querySelectorAll('[data-only]').forEach(e => e.classList.toggle('hidden', kind.value !== e.dataset.only));
            kind.addEventListener('change', sync); sync();
            f.addEventListener('submit', async e => {
                e.preventDefault(); err.classList.add('hidden'); go.disabled = true; go.textContent = @json($s('generating'));
                const fd = new FormData(f);
                const r = await fetch(document.getElementById('gen').dataset.store, {method: 'POST', headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content}, body: fd});
                const j = await r.json().catch(() => ({}));
                if (r.ok && j.url) { location.href = j.url; return; }
                err.textContent = j.message || Object.values(j.errors || {}).flat().join(' ') || 'Error'; err.classList.remove('hidden');
                go.disabled = false; go.textContent = @json($s('generate'));
            });
        })();
    </script>
</x-layouts.admin>
