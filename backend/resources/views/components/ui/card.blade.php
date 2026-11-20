{{-- Card (Figma Card): variant basic | hero (accent gradient, once per page) | media. title / subtitle + `actions` slot in the header, `footer` slot, flush removes
     body padding (tables, lists). --}}
@props(['title' => null, 'subtitle' => null, 'flush' => false, 'variant' => 'basic', 'padding' => true])
@php
    $hero = $variant === 'hero';
    $shell = $hero ? 'border-transparent bg-gradient-to-br from-panel-accent to-panel-accent-deep text-panel-on-accent shadow-panel-md' : 'border-panel-border bg-panel-surface text-panel-text shadow-panel-xs';
@endphp
<div {{ $attributes->merge(['class' => "min-w-0 rounded-2xl border $shell"]) }}>
    @php($hasActions = isset($actions) && trim((string) $actions) !== '')
    @php($hasFooter = isset($footer) && trim((string) $footer) !== '')
    @if ($title || $hasActions)
        <div class="flex flex-wrap items-center justify-between gap-3 {{ $hero ? '' : 'border-b border-panel-border' }} px-5 py-4">
            <div class="min-w-0">
                <h2 class="text-panel-h4 font-bold {{ $hero ? '' : 'text-panel-text' }}">{{ $title }}</h2>
                @if ($subtitle)<p class="mt-0.5 text-xs {{ $hero ? 'opacity-85' : 'text-panel-text-subtle' }}">{{ $subtitle }}</p>@endif
            </div>
            @if ($hasActions)<div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>@endif
        </div>
    @endif
    <div class="{{ $flush || ! $padding ? '' : 'p-5' }}">{{ $slot }}</div>
    @if ($hasFooter)<div class="flex flex-wrap items-center justify-between gap-3 border-t {{ $hero ? 'border-white/20' : 'border-panel-border' }} px-5 py-3">{{ $footer }}</div>@endif
</div>
