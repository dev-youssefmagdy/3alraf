{{-- TimelineItem (Figma TimelineItem): order history / activity. state done | current | upcoming. --}}
@props(['title', 'time' => null, 'state' => 'done'])
@php $dot = ['done' => 'bg-panel-success-solid text-white', 'current' => 'bg-panel-accent text-panel-on-accent ring-4 ring-panel-accent-soft', 'upcoming' => 'border-2 border-panel-border-strong bg-panel-surface text-transparent'][$state] ?? ''; @endphp
<li {{ $attributes->merge(['class' => 'relative flex gap-3 pb-5 last:pb-0 [&:not(:last-child)]:before:absolute [&:not(:last-child)]:before:start-[11px] [&:not(:last-child)]:before:top-6 [&:not(:last-child)]:before:bottom-0 [&:not(:last-child)]:before:w-px [&:not(:last-child)]:before:bg-panel-border']) }} @if ($state === 'current') aria-current="step" @endif>
    <span class="mt-0.5 grid size-6 shrink-0 place-items-center rounded-full {{ $dot }}"><x-ui.icon name="check" class="size-3.5" stroke="2.5" /></span>
    <div class="min-w-0"><p class="text-sm font-semibold {{ $state === 'upcoming' ? 'text-panel-text-subtle' : 'text-panel-text' }}">{{ $title }}</p>@if ($time)<p class="text-xs text-panel-text-subtle">{{ $time }}</p>@endif{{ $slot }}</div>
</li>
