{{-- Skeleton (Figma Skeleton): kind text|card|tablerow|stat. Mirrors the final layout so nothing jumps. --}}
@props(['kind' => 'text', 'lines' => 3])
@if ($kind === 'text')
    <div {{ $attributes->merge(['class' => 'space-y-2']) }} aria-hidden="true">@for ($i = 0; $i < $lines; $i++)<div class="skeleton h-3.5 {{ $i === $lines - 1 ? 'w-2/3' : 'w-full' }}"></div>@endfor</div>
@elseif ($kind === 'stat')
    <div {{ $attributes->merge(['class' => 'rounded-2xl border border-panel-border bg-panel-surface p-5']) }} aria-hidden="true"><div class="skeleton size-9 rounded-xl"></div><div class="skeleton mt-4 h-3 w-1/3"></div><div class="skeleton mt-3 h-8 w-1/2"></div></div>
@elseif ($kind === 'tablerow')
    <div {{ $attributes->merge(['class' => 'flex items-center gap-4 border-b border-panel-border px-4 py-3']) }} aria-hidden="true"><div class="skeleton size-10 rounded-full"></div><div class="skeleton h-3.5 flex-1"></div><div class="skeleton h-3.5 w-20"></div><div class="skeleton h-6 w-16 rounded-full"></div></div>
@else
    <div {{ $attributes->merge(['class' => 'rounded-2xl border border-panel-border bg-panel-surface p-5']) }} aria-hidden="true"><div class="skeleton h-4 w-1/3"></div><div class="skeleton mt-4 h-32 w-full"></div></div>
@endif
