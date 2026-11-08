@php
    $t = fn ($k, $r = []) => __('marketing.meta.'.$k, $r);
    $f = fn ($k) => __('marketing.meta.fields.'.$k);
    $editing = $d->exists;
    $aud = (array) $d->audience; $loc = (array) $d->locations; $pl = (array) $d->placements; $cr = (array) $d->creative; $cp = (array) $d->copy;
    $auto = $pl['automatic'] ?? true;
    $kpis = collect((array) $d->expected_kpis)->map(fn ($v, $k) => [$k, $v])->values()->all();
    $objectives = collect(array_keys(config('meta.objectives')))->mapWithKeys(fn ($o) => [$o => $t('objectives.'.$o)])->all();
    $goals = array_combine(config('meta.optimization_goals'), config('meta.optimization_goals'));
    $ctas = array_combine(config('meta.ctas'), config('meta.ctas'));
    $selected = array_map('intval', (array) $d->product_ids);
    $selectedAssets = array_map('intval', (array) ($cr['asset_ids'] ?? []));
    $dtv = fn ($v) => $v ? \Carbon\CarbonImmutable::parse($v)->format('Y-m-d\TH:i') : null;
@endphp
<x-layouts.marketer :title="$editing ? $t('btn.edit').': '.$d->name : $t('btn.create')">
    <form data-ajax-form @if ($editing) data-method="PUT" @endif data-validate-url="{{ route('marketer.meta-ads.validate') }}"
        action="{{ $editing ? route('marketer.meta-ads.update', $d->id) : route('marketer.meta-ads.store') }}" method="POST" class="space-y-4" novalidate data-draft-form>
        @csrf
        <a href="{{ $editing ? route('marketer.meta-ads.show', $d->id) : route('marketer.meta-ads.index') }}" class="text-sm text-brand-600 hover:underline">← {{ $t('btn.back') }}</a>
        <h1 class="text-xl font-bold text-slate-800">{{ $editing ? $t('btn.edit').': '.$d->name : $t('btn.create') }}</h1>
        <p class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800" role="note">{{ $t('safety_notice') }}</p>

        <x-card :title="$t('sections.basics')">
            <div class="grid gap-3 sm:grid-cols-2">
                <x-input name="name" :label="$f('name')" :value="$d->name" required maxlength="200" class="sm:col-span-2" />
                <x-select name="objective" :label="$f('objective')" :options="$objectives" :value="$d->objective" required />
                <x-select name="optimization_goal" :label="$f('optimization_goal')" :options="$goals" :value="$d->optimization_goal" placeholder="—" />
            </div>
        </x-card>

        <x-card :title="$t('sections.products')">
            <div class="grid gap-3 sm:grid-cols-2">
                <div data-field class="sm:col-span-2">
                    <label for="f-products" class="form-label">{{ $f('products') }}<span class="text-red-500">*</span></label>
                    <select id="f-products" name="product_ids[]" multiple data-search class="form-control">
                        @foreach ($products as $p)<option value="{{ $p->id }}" @selected(in_array($p->id, $selected, true))>{{ $p->name }} — {{ $p->sku }}</option>@endforeach
                    </select>
                </div>
                <x-input name="landing_url" :label="$f('landing_url')" :value="$d->landing_url" dir="ltr" placeholder="/ar/product/…" class="sm:col-span-2" />
            </div>
        </x-card>

        <x-card :title="$t('sections.audience')">
            <div class="grid gap-3 sm:grid-cols-3">
                <x-textarea name="audience[description]" :label="$f('audience_description')" :value="$aud['description'] ?? ''" rows="2" class="sm:col-span-3" />
                <x-input name="audience[age_min]" type="number" min="13" max="65" :label="$f('age_min')" :value="$aud['age_min'] ?? 18" />
                <x-input name="audience[age_max]" type="number" min="13" max="65" :label="$f('age_max')" :value="$aud['age_max'] ?? 65" />
                <x-select name="audience[genders]" :label="$f('genders')" :options="['all' => $t('genders.all'), 'male' => $t('genders.male'), 'female' => $t('genders.female')]" :value="$aud['genders'] ?? 'all'" />
                <x-input name="audience[interests]" :label="$f('interests')" :value="implode(', ', (array) ($aud['interests'] ?? []))" class="sm:col-span-3" />
                <x-input name="locations[countries]" :label="$f('countries')" :value="implode(', ', (array) ($loc['countries'] ?? []))" dir="ltr" />
                <x-input name="locations[cities]" :label="$f('cities')" :value="implode(', ', (array) ($loc['cities'] ?? []))" class="sm:col-span-2" />
            </div>
        </x-card>

        <x-card :title="$t('sections.placements')">
            <div class="space-y-3">
                <x-checkbox name="placements[automatic]" :label="$f('automatic')" :checked="$auto" />
                @foreach ([['publisher_platforms', config('meta.publisher_platforms')], ['facebook_positions', config('meta.positions.facebook')], ['instagram_positions', config('meta.positions.instagram')]] as [$key, $opts])
                    <div data-field>
                        <span class="form-label">{{ $f($key) }}</span>
                        <div class="flex flex-wrap gap-x-4 gap-y-1" dir="ltr">@foreach ($opts as $o)<x-checkbox :name="'placements['.$key.'][]'" :value="$o" :bool="false" :label="$o" :checked="in_array($o, (array) ($pl[$key] ?? []), true)" />@endforeach</div>
                    </div>
                @endforeach
            </div>
        </x-card>

        <x-card :title="$t('sections.budget')">
            <div class="grid gap-3 sm:grid-cols-3">
                <x-select name="budget_type" :label="$f('budget_type')" :options="['daily' => $t('budget_types.daily'), 'lifetime' => $t('budget_types.lifetime')]" :value="$d->budget_type" required />
                <x-input name="budget_amount" type="number" step="0.01" min="1" :label="$f('budget_amount')" :value="$d->budget_amount" required />
                <x-input name="currency" :label="$f('currency')" :value="$d->currency" maxlength="3" dir="ltr" />
                <x-input name="start_at" type="datetime-local" :label="$f('start_at')" :value="$dtv($d->start_at)" required />
                <x-input name="end_at" type="datetime-local" :label="$f('end_at')" :value="$dtv($d->end_at)" />
            </div>
        </x-card>

        <x-card :title="$t('sections.creative')">
            <div class="grid gap-3 sm:grid-cols-2">
                <div data-field class="sm:col-span-2">
                    <label for="f-assets" class="form-label">{{ $f('assets') }}</label>
                    <select id="f-assets" name="creative[asset_ids][]" multiple data-search class="form-control">
                        @foreach ($assets as $a)<option value="{{ $a->id }}" @selected(in_array($a->id, $selectedAssets, true))>#{{ $a->id }} {{ $a->title }} — {{ $a->product?->name }} ({{ $a->status->label() }})</option>@endforeach
                    </select>
                </div>
                <x-input name="creative[canva_url]" :label="$f('canva_url')" :value="$cr['canva_url'] ?? ''" dir="ltr" placeholder="https://www.canva.com/design/…" />
                <x-input name="creative[notes]" :label="$f('creative_notes')" :value="$cr['notes'] ?? ''" />
            </div>
        </x-card>

        <x-card :title="$t('sections.copy')">
            <div class="grid gap-3 sm:grid-cols-2">
                <x-textarea name="copy[primary_text]" :label="$f('primary_text')" :value="$cp['primary_text'] ?? ''" rows="4" required class="sm:col-span-2" />
                <x-input name="copy[headline]" :label="$f('headline')" :value="$cp['headline'] ?? ''" maxlength="255" />
                <x-input name="copy[description]" :label="$f('description')" :value="$cp['description'] ?? ''" maxlength="255" />
                <x-select name="cta" :label="$f('cta')" :options="$ctas" :value="$d->cta" required />
            </div>
        </x-card>

        <x-card :title="$t('sections.kpis')">
            <div class="grid gap-2 sm:grid-cols-2" data-kpis>
                @for ($i = 0; $i < 4; $i++)
                    <div class="flex gap-2">
                        <input type="text" name="kpi_key[]" value="{{ $kpis[$i][0] ?? '' }}" class="form-control min-w-0" dir="ltr" placeholder="{{ $f('kpi_key') }}" aria-label="{{ $f('kpi_key') }}">
                        <input type="text" name="kpi_value[]" value="{{ $kpis[$i][1] ?? '' }}" class="form-control min-w-0" placeholder="{{ $f('kpi_value') }}" aria-label="{{ $f('kpi_value') }}">
                    </div>
                @endfor
            </div>
        </x-card>

        <x-card :title="$t('sections.risks')">
            <div class="grid gap-3 sm:grid-cols-2">
                <x-textarea name="risks" :label="$f('risks')" :value="$d->risks" rows="3" />
                <x-textarea name="assumptions" :label="$f('assumptions')" :value="$d->assumptions" rows="3" />
            </div>
        </x-card>

        <div class="flex flex-wrap items-center gap-4">
            <x-button type="submit" data-action="save">{{ $t('btn.save') }}</x-button>
            <x-checkbox name="submit" :label="$t('btn.save_submit')" />
        </div>
    </form>
</x-layouts.marketer>
