@props(['heading', 'intro' => null])
{{-- Customer auth shell (login / register, design 28:9480 / 28:9520): brand-800 backdrop with blobs, white card on the start side, display tagline on the end side from 1024px.
     Use it inside <x-layouts.base :lite="true">, which links the storefront fonts in the head. --}}
<div class="relative isolate flex min-h-screen items-center overflow-hidden bg-brand-800 px-4 py-8 text-slate-950 lg:px-20">
    <span class="pointer-events-none absolute -start-32 -top-40 -z-10 size-[420px] rounded-full bg-pop-600 lg:-start-24 lg:size-[520px]" aria-hidden="true"></span>
    <span class="pointer-events-none absolute end-10 top-24 -z-10 size-20 rounded-full bg-violet-500 lg:end-[22%] lg:top-28 lg:size-36" aria-hidden="true"></span>
    <span class="pointer-events-none absolute -bottom-24 -end-24 -z-10 size-64 rounded-full bg-sun-400 lg:size-[360px]" aria-hidden="true"></span>
    <span class="pointer-events-none absolute bottom-10 start-[18%] -z-10 size-14 rounded-full bg-mint-400 lg:size-24" aria-hidden="true"></span>
    <div class="mx-auto flex w-full max-w-6xl items-center justify-center gap-16 lg:justify-between">
        <div class="w-full max-w-[420px] rounded-[32px] bg-white p-6 shadow-overlay sm:p-8">
            <div class="mb-5 text-center">
                <a href="{{ route('storefront.home') }}" class="inline-block rounded-xl" aria-label="{{ setting('name') ?? config('app.name') }}">
                    <img src="{{ asset('logo-sm.webp') }}" alt="{{ setting('name') ?? config('app.name') }}" width="67" height="56" class="mx-auto h-16 w-auto object-contain">
                </a>
                <h1 class="mt-3 font-display text-[28px]/9 font-extrabold text-slate-950">{{ $heading }}</h1>
                @if ($intro)<p class="mt-1 text-sm text-slate-600">{{ $intro }}</p>@endif
            </div>
            {{ $slot }}
        </div>
        <div class="hidden max-w-md text-white lg:block">
            {{-- brand mark: the real logo on a white tile, store name ("ع الرف") under it --}}
            <a href="{{ route('storefront.home') }}" class="inline-grid -rotate-2 rounded-[28px] bg-white p-4 shadow-overlay transition hover:rotate-0" aria-label="{{ setting('name') ?? config('app.name') }}">
                <img src="{{ asset('logo-sm.webp') }}" alt="" width="206" height="171" class="h-28 w-auto object-contain">
            </a>
            <p class="mt-6 font-display text-[40px]/[48px] font-extrabold">{{ setting('name') ?? config('app.name') }}</p>
            <p class="mt-2 text-base/[26px] text-white">{{ __('store.brand_tagline') }}</p>
        </div>
    </div>
</div>
