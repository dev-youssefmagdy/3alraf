{{-- ListItem (Figma ListItem): 64px tappable row: lead slot (avatar / icon), title + subtitle, trail slot (badge, amount, chevron). href makes it a link. --}}
@props(['title', 'subtitle' => null, 'href' => null])
@php $cls = 'flex min-h-16 w-full items-center gap-3 border-b border-panel-border px-4 py-2.5 text-start last:border-b-0 '.($href ? 'transition hover:bg-panel-surface-sunken' : ''); @endphp
@if ($href)<a href="{{ $href }}" {{ $attributes->merge(['class' => $cls]) }}>@else<div {{ $attributes->merge(['class' => $cls]) }}>@endif
    @isset($lead)<div class="shrink-0">{{ $lead }}</div>@endisset
    <div class="min-w-0 flex-1">
        <p class="truncate text-sm font-semibold text-panel-text">{{ $title }}</p>
        @if ($subtitle)<p class="truncate text-xs text-panel-text-subtle">{{ $subtitle }}</p>@endif
    </div>
    @isset($trail)<div class="flex shrink-0 items-center gap-2">{{ $trail }}</div>@elseif ($href)<x-ui.icon name="chevron-end" class="size-4 text-panel-text-subtle" />@endisset
@if ($href)</a>@else</div>@endif
