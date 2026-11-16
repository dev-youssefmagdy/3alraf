{{-- Panel sign-in screens (Figma "Login + OTP"): form card on one side, brand side in the panel colour with what the panel is for.
     panel: admin | marketer (sets <html data-panel>). Texts: admin.auth_side.{panel}. --}}
@props(['panel', 'title'])
@php($side = __('admin.auth_side.'.$panel))
<x-layouts.base :title="$title" :panel="$panel" bodyClass="bg-slate-50">
    <div class="flex min-h-screen" data-auth>
        <div class="flex flex-1 items-center justify-center px-4 py-10">
            <div class="w-full max-w-md">
                <a href="{{ url('/') }}" class="mb-6 flex justify-center lg:hidden" aria-label="{{ setting('name') ?? config('app.name') }}">
                    <img src="{{ asset('logo-sm.webp') }}" alt="{{ setting('name') ?? config('app.name') }}" width="67" height="56" class="h-14 w-auto object-contain">
                </a>
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-[0_12px_32px_rgb(69_49_59/0.10)] sm:p-8">
                    {{ $slot }}
                </div>
                @if (is_array($side) && ! empty($side['note']))<p class="mt-4 text-center text-xs text-slate-500">{{ $side['note'] }}</p>@endif
            </div>
        </div>
        <aside class="hidden w-[420px] shrink-0 flex-col bg-panel-sidebar p-12 text-white lg:flex xl:w-[480px]">
            <img src="{{ asset('logo-sm.webp') }}" alt="{{ setting('name') ?? config('app.name') }}" class="h-14 w-auto self-start object-contain">
            @if (is_array($side))
                <div class="mt-auto">
                    <p class="text-2xl font-extrabold leading-snug">{{ $side['headline'] }}</p>
                    <ul class="mt-5 space-y-3 text-sm text-panel-sidebar-text">
                        @foreach ($side['points'] as $pt)<li class="flex items-start gap-2"><span class="mt-1.5 size-1.5 shrink-0 rounded-full bg-panel-stripe"></span>{{ $pt }}</li>@endforeach
                    </ul>
                </div>
            @endif
        </aside>
    </div>
</x-layouts.base>
