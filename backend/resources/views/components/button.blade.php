@props(['variant' => 'primary', 'type' => 'button', 'href' => null])
@php($cls = 'btn btn-'.$variant)
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $cls]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $cls]) }}>{{ $slot }}</button>
@endif
