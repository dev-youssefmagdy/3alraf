@props(['title' => config('app.name'), 'bodyClass' => 'bg-slate-50', 'siteName' => null, 'fullTitle' => null, 'lite' => false])
@php
    $jsStrings = [
        'confirm_delete' => __('admin.confirm_delete'),
        'fix_errors' => __('admin.fix_errors'),
        'error' => __('admin.error'),
        'pick_values' => __('admin.pick_values'),
        'nothing_new' => __('admin.nothing_new'),
        'datatable' => [
            'search' => __('admin.search').':',
            'processing' => __('admin.processing'),
            'emptyTable' => __('admin.no_data'),
            'zeroRecords' => __('admin.no_data'),
        ],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ ($isRtl ?? false) ? 'rtl' : 'ltr' }}">
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
    @if ($lite || isset($head))
    @vite(['resources/css/storefront.css', 'resources/js/storefront.js'])
    @else
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <script>window.__t = {{ Illuminate\Support\Js::from($jsStrings) }};</script>
</head>
<body class="{{ $bodyClass }} min-h-screen font-sans text-slate-800 antialiased">
    {{ $slot }}
</body>
</html>
