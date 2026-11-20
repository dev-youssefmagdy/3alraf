{{-- Alert (Figma Alert): tone info|success|warning|danger, always icon + text. kind inline|banner; dismissible adds a close button. --}}
@props(['tone' => 'info', 'title' => null, 'dismissible' => false, 'kind' => 'inline'])
@php
    [$cls, $icon] = ['info' => ['bg-panel-info-bg text-panel-info-fg border-panel-info-solid/25', 'info'], 'success' => ['bg-panel-success-bg text-panel-success-fg border-panel-success-solid/25', 'check-circle'],
        'warning' => ['bg-panel-warning-bg text-panel-warning-fg border-panel-warning-solid/30', 'alert'], 'danger' => ['bg-panel-danger-bg text-panel-danger-fg border-panel-danger-solid/25', 'x-circle']][$tone] ?? ['', 'info'];
@endphp
<div role="{{ $tone === 'danger' ? 'alert' : 'status' }}" {{ $attributes->merge(['class' => "flex items-start gap-3 border px-4 py-3 text-sm $cls ".($kind === 'banner' ? 'rounded-none border-x-0' : 'rounded-xl')]) }} data-alert>
    <x-ui.icon :name="$icon" class="mt-0.5 size-5" />
    <div class="min-w-0 flex-1">@if ($title)<p class="font-bold">{{ $title }}</p>@endif<div class="{{ $title ? 'mt-0.5 opacity-90' : '' }}">{{ $slot }}</div></div>
    @if ($dismissible)<button type="button" data-alert-close class="-m-1 grid size-8 place-items-center rounded-lg hover:bg-black/5" aria-label="{{ __('ui.close') }}"><x-ui.icon name="x" class="size-4" /></button>@endif
</div>
