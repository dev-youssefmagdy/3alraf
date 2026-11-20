{{-- Spinner (Figma Spinner): inline only (buttons, table refresh). size 16|24|40. --}}
@props(['size' => 24])
<span {{ $attributes->merge(['class' => 'inline-block shrink-0 animate-panel-spin rounded-full border-2 border-panel-accent border-t-transparent']) }} style="width: {{ (int) $size }}px; height: {{ (int) $size }}px" role="status"><span class="sr-only">{{ __('ui.loading') }}</span></span>
