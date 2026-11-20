{{-- Icon (Figma icon/*): <x-ui.icon name="cart" class="size-5" />. Registry: App\Support\Ui\Icons (a raw SVG path string also works). Directional icons mirror in RTL. --}}
@props(['name', 'stroke' => 1.8])
<svg {{ $attributes->merge(['class' => 'size-5 shrink-0'.(\App\Support\Ui\Icons::mirrors($name) ? ' rtl:-scale-x-100' : '')]) }} fill="none" stroke="currentColor" stroke-width="{{ $stroke }}" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true" focusable="false">{!! \App\Support\Ui\Icons::markup($name) !!}</svg>
