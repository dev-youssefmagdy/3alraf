@props(['title' => config('app.name'), 'bodyClass' => 'bg-slate-50', 'siteName' => null, 'fullTitle' => null, 'lite' => false, 'panel' => null])
@php
    $jsStrings = [
        'confirm_delete' => __('admin.confirm_delete'),
        'fix_errors' => __('admin.fix_errors'),
        'error' => __('admin.error'),
        'pick_values' => __('admin.pick_values'),
        'nothing_new' => __('admin.nothing_new'),
        'vb_select_all' => __('admin.variant_builder.select_all'),
        'vb_clear' => __('admin.variant_builder.clear'),
        'vb_remove' => __('admin.variant_builder.remove'),
        'vb_no_values' => __('admin.variant_builder.no_values'),
        'vb_combinations' => __('admin.variant_builder.combinations'),
        'vb_new' => __('admin.variant_builder.new'),
        'vb_generated' => __('admin.variant_builder.generated'),
        'cmdk_empty' => __('admin.cmdk_empty'),
        'close' => __('ui.close'),
        'copied' => __('ui.copied'),
        'undo' => __('ui.undo'),
        'datatable' => [
            'search' => __('ui.datatable.search'),
            'processing' => __('admin.processing'),
            'emptyTable' => __('admin.no_data'),
            'zeroRecords' => __('admin.no_data'),
            'lengthMenu' => __('ui.datatable.lengthMenu'),
            'info' => __('ui.datatable.info'),
            'infoEmpty' => __('ui.datatable.infoEmpty'),
            'infoFiltered' => __('ui.datatable.infoFiltered'),
            'paginate' => ['first' => __('ui.datatable.first'), 'last' => __('ui.datatable.last'), 'next' => __('ui.datatable.next'), 'previous' => __('ui.datatable.previous')],
        ],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ ($isRtl ?? false) ? 'rtl' : 'ltr' }}" @if ($panel) data-panel="{{ $panel }}" @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $fullTitle ?: $title.' | '.($siteName ?: config('app.name')) }}</title>
    <link rel="icon" type="image/png" sizes="48x48" href="{{ asset('favicon-48.png') }}">
    @unless (isset($head))
    {{-- non-storefront pages (admin, login, register): never indexed --}}
    <meta name="robots" content="noindex,nofollow">
    <meta name="description" content="{{ $fullTitle ?: $title.' | '.($siteName ?: config('app.name')) }}">
    @endunless
    {{ $head ?? '' }}
    @if ($panel)
    {{-- panel theme (light default, dark from the top-bar toggle) is applied before first paint so there is no flash --}}
    <script>try{var t=localStorage.getItem('panel_theme');if(t==='dark')document.documentElement.setAttribute('data-theme','dark')}catch(e){}</script>
    @endif
    @if ($panel && ! $lite)
    {{-- panels on the admin bundle; storefront.css (storefront and lite pages) self-hosts these fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Baloo+Bhaijaan+2:wght@600;700;800&family=Cairo:wght@400;500;600;700;800&display=swap">
    @endif
    @if ($lite || isset($head))
    {{-- no modulepreload on the storefront: scripts must not compete with the stylesheet and the main image --}}
    @php(\Illuminate\Support\Facades\Vite::usePreloadTagAttributes(false))
    @vite(['resources/css/storefront.css', 'resources/js/storefront.js'])
    {{-- web fonts: after the page has loaded on a first visit (text shows in the fallback font until then), straight away once they are cached --}}
    <script>(function(){function f(){var l=document.createElement('link');l.rel='stylesheet';l.href=@js(\Illuminate\Support\Facades\Vite::asset('resources/css/fonts.css'));document.head.appendChild(l)}var k='sf_fonts',s;try{s=localStorage.getItem(k)}catch(e){}if(s)f();else addEventListener('load',function(){setTimeout(function(){f();try{localStorage.setItem(k,'1')}catch(e){}},300)},{once:true})})();</script>
    <noscript><link rel="stylesheet" href="{{ \Illuminate\Support\Facades\Vite::asset('resources/css/fonts.css') }}"></noscript>
    @else
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <script>window.__t = {{ Illuminate\Support\Js::from($jsStrings) }};</script>
</head>
<body class="{{ $bodyClass }} min-h-screen font-sans {{ $panel ? 'text-panel-text' : 'text-slate-800' }} antialiased">
    {{ $slot }}
</body>
</html>
