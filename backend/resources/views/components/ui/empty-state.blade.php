{{-- EmptyState (Figma EmptyState): kind nodata | noresults | error | forbidden; icon in a soft blob, title, message, one primary action (href + action, or slot). --}}
@props(['kind' => 'nodata', 'title' => null, 'message' => null, 'href' => null, 'action' => null])
@php
    [$icon, $tone] = ['nodata' => ['box', 'bg-panel-accent-soft text-panel-accent-text'], 'noresults' => ['search', 'bg-panel-info-bg text-panel-info-fg'],
        'error' => ['alert', 'bg-panel-danger-bg text-panel-danger-fg'], 'forbidden' => ['lock', 'bg-panel-warning-bg text-panel-warning-fg']][$kind] ?? ['box', 'bg-panel-accent-soft text-panel-accent-text'];
    $title ??= __('ui.empty.'.$kind.'.title');
    $message ??= __('ui.empty.'.$kind.'.message');
@endphp
<div {{ $attributes->merge(['class' => 'flex flex-col items-center px-4 py-10 text-center']) }}>
    <span class="grid size-24 place-items-center rounded-[42%_58%_55%_45%/48%_42%_58%_52%] {{ $tone }}" aria-hidden="true"><x-ui.icon :name="$icon" class="size-10" /></span>
    <h3 class="mt-5 text-panel-h3 font-bold text-panel-text">{{ $title }}</h3>
    @if ($message)<p class="mt-1 max-w-sm text-panel-body text-panel-text-muted">{{ $message }}</p>@endif
    {{ $slot }}
    @if ($href && $action)<x-ui.button :href="$href" class="mt-5">{{ $action }}</x-ui.button>@endif
</div>
