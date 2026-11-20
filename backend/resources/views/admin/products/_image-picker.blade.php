{{--
    Product edit → "Find images" (tools/image-picker, config image_picker). Free web image search (Bing via ddgs) and import of the picture
    the admin chooses. Driven by resources/js/lib/image-picker.js; opened by [data-image-picker-open] (data-target-type product|variant,
    data-target-id). Titles / sources from the web are inserted as text by the script, never as HTML.
--}}
@php
    $t = fn (string $k) => __('admin.image_picker.'.$k);
    $cfg = [
        'search' => route('admin.products.image-picker.search', $product),
        'import' => route('admin.products.image-picker.import', $product),
        'targets' => app(\App\Services\ImagePicker\ImagePickerService::class)->targets($product),
        'i18n' => __('admin.image_picker'),
    ];
@endphp
<div id="image-picker" data-image-picker data-config='@json($cfg)' role="dialog" aria-modal="true" aria-labelledby="image-picker-title"
     class="fixed inset-0 z-50 hidden items-stretch justify-center bg-black/50 md:items-center md:p-6">
    <div class="flex h-full w-full max-w-6xl flex-col overflow-hidden bg-panel-surface shadow-xl md:h-[calc(100vh-3rem)] md:rounded-2xl">
        <div class="flex shrink-0 flex-wrap items-center gap-3 border-b border-panel-border px-4 py-3 md:px-6">
            <div class="min-w-0 flex-1">
                <h2 id="image-picker-title" class="text-lg font-bold text-panel-text">{{ $t('title') }}</h2>
                <p class="hidden text-xs text-panel-text-subtle md:block">{{ $t('subtitle') }}</p>
            </div>
            <label class="flex items-center gap-2 text-xs font-bold text-panel-text-muted">
                <span>{{ $t('for') }}</span>
                <select data-ip-target class="form-control !h-9 !w-auto min-w-48 !py-1 text-sm"></select>
            </label>
            <button type="button" data-ip-close class="inline-flex size-9 items-center justify-center rounded-full hover:bg-panel-surface-sunken focus:outline-none focus-visible:ring-2 focus-visible:ring-panel-accent-soft" aria-label="{{ $t('close') }}">
                <svg class="size-[18px]" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M13.5 4.5L4.5 13.5M4.5 4.5L13.5 13.5"/></svg>
            </button>
        </div>

        <form data-ip-form novalidate class="flex shrink-0 flex-wrap items-center gap-2 border-b border-panel-border px-4 py-3 md:px-6">
            <img data-ip-thumb alt="" class="hidden size-10 rounded-lg border border-panel-border bg-panel-surface object-contain">
            <input data-ip-query type="text" maxlength="200" class="form-control min-w-56 flex-1" aria-label="{{ $t('search') }}" autocomplete="off">
            <label class="flex items-center gap-1.5 text-xs font-bold text-panel-text-muted">
                <input type="checkbox" data-ip-white class="size-4 rounded border-panel-border-strong text-panel-accent-text"> {{ $t('white_bg') }}
            </label>
            <button type="submit" data-ip-submit class="btn btn-primary">{{ $t('search') }}</button>
            <a data-ip-google href="https://www.google.com/search?udm=2" target="_blank" rel="noopener noreferrer" class="btn btn-secondary inline-flex items-center gap-1.5">
                {{ $t('search_google') }}
                <svg class="size-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 3h4v4M13 3L7 9M11 9.5V13H3V5h3.5"/></svg>
            </a>
        </form>

        {{-- From a link: an image address copied from Google / any site. Page links of Google, Bing and Yandex image results are unwrapped. --}}
        <div class="shrink-0 border-b border-panel-border bg-panel-surface-sunken px-4 py-3 md:px-6">
            <div class="flex flex-wrap items-center gap-2">
                <label for="image-picker-link" class="text-xs font-bold text-panel-text-muted">{{ $t('from_link') }}</label>
                <input id="image-picker-link" data-ip-link type="url" inputmode="url" maxlength="2048" class="form-control min-w-56 flex-1"
                       placeholder="{{ $t('link_placeholder') }}" autocomplete="off" spellcheck="false">
            </div>
            <p data-ip-link-msg class="mt-1.5 text-xs text-panel-text-subtle">{{ $t('link_hint') }}</p>
            <div data-ip-link-card class="mt-2 hidden items-center gap-3 rounded-xl border border-panel-border bg-panel-surface p-2">
                <img data-img alt="" referrerpolicy="no-referrer" class="size-16 shrink-0 rounded-lg bg-panel-surface object-contain">
                <div class="min-w-0 flex-1 text-xs">
                    <p data-source class="truncate font-bold text-panel-text"></p>
                    <p data-size class="tabular-nums text-panel-text-subtle"></p>
                    <a data-page target="_blank" rel="noopener noreferrer nofollow" referrerpolicy="no-referrer" class="text-panel-accent-text underline">{{ $t('open_page') }}</a>
                </div>
                <div class="flex flex-wrap gap-1.5">
                    <button type="button" data-act="main" class="btn-sm btn-primary"></button>
                    <button type="button" data-act="gallery" class="btn-sm btn-secondary">{{ $t('add_gallery') }}</button>
                </div>
            </div>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto p-4 md:p-6" aria-live="polite">
            <p class="mb-3 rounded-lg border border-panel-warning-solid bg-panel-warning-bg p-3 text-xs text-panel-warning-fg">{{ $t('license_note') }}</p>
            <p data-ip-message class="py-10 text-center text-sm text-panel-text-subtle">{{ $t('empty') }}</p>
            <p data-ip-errors class="mb-3 hidden text-xs text-panel-warning-fg"></p>
            <ul data-ip-grid class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5"></ul>
            <p class="mt-4 text-center"><button type="button" data-ip-more class="btn btn-secondary hidden">{{ $t('more') }}</button></p>
        </div>
    </div>

    <template data-ip-card>
        <li class="flex flex-col overflow-hidden rounded-xl border border-panel-border bg-panel-surface">
            <div class="aspect-square bg-panel-surface"><img data-img alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer" class="size-full object-contain p-1.5"></div>
            <div class="flex flex-1 flex-col gap-1.5 border-t border-panel-border p-2.5 text-xs">
                <p data-title class="line-clamp-2 font-bold text-panel-text"></p>
                <p class="truncate text-panel-text-subtle"><span data-size class="tabular-nums"></span> · <span data-source></span></p>
                <a data-page target="_blank" rel="noopener noreferrer nofollow" referrerpolicy="no-referrer" class="text-panel-accent-text underline">{{ $t('open_page') }}</a>
                <div class="mt-auto flex flex-wrap gap-1.5 pt-1">
                    <button type="button" data-act="main" class="btn-sm btn-primary"></button>
                    <button type="button" data-act="gallery" class="btn-sm btn-secondary">{{ $t('add_gallery') }}</button>
                </div>
            </div>
        </li>
    </template>
</div>
