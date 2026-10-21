@props(['title' => null])
<div {{ $attributes->merge(['class' => 'rounded-xl border border-slate-200 bg-white shadow-sm']) }}>
    @if ($title || isset($actions))
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-3">
            <h2 class="text-base font-semibold text-slate-800">{{ $title }}</h2>
            @isset($actions)<div class="flex items-center gap-2">{{ $actions }}</div>@endisset
        </div>
    @endif
    <div class="p-5">{{ $slot }}</div>
</div>
