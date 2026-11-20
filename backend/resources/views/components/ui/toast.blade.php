{{-- Toast (Figma Toast): markup for one toast (also used by the JS toast() in ui.js, which clones the <template data-toast-template> in the shell). tone success | danger | info | warning. --}}
@props(['tone' => 'info', 'message' => '', 'action' => null, 'actionHref' => null])
@php
    [$cls, $icon] = ['success' => ['border-panel-success-solid/40', 'check-circle'], 'danger' => ['border-panel-danger-solid/40', 'x-circle'], 'warning' => ['border-panel-warning-solid/40', 'alert'], 'info' => ['border-panel-info-solid/40', 'info']][$tone] ?? ['', 'info'];
    $ico = ['success' => 'text-panel-success-solid', 'danger' => 'text-panel-danger-solid', 'warning' => 'text-panel-warning-solid', 'info' => 'text-panel-info-solid'][$tone] ?? '';
@endphp
<div data-toast role="{{ $tone === 'danger' ? 'alert' : 'status' }}" {{ $attributes->merge(['class' => "pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-2xl border bg-panel-surface-raised p-3.5 ps-4 text-sm text-panel-text shadow-panel-lg animate-[toast-in_var(--duration-base)_var(--ease-panel)] $cls"]) }}>
    <x-ui.icon :name="$icon" class="mt-0.5 size-5 {{ $ico }}" />
    <p class="min-w-0 flex-1 py-px" data-toast-message>{{ $message }}</p>
    @if ($action)<a href="{{ $actionHref ?? '#' }}" data-toast-action class="shrink-0 font-bold text-panel-accent-text underline-offset-2 hover:underline">{{ $action }}</a>@endif
    <button type="button" data-toast-close class="-m-1.5 grid size-8 shrink-0 place-items-center rounded-lg text-panel-text-subtle hover:bg-panel-surface-sunken" aria-label="{{ __('ui.close') }}"><x-ui.icon name="x" class="size-4" /></button>
</div>
