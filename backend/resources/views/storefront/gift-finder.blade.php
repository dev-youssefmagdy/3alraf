<x-layouts.storefront :title="$initial ? __('gift_finder.shared_title') : __('gift_finder.title')" :noindex="(bool) $initial">
    @php
        $g = fn ($k, $a = []) => __('gift_finder.'.$k, $a);
        $locale = app()->getLocale();
        $a = $initial['answers'] ?? [];
        $sym = setting('currency_symbol');
        $presets = (array) config('gift_finder.budget_presets');
        $sliderMax = (int) config('gift_finder.budget_slider_max', 10000);
        $budget = $a['budget'] ?? null;
        $cfg = ['symbol' => $sym, 'searchUrl' => route('storefront.gift-finder.search', ['locale' => $locale]), 'cartUrl' => route('storefront.cart.show', ['locale' => $locale]),
            'productUrl' => url($locale.'/product'), 'bundleUrl' => url($locale.'/bundle'), 'initial' => $initial,
            't' => collect(['results', 'none', 'near', 'loading', 'error', 'over', 'choose_options', 'unavailable_option', 'bundle', 'includes', 'add_bundle', 'share', 'copy_link', 'copied', 'added', 'view_cart'])
                ->mapWithKeys(fn ($k) => [$k => $g($k)])->all() + ['add' => $g('add_selected'), 'count' => $g('results_count', ['n' => ':n']), 'save' => $g('save', ['amount' => ':amount']), 'select' => $g('select_item', ['name' => ':name'])]];
        $field = 'block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30';
        $label = 'mb-1 block text-sm font-semibold text-slate-700';
    @endphp
    <div id="gf" class="mx-auto max-w-6xl" data-config="{{ json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP) }}">
        <header class="mb-5">
            <h1 class="text-2xl font-extrabold sm:text-3xl">{{ $initial ? $g('shared_title') : $g('title') }}</h1>
            <p class="mt-1 text-sm text-slate-600">{{ $initial ? $g('shared_hint') : $g('intro') }}</p>
        </header>

        <div class="grid gap-6 lg:grid-cols-[22rem_1fr]">
            <form id="gf-form" class="space-y-4 self-start rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 lg:sticky lg:top-40" novalidate aria-label="{{ $g('title') }}">
                @csrf
                <div>
                    <label for="gf-budget" class="{{ $label }}">{{ $g('fields.budget') }} <span class="font-normal text-slate-500">({{ $sym }})</span></label>
                    <input id="gf-budget" type="number" name="budget" min="1" max="1000000" step="any" inputmode="decimal" required value="{{ $budget }}" class="{{ $field }}" aria-describedby="gf-presets-label">
                    <input id="gf-range" type="range" min="50" max="{{ $sliderMax }}" step="50" value="{{ $budget ? min($sliderMax, (int) $budget) : min($sliderMax, $presets[1] ?? 500) }}" class="mt-3 w-full accent-brand-600" aria-label="{{ $g('fields.budget') }}" data-range>
                    <p id="gf-presets-label" class="mt-2 text-xs text-slate-500">{{ $g('budget_presets') }}</p>
                    <div class="mt-1 flex flex-wrap gap-2" role="group" aria-labelledby="gf-presets-label">
                        @foreach ($presets as $p)
                            <button type="button" data-preset="{{ $p }}" aria-pressed="{{ (float) $budget === (float) $p ? 'true' : 'false' }}"
                                class="rounded-full border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:border-brand-500 aria-pressed:border-brand-600 aria-pressed:bg-brand-600 aria-pressed:text-white">{{ number_format($p) }}</button>
                        @endforeach
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3 lg:grid-cols-1">
                    <div>
                        <label for="gf-occasion" class="{{ $label }}">{{ $g('fields.occasion') }}</label>
                        <select id="gf-occasion" name="occasion" class="{{ $field }}"><option value="">{{ $g('any') }}</option>@foreach ($occasions as $o)<option value="{{ $o }}" @selected(($a['occasion'] ?? null) === $o)>{{ $g('occasions.'.$o) }}</option>@endforeach</select>
                    </div>
                    <div>
                        <label for="gf-recipient" class="{{ $label }}">{{ $g('fields.recipient') }}</label>
                        <select id="gf-recipient" name="recipient" class="{{ $field }}"><option value="">{{ $g('any') }}</option>@foreach ($recipients as $o)<option value="{{ $o }}" @selected(($a['recipient'] ?? null) === $o)>{{ $g('recipients.'.$o) }}</option>@endforeach</select>
                    </div>
                </div>
                <fieldset>
                    <legend class="{{ $label }}">{{ $g('fields_type') }}</legend>
                    <div class="grid grid-cols-3 gap-1 rounded-xl bg-slate-100 p-1 text-sm">
                        @foreach (\App\Services\GiftFinder\GiftFinderService::TYPES as $t)
                            <label class="cursor-pointer">
                                <input type="radio" name="type" value="{{ $t }}" class="peer sr-only" @checked(($a['type'] ?? 'all') === $t)>
                                <span class="block rounded-lg px-2 py-1.5 text-center font-semibold text-slate-600 peer-checked:bg-white peer-checked:text-brand-700 peer-checked:shadow-sm peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500">{{ $g('type_'.$t) }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <div class="grid grid-cols-2 gap-3 lg:grid-cols-1">
                    <div>
                        <label for="gf-category" class="{{ $label }}">{{ $g('fields.category_id') }}</label>
                        <select id="gf-category" name="category_id" class="{{ $field }}"><option value="">{{ $g('any') }}</option>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected((int) ($a['category_id'] ?? 0) === $c->id)>{{ str_repeat('— ', (int) $c->depth) }}{{ $c->name }}</option>@endforeach</select>
                    </div>
                    <div>
                        <label for="gf-sort" class="{{ $label }}">{{ $g('fields_sort') }}</label>
                        <select id="gf-sort" name="sort" class="{{ $field }}">@foreach (\App\Services\GiftFinder\GiftFinderService::SORTS as $s)<option value="{{ $s }}" @selected(($a['sort'] ?? 'best') === $s)>{{ $g('sort_'.$s) }}</option>@endforeach</select>
                    </div>
                </div>
                <details class="group" @if (! empty($a['restrictions'])) open @endif>
                    <summary class="cursor-pointer text-sm font-semibold text-brand-700">{{ $g('more_options') }}</summary>
                    <label for="gf-restrictions" class="{{ $label }} mt-2">{{ $g('fields.restrictions') }}</label>
                    <input id="gf-restrictions" name="restrictions" maxlength="200" value="{{ $a['restrictions'] ?? '' }}" class="{{ $field }}">
                </details>
                <button type="submit" class="btn btn-primary w-full !py-3 text-base">{{ $g('find') }}</button>
            </form>

            <section aria-labelledby="gf-results-h" class="min-w-0">
                <h2 id="gf-results-h" class="sr-only">{{ $g('results') }}</h2>
                <p id="gf-status" class="sr-only" role="status" aria-live="polite"></p>
                <div id="gf-out" class="scroll-mt-40 space-y-4 focus:outline-none" tabindex="-1">
                    @unless ($initial)
                        <div class="grid place-items-center rounded-2xl border-2 border-dashed border-slate-200 bg-white/60 p-8 text-center text-slate-500">
                            <svg class="mb-3 size-12 text-brand-400" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 12v9H4v-9M2 7h20v5H2zM12 21V7M12 7H7.5a2.5 2.5 0 1 1 0-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 1 0 0-5C13 2 12 7 12 7z"/></svg>
                            <p class="text-sm">{{ $g('intro') }}</p>
                        </div>
                    @endunless
                </div>
            </section>
        </div>
    </div>
</x-layouts.storefront>
