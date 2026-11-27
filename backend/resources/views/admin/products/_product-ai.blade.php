{{--
    Product edit → AI tools (PRODUCT_AI_FREELLMAPI_PLAN.md §7): Prices by image, Marketplace prices, Title ideas on the FreeLLMAPI gateway
    (Admin\ProductAiController). Included only when the gateway is configured. Driven by resources/js/lib/product-ai.js; opened by
    [data-product-ai-open] (data-tab image|market|titles, data-target-type product|variant, data-target-id). Built like x-ui.modal
    (data-modal / role=dialog / data-modal-close), so ui.js traps focus, closes on Esc and returns focus. Text from the web and the
    AI models is set by the script with textContent only, never as HTML.
--}}
@php
    $t = fn (string $k) => __('admin.product_ai.'.$k);
    $cfg = [
        'identify' => route('admin.products.ai.identify', $product),
        'prices' => route('admin.products.ai.prices', $product),
        'titles' => route('admin.products.ai.titles', $product),
        'apply' => route('admin.products.ai.titles.apply', $product),
        'targets' => app(\App\Services\ImagePicker\ImagePickerService::class)->targets($product),
        'configured' => app(\App\Services\Llm\FreeLlmClient::class)->configured(),
        'market' => config('product_ai.market'),
        'locales' => config('product_ai.locales', ['ar', 'en']),
        'upload_max_kb' => (int) config('product_ai.upload_max_kb', 5120),
        'edit_prefix' => __('admin.edit'),
        'steps_label' => __('ui.steps'),
        'i18n' => __('admin.product_ai'),
    ];
    $tabs = ['image' => 'ai-price-image', 'market' => 'ai-market-prices', 'titles' => 'ai-titles'];
