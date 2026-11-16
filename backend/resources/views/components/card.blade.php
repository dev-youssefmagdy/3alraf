{{-- Section card (Figma Panel/Card): title + optional subtitle + actions slot, body. :flush="true" removes body padding (tables). --}}
@props(['title' => null, 'subtitle' => null, 'flush' => false])
<div {{ $attributes->merge(['class' => 'rounded-2xl border border-slate-200 bg-white shadow-[0_1px_2px_rgb(69_49_59/0.06)]']) }}>
    @if ($title || isset($actions))
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
            <div class="min-w-0">
                <h2 class="text-base font-bold text-slate-900">{{ $title }}</h2>
                @if ($subtitle)<p class="mt-0.5 text-xs text-slate-500">{{ $subtitle }}</p>@endif
            </div>
            @isset($actions)<div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>@endisset
        </div>
    @endif
    <div class="{{ $flush ? '' : 'p-5' }}">{{ $slot }}</div>
</div>
