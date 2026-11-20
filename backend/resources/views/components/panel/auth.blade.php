{{-- Panel sign-in screens (Figma "Login + OTP"): desktop = brand side (640, panel colour, pitch) + form side; tablet / mobile = brand hero strip with the form card overlapping it.
     panel: admin | marketer | affiliate (sets <html data-panel>). heading + intro are the form's title. Texts: admin.auth_side.{admin|marketer}, ui.auth_side.affiliate.
     lite = storefront CSS bundle (the affiliate pages). Slot = the form. --}}
@props(['panel', 'title', 'heading' => null, 'intro' => null, 'lite' => false, 'panelLabel' => null, 'step' => null, 'description' => null, 'indexable' => false, 'altRoute' => null])
@php
    $side = __('admin.auth_side.'.$panel);
    if (! is_array($side)) { $side = __('ui.auth_side.'.$panel); }
    $label = $panelLabel ?? ['admin' => __('admin.admin_panel'), 'marketer' => __('marketing.panel'), 'affiliate' => __('store.affiliate_program')][$panel] ?? '';
@endphp
<x-layouts.base :title="$title" :panel="$panel" :lite="$lite" :site-name="$indexable ? setting('name') : null" bodyClass="bg-panel-bg">
    @if ($indexable)
        {{-- public, indexable auth-layout page (affiliate register): the same SEO head the storefront layout writes (description, robots, canonical, hreflang) --}}
        <x-slot:head>
            <meta name="description" content="{{ \Illuminate\Support\Str::limit(\App\Support\SafeHtml::text((string) $description), 158, '') }}">
            <meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1">
            <link rel="canonical" href="{{ url()->current() }}">
            @if ($altRoute)
                @foreach (\App\Models\Language::activeList()->filter(fn ($l) => in_array($l->code, (array) setting('locales', []), true)) as $l)<link rel="alternate" hreflang="{{ $l->code }}" href="{{ route($altRoute, ['locale' => $l->code]) }}">@endforeach
                <link rel="alternate" hreflang="x-default" href="{{ route($altRoute, ['locale' => setting('default_locale')]) }}">
            @endif
            <meta name="theme-color" content="#7c3aed">
        </x-slot:head>
    @endif
    <div class="flex min-h-screen flex-col lg:flex-row" data-auth>
        {{-- brand side: full column from 1024, hero strip below --}}
        <aside class="relative isolate flex shrink-0 flex-col overflow-hidden bg-gradient-to-br from-panel-sidebar-bg to-panel-accent-deep px-6 pb-24 pt-8 text-white lg:w-[640px] lg:justify-between lg:px-14 lg:py-14 lg:pb-14 xl:w-[640px]">
            <span class="pointer-events-none absolute -start-[60px] -top-[60px] -z-10 size-[260px] rounded-full bg-panel-accent/35 lg:-start-[120px] lg:bottom-[-120px] lg:top-auto lg:size-[420px]" aria-hidden="true"></span>
            <span class="pointer-events-none absolute -end-10 -top-10 -z-10 hidden size-60 rounded-full bg-white/10 lg:block" aria-hidden="true"></span>
            <a href="{{ url('/') }}" class="inline-flex items-center gap-3 self-start rounded-2xl bg-white px-3 py-1.5 shadow-panel-md" aria-label="{{ setting('name') ?? config('app.name') }}"><x-logo :width="48" :height="40" class="h-10 w-auto object-contain" /></a>
            <div class="mt-3 lg:hidden"><p class="text-panel-h3 font-bold">{{ $label }}</p></div>
            @if (is_array($side))
                <div class="hidden lg:block">
                    <p class="max-w-md text-panel-h1 font-bold leading-snug xl:text-[32px]/[44px]">{{ $side['headline'] }}</p>
                    <ul class="mt-6 space-y-3 text-base text-panel-sidebar-text">
                        @foreach ($side['points'] as $pt)<li class="flex items-center gap-3"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-white/15 text-panel-stripe"><x-ui.icon name="check" class="size-3.5" stroke="2.5" /></span>{{ $pt }}</li>@endforeach
                    </ul>
                </div>
            @endif
            <p class="hidden text-sm text-panel-sidebar-muted lg:block">&copy; {{ setting('name') ?? config('app.name') }} {{ date('Y') }}</p>
        </aside>
        {{-- form side --}}
        <main class="relative z-10 -mt-16 flex flex-1 items-start justify-center px-4 pb-10 lg:mt-0 lg:items-center lg:px-10 lg:py-14">
            <div class="w-full max-w-[520px] rounded-3xl border border-panel-border bg-panel-surface p-5 shadow-panel-lg sm:p-10 lg:max-w-[400px] lg:border-0 lg:bg-transparent lg:p-0 lg:shadow-none">
                @if ($heading)
                    <div class="mb-6">
                        @if ($step)<p class="mb-2 text-xs font-bold text-panel-accent-text">{{ $step }}</p>@endif
                        <h1 class="text-xl font-bold text-panel-text sm:text-panel-h1">{{ $heading }}</h1>
                        @if ($intro)<p class="mt-2 text-panel-body text-panel-text-muted">{{ $intro }}</p>@endif
                    </div>
                @endif
                {{ $slot }}
                @if (is_array($side) && ! empty($side['note']))<p class="mt-5 flex items-start gap-2 rounded-xl bg-panel-surface-sunken px-3 py-2.5 text-xs text-panel-text-muted"><x-ui.icon name="info" class="mt-0.5 size-4 shrink-0" />{{ $side['note'] }}</p>@endif
            </div>
        </main>
    </div>
    <x-ui.toast-region />
</x-layouts.base>
