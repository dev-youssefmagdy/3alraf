{{-- Avatar (Figma Avatar): image or initials fallback. size sm 32 | md 40 | lg 56. --}}
@props(['name' => '', 'src' => null, 'size' => 'md'])
@php
    $dim = ['sm' => 'size-8 text-xs', 'md' => 'size-10 text-sm', 'lg' => 'size-14 text-lg'][$size] ?? 'size-10 text-sm';
    $initial = mb_strtoupper(mb_substr(trim((string) $name), 0, 1));
@endphp
<span {{ $attributes->merge(['class' => "inline-grid shrink-0 place-items-center overflow-hidden rounded-full bg-panel-accent-soft font-bold text-panel-accent-text $dim"]) }} @if ($name) title="{{ $name }}" @endif>
    @if ($src)<img src="{{ $src }}" alt="{{ $name }}" class="size-full object-cover" loading="lazy">@else<span aria-hidden="true">{{ $initial }}</span><span class="sr-only">{{ $name }}</span>@endif
</span>
