@props(['src', 'alt' => '', 'width' => null, 'height' => null, 'eager' => false, 'sizes' => null])
@php($srcset = $sizes ? media_srcset($src) : null)
{{-- Images are stored as AVIF only (no WebP sibling). The first/LCP image is eager + high priority, everything else lazy. --}}
<img src="{{ $src }}" @if ($srcset) srcset="{{ $srcset }}" sizes="{{ $sizes }}" @endif alt="{{ $alt }}" @if ($width) width="{{ $width }}" @endif @if ($height) height="{{ $height }}" @endif @if ($eager) fetchpriority="high" @else loading="lazy" @endif decoding="async" {{ $attributes }}>
