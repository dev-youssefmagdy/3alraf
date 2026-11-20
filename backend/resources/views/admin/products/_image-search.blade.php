{{--
    Search by image modal (IMAGE_SEARCH_PLAN.md §9, Figma "Admin · Image search": 111-1421 empty, 111-2184 searching, 110-1249 results,
    111-3095 low confidence, 112-2797 no matches, 112-3284 service down, 112-3756 file error, 113-* mobile). Driven by resources/js/lib/image-search.js.
    States = data-state on [data-image-search]: empty | searching | results | none | down. The uploaded photo stays in the browser (object URL) and is
    sent once to admin.image-search; the server never stores it. Icons: inline SVG, stroke = currentColor (Figma Icon/* spec).
--}}
@php
    $t = fn (string $k, array $r = []) => __('admin.image_search.'.$k, $r);
    $cfg = ['search' => route('admin.image-search'), 'coverage' => route('admin.image-search.coverage'), 'maxBytes' => (int) config('image_search.max_upload_kb') * 1024,
        'mimes' => ['image/jpeg', 'image/png', 'image/webp', 'image/avif'], 'exts' => (array) config('image_search.mimes'), 'limit' => (int) config('image_search.results'),
        'i18n' => __('admin.image_search')];
    $chip = 'group inline-flex h-10 shrink-0 cursor-pointer items-center gap-2 rounded-full border px-4 text-sm font-bold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-panel-accent-soft '
        .'aria-checked:border-panel-accent aria-checked:bg-panel-accent aria-checked:text-white border-panel-border-strong bg-panel-surface text-panel-text hover:bg-panel-surface-sunken aria-checked:hover:bg-panel-accent-deep';
    $pill = 'inline-flex h-9 items-center justify-center gap-2 rounded-full border border-panel-border-strong bg-panel-surface px-4 text-xs font-bold text-panel-text hover:bg-panel-surface-sunken focus:outline-none focus-visible:ring-2 focus-visible:ring-panel-accent-soft rtl:flex-row-reverse';
    $check = '<svg class="hidden size-4 group-aria-checked:block" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13.3333 4L6 11.3333L2.66667 8"/></svg>';
    $camera16 = '<svg class="size-4 shrink-0" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9.66667 2.66667H6.33333L4.66667 4.66667H2.66667C2.31304 4.66667 1.97391 4.80714 1.72386 5.05719C1.47381 5.30724 1.33333 5.64638 1.33333 6V12C1.33333 12.3536 1.47381 12.6928 1.72386 12.9428C1.97391 13.1929 2.31304 13.3333 2.66667 13.3333H13.3333C13.687 13.3333 14.0261 13.1929 14.2761 12.9428C14.5262 12.6928 14.6667 12.3536 14.6667 12V6C14.6667 5.64638 14.5262 5.30724 14.2761 5.05719C14.0261 4.80714 13.687 4.66667 13.3333 4.66667H11.3333L9.66667 2.66667Z"/><path d="M10 8.66667C10 9.77124 9.10457 10.6667 8 10.6667C6.89543 10.6667 6 9.77124 6 8.66667C6 7.5621 6.89543 6.66667 8 6.66667C9.10457 6.66667 10 7.5621 10 8.66667Z"/></svg>';
    $warn = '<svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0zM12 9v4M12 17h.01"/></svg>';
@endphp
<div id="image-search" data-image-search data-state="empty" data-config='@json($cfg)' role="dialog" aria-modal="true" aria-labelledby="image-search-title"
     class="fixed inset-0 z-50 hidden items-stretch justify-center bg-black/50 md:items-center md:p-6">
    <div class="flex h-full w-full max-w-[1040px] flex-col overflow-hidden bg-panel-surface shadow-[0_16px_40px_rgba(47,33,41,0.14)] md:h-auto md:max-h-[calc(100vh-3rem)] md:rounded-2xl" data-is-dialog>
        {{-- Header --}}
        <div class="flex shrink-0 items-center justify-between gap-4 border-b border-panel-border px-4 py-3 md:px-6 md:py-4">
            <div class="min-w-0">
                <h2 id="image-search-title" class="text-lg/7 font-bold text-panel-text">{{ $t('title') }}</h2>
                <p class="hidden text-xs/[18px] text-panel-text-subtle md:block">{{ $t('subtitle') }}</p>
            </div>
            <button type="button" data-is-close class="inline-flex size-9 shrink-0 items-center justify-center rounded-full text-panel-text hover:bg-panel-surface-sunken focus:outline-none focus-visible:ring-2 focus-visible:ring-panel-accent-soft" aria-label="{{ $t('close') }}">
                <svg class="size-[18px]" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13.5 4.5L4.5 13.5M4.5 4.5L13.5 13.5"/></svg>
            </button>
        </div>

        {{-- Empty / ready (+ file error) --}}
        <div data-is-pane="empty" class="flex min-h-0 flex-col gap-4 overflow-y-auto p-4 md:p-6">
            <div class="flex flex-wrap items-center gap-3">
                <span class="text-xs/4 font-bold text-panel-text-muted">{{ $t('filter_label') }}</span>
                <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="{{ $t('filter_label') }}" data-is-filter>
                    @foreach (['all', 'products', 'bundles'] as $f)
                        <button type="button" role="radio" aria-checked="{{ $f === 'all' ? 'true' : 'false' }}" data-filter="{{ $f }}" class="{{ $chip }}">{!! $check !!}<span>{{ $t('filter_'.$f) }}</span><span data-count class="hidden tabular-nums opacity-80"></span></button>
                    @endforeach
                </div>
            </div>
            <div data-is-drop tabindex="0" role="button" aria-describedby="image-search-accepted"
                 class="group flex cursor-pointer flex-col items-center gap-2 rounded-2xl border-[1.5px] border-dashed border-panel-accent bg-panel-surface-sunken px-6 py-10 text-center outline-none transition hover:bg-panel-accent-soft focus-visible:shadow-[0_0_0_3px_rgba(201,111,145,0.4)] data-[error]:border-panel-danger-solid data-[error]:bg-panel-danger-bg">
                <span class="mb-1 inline-flex size-16 items-center justify-center rounded-full bg-panel-accent-soft text-panel-accent-text">
                    <svg class="size-7" viewBox="0 0 28 28" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16.9167 4.66667H11.0833L8.16667 8.16667H4.66667C4.04783 8.16667 3.45434 8.4125 3.01675 8.85008C2.57917 9.28767 2.33333 9.88116 2.33333 10.5V21C2.33333 21.6188 2.57917 22.2123 3.01675 22.6499C3.45434 23.0875 4.04783 23.3333 4.66667 23.3333H23.3333C23.9522 23.3333 24.5457 23.0875 24.9833 22.6499C25.4208 22.2123 25.6667 21.6188 25.6667 21V10.5C25.6667 9.88116 25.4208 9.28767 24.9833 8.85008C24.5457 8.4125 23.9522 8.16667 23.3333 8.16667H19.8333L16.9167 4.66667Z"/><path d="M17.5 15.1667C17.5 17.0997 15.933 18.6667 14 18.6667C12.067 18.6667 10.5 17.0997 10.5 15.1667C10.5 13.2337 12.067 11.6667 14 11.6667C15.933 11.6667 17.5 13.2337 17.5 15.1667Z"/></svg>
                </span>
                <p class="text-base/6 font-bold text-panel-text"><span class="hidden md:inline">{{ $t('drop_title') }}</span><span class="md:hidden">{{ $t('drop_title_mobile') }}</span></p>
                <p class="hidden items-center gap-1.5 text-sm/[22px] text-panel-text-muted md:flex">
                    <kbd class="rounded-md border border-panel-border bg-panel-surface px-1.5 py-px font-sans text-[11px]/[14px] font-bold text-panel-text-muted" dir="ltr">Ctrl + V</kbd>
                    <span>{{ $t('paste_hint') }}</span>
                </p>
                <p id="image-search-accepted" class="text-xs/[18px] text-panel-text-subtle">{{ $t('accepted') }}</p>
                <span class="{{ $pill }} mt-1" data-is-choose>
                    <svg class="size-4 shrink-0" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 10.6667V2.66667M11.3333 6L8 2.66667L4.66667 6M2.66667 13.3333H13.3333"/></svg>
                    <span>{{ $t('choose') }}</span>
                </span>
            </div>
            <p data-is-file-error role="alert" class="hidden items-center gap-1.5 text-xs/[18px] text-panel-danger-fg">{!! $warn !!}<span></span></p>
            <p class="flex items-center gap-1.5 text-xs/[18px] text-panel-text-subtle">
                <svg class="size-3.5 shrink-0" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12.25 7C12.25 9.89949 9.89949 12.25 7 12.25C4.1005 12.25 1.75 9.89949 1.75 7C1.75 4.1005 4.1005 1.75 7 1.75C9.89949 1.75 12.25 4.1005 12.25 7Z"/><path d="M7 6.41667V9.33333M7 4.66667H7.00583"/></svg>
                <span>{{ $t('tip') }}</span>
            </p>
        </div>

        {{-- Searching / results / none / down: query photo panel + results area --}}
        <div data-is-pane="query" class="hidden min-h-0 flex-1 flex-col md:flex-row">
            <aside class="flex shrink-0 items-center gap-3 border-b border-panel-border bg-panel-surface-sunken p-4 md:w-[272px] md:flex-col md:items-stretch md:gap-3 md:border-b-0 md:border-e md:p-6">
                <p class="hidden text-xs/4 font-bold text-panel-text-subtle md:block">{{ $t('your_photo') }}</p>
                <div class="size-14 shrink-0 overflow-hidden rounded-lg border border-panel-border bg-panel-surface md:size-56 md:rounded-xl">
                    <img data-is-preview alt="{{ $t('your_photo_alt') }}" class="size-full object-cover">
                </div>
                <div class="min-w-0 flex-1 text-xs/[18px] text-panel-text-subtle md:flex-none">
                    <p class="font-bold text-panel-text md:hidden">{{ $t('your_photo') }}</p>
                    <p data-is-file-name class="truncate" dir="auto"></p>
                    <p data-is-file-meta dir="auto"></p>
                </div>
                <button type="button" data-is-another class="{{ $pill }} md:w-full">
                    {!! $camera16 !!}<span class="hidden md:inline">{{ $t('another') }}</span><span class="md:hidden">{{ $t('another_short') }}</span>
                </button>
                <p class="mt-auto hidden items-center gap-1.5 text-xs/[18px] text-panel-text-subtle md:flex">
                    <svg class="size-3.5 shrink-0" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 1.75L2.33333 3.5V7C2.33333 9.91667 4.375 11.6667 7 12.25C9.625 11.6667 11.6667 9.91667 11.6667 7V3.5L7 1.75Z"/><path d="M5.25 7L6.41667 8.16667L8.75 5.83333"/></svg>
                    <span>{{ $t('privacy') }}</span>
                </p>
            </aside>

            <section class="flex min-h-0 min-w-0 flex-1 flex-col gap-4 overflow-y-auto p-4 md:min-h-[560px] md:p-6" aria-live="polite">
                {{-- results header (searching + results) --}}
                <div data-is-results-head class="flex flex-wrap items-center justify-between gap-3">
                    <p class="flex items-baseline gap-2"><span data-is-count class="text-base/6 font-bold text-panel-text"></span><span class="text-xs/[18px] text-panel-text-subtle">{{ $t('closest') }}</span></p>
                    <div data-is-filter-slot></div>
                </div>
                {{-- searching --}}
                <div data-is-block="searching" class="hidden flex-col gap-4">
                    <p class="flex items-center gap-2 text-sm text-panel-text-muted" role="status">
                        <svg class="size-4 animate-spin text-panel-accent-text" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".25" stroke-width="3"/><path d="M21 12a9 9 0 00-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
                        <b class="text-panel-text">{{ $t('searching') }}</b> <span>{{ $t('searching_hint') }}</span>
                    </p>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:gap-4 lg:grid-cols-4" aria-hidden="true">
                        @for ($i = 0; $i < 8; $i++)
                            <div class="overflow-hidden rounded-xl border border-panel-border bg-panel-surface"><div class="aspect-square animate-pulse bg-panel-surface-sunken"></div><div class="space-y-2 p-3"><div class="h-3 w-4/5 animate-pulse rounded bg-panel-surface-sunken"></div><div class="h-2.5 w-1/2 animate-pulse rounded bg-panel-surface-sunken"></div><div class="h-1.5 animate-pulse rounded-full bg-panel-surface-sunken"></div></div></div>
                        @endfor
                    </div>
                </div>
                {{-- results --}}
                <div data-is-block="results" class="hidden flex-col gap-4">
                    <div data-is-low class="hidden items-start gap-2 rounded-xl border border-panel-warning-solid bg-panel-warning-bg px-4 py-3 text-[#8a5a12]" role="note">
                        <span class="mt-0.5">{!! $warn !!}</span>
                        <div class="text-xs/[18px]"><p class="text-sm font-bold">{{ $t('low_title') }}</p><p>{{ $t('low_body') }}</p></div>
                    </div>
                    <div data-is-grid class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:gap-4 lg:grid-cols-4"></div>
                </div>
                {{-- no matches --}}
                <div data-is-block="none" class="m-auto hidden max-w-md flex-col items-center gap-2 py-10 text-center">
                    <span class="mb-1 inline-flex size-12 items-center justify-center rounded-full bg-panel-surface-sunken text-panel-text-subtle">
                        <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 21l-4.3-4.3M11 18a7 7 0 100-14 7 7 0 000 14zM8.5 8.5l5 5M13.5 8.5l-5 5"/></svg>
                    </span>
                    <p class="text-base/6 font-bold text-panel-text">{{ $t('none_title') }}</p>
                    <p class="text-sm text-panel-text-muted">{{ $t('none_body') }}</p>
                    <ul class="mt-2 list-inside list-disc space-y-1 text-start text-xs/[18px] text-panel-text-subtle">
                        <li>{{ $t('none_tip_clear') }}</li><li>{{ $t('none_tip_crop') }}</li><li>{{ $t('none_tip_name') }}</li>
                    </ul>
                </div>
                {{-- service down --}}
                <div data-is-block="down" class="m-auto hidden max-w-md flex-col items-center gap-2 py-10 text-center" role="alert">
                    <span class="mb-1 inline-flex size-12 items-center justify-center rounded-full bg-panel-warning-bg text-panel-warning-fg">
                        <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 2l20 20M5.8 8.4A5 5 0 007 18h10.3M21.4 15.7A4 4 0 0018 10h-1.3A8 8 0 009.4 4.3"/></svg>
                    </span>
                    <p class="text-base/6 font-bold text-panel-text">{{ $t('down_title') }}</p>
                    <p class="text-sm text-panel-text-muted">{{ $t('down_body') }}</p>
                    <div class="mt-3 flex flex-wrap justify-center gap-2">
                        <button type="button" data-is-retry class="btn btn-primary rounded-full">
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 11-3-6.7L21 8M21 3v5h-5"/></svg>
                            {{ $t('retry') }}
                        </button>
                        <button type="button" data-is-by-name class="btn btn-secondary rounded-full">
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 21l-4.3-4.3M11 18a7 7 0 100-14 7 7 0 000 14z"/></svg>
                            {{ $t('by_name') }}
                        </button>
                    </div>
                </div>
            </section>
        </div>

        {{-- Coverage strip (Figma 108-210) --}}
        <div data-is-coverage role="status" class="flex min-h-10 shrink-0 items-center gap-3 border-t border-panel-border bg-panel-surface-sunken px-4 py-2 text-xs/[18px] text-panel-text-muted md:h-10 md:px-6 md:py-0">
            <svg data-cov-icon="ok" class="size-[18px] shrink-0 text-panel-text-subtle" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3.75 2.25H14.25C15.0784 2.25 15.75 2.92157 15.75 3.75V14.25C15.75 15.0784 15.0784 15.75 14.25 15.75H3.75C2.92157 15.75 2.25 15.0784 2.25 14.25V3.75C2.25 2.92157 2.92157 2.25 3.75 2.25Z"/><path d="M8.25 6.75C8.25 7.57843 7.57843 8.25 6.75 8.25C5.92157 8.25 5.25 7.57843 5.25 6.75C5.25 5.92157 5.92157 5.25 6.75 5.25C7.57843 5.25 8.25 5.92157 8.25 6.75Z"/><path d="M15.75 11.25L13.425 8.925C13.1446 8.65016 12.7676 8.49621 12.375 8.49621C11.9824 8.49621 11.6054 8.65016 11.325 8.925L4.5 15.75"/></svg>
            <span data-cov-icon="warn" class="hidden text-panel-text-subtle">{!! $warn !!}</span>
            <span data-cov-text class="line-clamp-2 min-w-0 flex-1 md:truncate" dir="auto"></span>
            <span data-cov-bar class="hidden items-center gap-3">
                <span class="relative h-1.5 w-20 overflow-hidden rounded-full bg-panel-surface-sunken sm:w-[140px]" role="progressbar" aria-valuemin="0" aria-valuemax="100" data-cov-progress><span data-cov-fill class="absolute inset-y-0 start-0 rounded-full bg-panel-accent"></span></span>
                <b data-cov-percent class="tabular-nums text-panel-text-muted" dir="ltr"></b>
            </span>
            <span class="hidden items-center gap-1.5 text-panel-text-subtle [@media(hover:hover)]:md:flex">
                <kbd class="rounded-md border border-panel-border bg-panel-surface px-1.5 py-px font-sans text-[11px]/[14px] font-bold text-panel-text-muted">Esc</kbd>{{ $t('close_hint') }}
            </span>
        </div>
        <input type="file" data-is-input class="hidden" accept="image/jpeg,image/png,image/webp,image/avif,.jpg,.jpeg,.png,.webp,.avif">
    </div>
</div>

{{-- Page-wide drop overlay (Figma 109-544) --}}
<div data-is-dragover class="pointer-events-none fixed inset-0 z-[60] hidden items-center justify-center bg-black/50 p-6">
    <div class="flex w-full max-w-xl flex-col items-center gap-3 rounded-2xl border-2 border-dashed border-panel-accent bg-panel-surface/95 px-6 py-14 text-center shadow-[0_16px_40px_rgba(47,33,41,0.14)]">
        <span class="inline-flex size-16 items-center justify-center rounded-full bg-panel-accent-soft text-panel-accent-text">{!! str_replace('size-4', 'size-7', $camera16) !!}</span>
        <p class="text-lg/7 font-bold text-panel-text">{{ $t('drag_overlay') }}</p>
    </div>
</div>

{{-- Result card (Figma 108-143). Whole card is the link; thumbnail = fixed square box + object-cover (catalog photos are square: no crop). --}}
<template data-is-card>
    <a class="group flex flex-col overflow-hidden rounded-xl border border-panel-border bg-panel-surface shadow-[0_1px_3px_rgba(47,33,41,0.06)] transition hover:border-panel-accent hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-panel-accent-soft">
        <div class="relative aspect-square w-full overflow-hidden bg-panel-surface">
            <img data-c-img alt="" loading="lazy" class="absolute inset-0 size-full object-cover">
            <span data-c-badge class="absolute start-2 top-2 rounded-full px-2 py-0.5 text-xs/4 font-bold"></span>
            <span data-c-best class="absolute end-2 top-2 hidden rounded-full bg-panel-accent px-2 py-0.5 text-xs/4 font-bold text-white"></span>
        </div>
        <div class="flex flex-col gap-1.5 p-3">
            <p data-c-name class="line-clamp-2 h-11 text-sm/[22px] font-semibold text-panel-text" dir="auto"></p>
            <p data-c-sub class="truncate text-xs/[18px] text-panel-text-subtle" dir="auto"></p>
            <div class="flex flex-col gap-1">
                <p data-c-label class="text-xs/4 font-bold"></p>
                <div class="relative h-1.5 overflow-hidden rounded-full bg-panel-surface-sunken" role="meter" aria-valuemin="0" aria-valuemax="100" data-c-meter><span data-c-fill class="absolute inset-y-0 start-0 rounded-full"></span></div>
            </div>
            <span class="inline-flex items-center gap-1 self-start text-xs/4 font-bold text-panel-accent-text group-hover:underline rtl:flex-row-reverse">
                <svg class="size-3.5 shrink-0" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8.75 1.75H12.25V5.25"/><path d="M5.83333 8.16667L12.25 1.75"/><path d="M10.5 7.58333V11.0833C10.5 11.3928 10.3771 11.6895 10.1583 11.9083C9.9395 12.1271 9.64275 12.25 9.33333 12.25H2.91667C2.60725 12.25 2.3105 12.1271 2.09171 11.9083C1.87292 11.6895 1.75 11.3928 1.75 11.0833V4.66667C1.75 4.35725 1.87292 4.0605 2.09171 3.84171C2.3105 3.62292 2.60725 3.5 2.91667 3.5H6.41667"/></svg>
                <span>{{ $t('open') }}</span>
            </span>
        </div>
    </a>
</template>