@endphp
<div id="product-ai" data-modal data-product-ai data-config='@json($cfg)' aria-hidden="true"
     class="fixed inset-0 z-50 hidden items-stretch justify-center bg-black/50 md:items-center md:p-6">
    <div data-modal-backdrop class="absolute inset-0"></div>
    <div role="dialog" aria-modal="true" aria-labelledby="product-ai-title" tabindex="-1"
         class="relative flex h-full w-full max-w-6xl flex-col overflow-hidden bg-panel-surface shadow-xl md:h-[calc(100vh-3rem)] md:rounded-2xl">
        <div class="flex shrink-0 flex-wrap items-center gap-3 border-b border-panel-border px-4 py-3 md:px-6">
            <div class="min-w-0 flex-1">
                <h2 id="product-ai-title" class="text-lg font-bold text-panel-text">{{ $t('title') }}</h2>
                <p class="hidden text-xs text-panel-text-subtle md:block">{{ $t('subtitle') }}</p>
            </div>
            <label class="order-last flex w-full items-center gap-2 text-xs font-bold text-panel-text-muted md:order-none md:w-auto">
                <span>{{ $t('for') }}</span>
                <select data-pai-target class="form-control !h-9 w-full min-w-48 !py-1 text-sm md:!w-auto"></select>
            </label>
            <button type="button" data-modal-close class="inline-flex size-9 items-center justify-center rounded-full hover:bg-panel-surface-sunken focus:outline-none focus-visible:ring-2 focus-visible:ring-panel-accent-soft" aria-label="{{ $t('close') }}">
                <x-ui.icon name="x" class="size-[18px]" stroke="2" />
            </button>
        </div>

        <div role="tablist" aria-labelledby="product-ai-title" class="flex shrink-0 gap-1 overflow-x-auto border-b border-panel-border px-2 [scrollbar-width:none] md:px-4">
            @foreach ($tabs as $key => $icon)
                <button type="button" role="tab" id="pai-tab-{{ $key }}" data-pai-tab="{{ $key }}" aria-controls="pai-panel-{{ $key }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}" tabindex="{{ $loop->first ? 0 : -1 }}"
                        class="-mb-px inline-flex min-h-11 shrink-0 items-center gap-2 whitespace-nowrap border-b-2 px-4 text-sm font-semibold transition {{ $loop->first ? 'border-panel-accent text-panel-accent-text' : 'border-transparent text-panel-text-muted hover:text-panel-text' }}">
                    <x-ui.icon :name="$icon" class="size-5" />{{ $t('tab_'.$key) }}
                </button>
            @endforeach
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto p-4 md:p-6">
            {{-- A1: Prices by image --}}
            <div role="tabpanel" id="pai-panel-image" aria-labelledby="pai-tab-image" data-pai-panel="image" class="space-y-5">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-start">
                    <div class="space-y-3 rounded-xl border border-panel-border p-4 lg:w-80 lg:shrink-0">
                        <fieldset>
                            <legend class="form-label">{{ $t('source_label') }}</legend>
                            <label class="flex min-h-11 w-fit cursor-pointer items-center gap-3 text-sm text-panel-text" data-pai-primary-label>
                                <input type="radio" name="pai-source" value="primary" data-pai-source class="size-[22px] shrink-0 border-panel-border-strong" checked>
                                <span>{{ $t('source_primary') }}<span data-pai-no-primary hidden class="block text-xs text-panel-text-subtle">{{ $t('no_primary') }}</span></span>
                            </label>
                            <label class="flex min-h-11 w-fit cursor-pointer items-center gap-3 text-sm text-panel-text">
                                <input type="radio" name="pai-source" value="upload" data-pai-source class="size-[22px] shrink-0 border-panel-border-strong">
                                <span>{{ $t('source_upload') }}</span>
                            </label>
                        </fieldset>
                        <div data-pai-upload hidden class="space-y-1">
                            <input type="file" id="pai-file" data-pai-file accept="image/jpeg,image/png,image/webp" aria-label="{{ $t('source_upload') }}" aria-describedby="pai-file-hint pai-file-error"
                                   class="block w-full text-xs text-panel-text-muted file:me-3 file:rounded-lg file:border file:border-panel-border-strong file:bg-panel-surface file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-panel-text">
                            <p id="pai-file-hint" class="text-xs text-panel-text-subtle">{{ $t('upload_hint') }}</p>
                            <p id="pai-file-error" data-pai-file-error hidden class="text-xs font-semibold text-panel-danger-fg"></p>
                        </div>
                        <img data-pai-preview hidden alt="{{ $t('preview_alt') }}" class="size-40 rounded-lg border border-panel-border bg-panel-surface object-contain">
                        <button type="button" data-pai-run="identify" class="btn btn-primary">{{ $t('identify') }}</button>
                    </div>
                    <div class="min-w-0 flex-1 space-y-4">
                        <div aria-live="polite"><div data-pai-status></div></div>
                        <div data-pai-identified hidden class="rounded-xl border border-panel-border p-4">
                            <h3 class="mb-3 font-semibold text-panel-text">{{ $t('identified') }}</h3>
                            <dl class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-1.5 text-sm">
                                @foreach (['brand', 'name', 'model', 'size'] as $f)
                                    <dt class="text-panel-text-muted">{{ $t($f) }}</dt><dd data-pai-id="{{ $f }}" class="min-w-0 break-words font-semibold text-panel-text"></dd>
                                @endforeach
                            </dl>
                            <p data-pai-id-cached hidden class="mt-2 text-xs text-panel-text-subtle"></p>
                            <div class="mt-4 space-y-2">
                                <label for="pai-image-query" class="form-label">{{ $t('search_text') }}</label>
                                <input id="pai-image-query" data-pai-image-query type="text" dir="auto" maxlength="200" class="form-control" autocomplete="off" aria-describedby="pai-image-query-hint">
                                <p id="pai-image-query-hint" class="text-xs text-panel-text-subtle">{{ $t('search_text_hint') }}</p>
                                <div data-pai-chips-wrap hidden>
                                    <p class="mb-1 text-xs font-bold text-panel-text-muted">{{ $t('other_queries') }}</p>
                                    <div data-pai-chips class="flex flex-wrap gap-2"></div>
                                </div>
                                <button type="button" data-pai-run="image-prices" class="btn btn-primary" disabled>{{ $t('search_prices') }}</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div data-pai-results></div>
            </div>

            {{-- A2: Marketplace prices --}}
            <div role="tabpanel" id="pai-panel-market" aria-labelledby="pai-tab-market" data-pai-panel="market" hidden class="space-y-4">
                <div>
                    <form data-pai-market-form novalidate class="flex flex-wrap items-center gap-2">
                        <img data-pai-thumb hidden alt="" class="size-10 rounded-lg border border-panel-border bg-panel-surface object-contain">
                        <input data-pai-query type="text" dir="auto" maxlength="200" class="form-control min-w-56 flex-1" aria-label="{{ $t('search_text') }}" aria-describedby="pai-market-hint" autocomplete="off">
                        <button type="submit" data-pai-run="market" class="btn btn-primary">{{ $t('search_prices') }}</button>
                    </form>
                    <p id="pai-market-hint" class="mt-1.5 text-xs text-panel-text-subtle">{{ $t('market_hint') }}</p>
                </div>
                <div aria-live="polite"><div data-pai-status></div></div>
                <div data-pai-results></div>
            </div>

            {{-- A3: Title ideas --}}
            <div role="tabpanel" id="pai-panel-titles" aria-labelledby="pai-tab-titles" data-pai-panel="titles" hidden class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <label class="flex min-h-11 w-fit cursor-pointer items-center gap-3 text-sm text-panel-text" data-pai-competitors-label>
                        <input type="checkbox" data-pai-competitors class="size-[22px] shrink-0 rounded-md border-panel-border-strong">
                        <span>{{ $t('use_competitors') }}<span data-pai-competitors-none class="block text-xs text-panel-text-subtle">{{ $t('use_competitors_none') }}</span></span>
                    </label>
                    <button type="button" data-pai-run="titles" class="btn btn-primary">{{ $t('suggest') }}</button>
                </div>
                <div aria-live="polite"><div data-pai-status></div></div>
                <div data-pai-results class="grid gap-4 lg:grid-cols-2"></div>
            </div>
        </div>
    </div>

    {{-- Pieces the script clones (shared components, rendered once) --}}
    <template data-pai-tpl="danger"><x-ui.alert tone="danger"><span data-msg></span><span data-actions class="mt-2 flex flex-wrap items-center gap-3"></span></x-ui.alert></template>
    <template data-pai-tpl="warning"><x-ui.alert tone="warning"><span data-msg></span></x-ui.alert></template>
    <template data-pai-tpl="spinner"><x-ui.spinner :size="16" /></template>
    <template data-pai-tpl="skeleton"><x-ui.skeleton kind="tablerow" /></template>
    <template data-pai-tpl="other"><x-ui.badge tone="neutral" size="sm" title="{{ $t('other_site_hint') }}">{{ $t('other_site') }}</x-ui.badge></template>
    <template data-pai-tpl="in_stock"><x-ui.badge tone="success" size="sm">{{ $t('in_stock') }}</x-ui.badge></template>
    <template data-pai-tpl="out_of_stock"><x-ui.badge tone="danger" size="sm">{{ $t('out_of_stock') }}</x-ui.badge></template>
    <template data-pai-tpl="check"><x-ui.icon name="check" class="size-4" stroke="2.5" /></template>
    <template data-pai-tpl="external"><x-ui.icon name="external" class="size-3.5" /></template>
</div>
