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
            't' => collect(['results', 'none', 'near', 'loading', 'error', 'over', 'choose_options', 'unavailable_option', 'bundle', 'includes', 'add_bundle', 'share', 'copy_link', 'copied', 'added', 'view_cart', 'bundles', 'type_products', 'new_search', 'any'])
                ->mapWithKeys(fn ($k) => [$k => $g($k)])->all() + ['prev' => __('store.prev'), 'next' => __('store.next'), 'edit' => __('store.edit'), 'budget' => $g('fields.budget'), 'add' => $g('add_selected'), 'count' => $g('results_count', ['n' => ':n']), 'save' => $g('save', ['amount' => ':amount']), 'select' => $g('select_item', ['name' => ':name'])]];
        $label = 'mb-1.5 block text-sm font-semibold text-slate-950';
        $chip = 'chip peer-checked:border-brand-600 peer-checked:bg-brand-600 peer-checked:text-white peer-focus-visible:ring-[3px] peer-focus-visible:ring-pop-500/45';
    @endphp
    {{-- design 25:4691 / 25:4968 (results: 31:1480 / 31:1832). resources/js/lib/gift-finder.js renders the results (product and bundle rows on Swiper). --}}
    <div id="gf" data-config="{{ json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP) }}">
        <header class="relative mb-5 overflow-hidden rounded-3xl bg-violet-500 px-5 py-7 text-white lg:mb-8 lg:rounded-[32px] lg:px-10 lg:py-12">
            <span class="pointer-events-none absolute -bottom-20 -end-14 size-52 rounded-full bg-pop-600 lg:size-72" aria-hidden="true"></span>
            <span class="pointer-events-none absolute end-6 top-1/2 grid size-16 -translate-y-1/2 place-items-center rounded-full bg-sun-400 text-slate-950 shadow-raised max-sm:hidden lg:end-16 lg:size-28" aria-hidden="true"><svg class="size-8 lg:size-12" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M20 12v9H4v-9M2 7h20v5H2zM12 21V7M12 7H7.5a2.5 2.5 0 1 1 0-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 1 0 0-5C13 2 12 7 12 7z"/></svg></span>
            <span class="pointer-events-none absolute -top-6 end-1/3 size-14 rounded-full border-[6px] border-sun-400 max-sm:hidden" aria-hidden="true"></span>
            <h1 class="relative font-display text-[32px]/[40px] font-extrabold sm:pe-24 lg:text-[44px]/[52px]">{{ $initial ? $g('shared_title') : $g('title') }}</h1>
            <p class="relative mt-2 max-w-xl text-sm/[22px] text-white/90 sm:pe-24 lg:text-base/[26px]">{{ $initial ? $g('shared_hint') : $g('intro') }}</p>
        </header>

        <div class="grid gap-6 lg:grid-cols-[400px_minmax(0,1fr)] lg:gap-8 xl:grid-cols-[440px_minmax(0,1fr)]">
            <form id="gf-form" class="panel space-y-5 self-start scroll-mt-40 lg:sticky lg:top-40 lg:p-6" novalidate aria-label="{{ $g('title') }}">
                @csrf
                <div>
                    <label for="gf-budget" class="{{ $label }}">{{ $g('fields.budget') }} <span class="font-normal text-slate-500">({{ $sym }})</span></label>
                    <input id="gf-budget" type="number" name="budget" min="1" max="1000000" step="any" inputmode="decimal" required value="{{ $budget }}" class="form-control w-full" aria-describedby="gf-presets-label">
                    <input id="gf-range" type="range" min="50" max="{{ $sliderMax }}" step="50" value="{{ $budget ? min($sliderMax, (int) $budget) : min($sliderMax, $presets[1] ?? 500) }}" class="mt-4 h-2 w-full cursor-pointer accent-pop-600" aria-label="{{ $g('fields.budget') }}" data-range>
                    <div class="mt-1 flex justify-between text-[11px] text-slate-500" aria-hidden="true"><span>50</span><span>{{ number_format($sliderMax) }}</span></div>
                    <p id="gf-presets-label" class="mt-3 text-xs font-bold text-slate-600">{{ $g('budget_presets') }}</p>
                    <div class="mt-2 flex flex-wrap gap-2" role="group" aria-labelledby="gf-presets-label">
                        @foreach ($presets as $p)
                            <button type="button" data-preset="{{ $p }}" aria-pressed="{{ (float) $budget === (float) $p ? 'true' : 'false' }}" class="chip !min-h-10 !px-3.5">{{ number_format($p) }}</button>
                        @endforeach
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="gf-occasion" class="{{ $label }}">{{ $g('fields.occasion') }}</label>
                        <select id="gf-occasion" name="occasion" class="form-control w-full"><option value="">{{ $g('any') }}</option>@foreach ($occasions as $o)<option value="{{ $o }}" @selected(($a['occasion'] ?? null) === $o)>{{ $g('occasions.'.$o) }}</option>@endforeach</select>
                    </div>
                    <div>
                        <label for="gf-recipient" class="{{ $label }}">{{ $g('fields.recipient') }}</label>
                        <select id="gf-recipient" name="recipient" class="form-control w-full"><option value="">{{ $g('any') }}</option>@foreach ($recipients as $o)<option value="{{ $o }}" @selected(($a['recipient'] ?? null) === $o)>{{ $g('recipients.'.$o) }}</option>@endforeach</select>
                    </div>
                </div>
                <fieldset>
                    <legend class="{{ $label }}">{{ $g('fields_type') }}</legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach (\App\Services\GiftFinder\GiftFinderService::TYPES as $t)
                            <label class="cursor-pointer">
                                <input type="radio" name="type" value="{{ $t }}" class="peer sr-only" @checked(($a['type'] ?? 'all') === $t)>
                                <span class="{{ $chip }}">{{ $g('type_'.$t) }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="gf-category" class="{{ $label }}">{{ $g('fields.category_id') }}</label>
                        <select id="gf-category" name="category_id" class="form-control w-full"><option value="">{{ $g('any') }}</option>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected((int) ($a['category_id'] ?? 0) === $c->id)>{{ str_repeat('— ', (int) $c->depth) }}{{ $c->name }}</option>@endforeach</select>
                    </div>
                    <div>
                        <label for="gf-sort" class="{{ $label }}">{{ $g('fields_sort') }}</label>
                        <select id="gf-sort" name="sort" class="form-control w-full">@foreach (\App\Services\GiftFinder\GiftFinderService::SORTS as $s)<option value="{{ $s }}" @selected(($a['sort'] ?? 'best') === $s)>{{ $g('sort_'.$s) }}</option>@endforeach</select>
                    </div>
                </div>
                <details class="group rounded-2xl border border-slate-200 px-4" @if (! empty($a['restrictions'])) open @endif>
                    <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between text-sm font-bold text-slate-950 [&::-webkit-details-marker]:hidden">{{ $g('more_options') }}<span class="text-xl leading-none text-brand-600 group-open:hidden" aria-hidden="true">+</span><span class="hidden text-xl leading-none text-brand-600 group-open:inline" aria-hidden="true">&minus;</span></summary>
                    <div class="pb-4">
                        <label for="gf-restrictions" class="{{ $label }}">{{ $g('fields.restrictions') }}</label>
                        <input id="gf-restrictions" name="restrictions" maxlength="200" value="{{ $a['restrictions'] ?? '' }}" class="form-control w-full">
                    </div>
                </details>
                <button type="submit" class="btn btn-primary btn-lg w-full">
                    <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14zM20 20l-3.5-3.5"/></svg>{{ $g('find') }}</button>
            </form>

            <section aria-labelledby="gf-results-h" class="min-w-0">
                <h2 id="gf-results-h" class="section-title mb-4">{{ $g('results') }}</h2>
                <p id="gf-status" class="sr-only" role="status" aria-live="polite"></p>
                <div id="gf-out" class="scroll-mt-40 space-y-6 focus:outline-none" tabindex="-1">
                    @unless ($initial)
                        <div class="panel">
                            <x-empty-state type="gift" :title="$g('title')" :text="$g('intro')" />
                        </div>
                    @endunless
                </div>
            </section>
        </div>
    </div>
</x-layouts.storefront>
