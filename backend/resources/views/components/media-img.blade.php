@props(['src', 'alt' => '', 'width' => null, 'height' => null, 'eager' => false])
@php($webp = is_string($src) && str_ends_with($src, '.avif') ? \App\Support\MediaPaths::withExtension($src, 'webp') : null)
{{-- AVIF for capable browsers; the sibling .webp (written on upload, kept by the migration) as the real fallback. The first/LCP image is eager + high priority, everything else lazy. --}}
@if ($webp)
<picture><source srcset="{{ $src }}" type="image/avif"><img src="{{ $webp }}" alt="{{ $alt }}" @if ($width) width="{{ $width }}" @endif @if ($height) height="{{ $height }}" @endif @if ($eager) fetchpriority="high" @else loading="lazy" @endif decoding="async" {{ $attributes }}></picture>
@else
<img src="{{ $src }}" alt="{{ $alt }}" @if ($width) width="{{ $width }}" @endif @if ($height) height="{{ $height }}" @endif @if ($eager) fetchpriority="high" @else loading="lazy" @endif decoding="async" {{ $attributes }}>
@endif
