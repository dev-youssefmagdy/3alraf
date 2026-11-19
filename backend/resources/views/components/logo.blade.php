@props(['width' => 67, 'height' => 56, 'alt' => null, 'sizes' => null])
{{-- Store logo: hashed AVIF from the Vite build (long-lived cache under /build). `width`/`height` are the displayed size; the browser picks the file. --}}
<img src="{{ Vite::asset('resources/images/logo-206.avif') }}" srcset="{{ Vite::asset('resources/images/logo-135.avif') }} 135w, {{ Vite::asset('resources/images/logo-206.avif') }} 206w" sizes="{{ $sizes ?? $width.'px' }}" alt="{{ $alt ?? (setting('name') ?: config('app.name')) }}" width="{{ $width }}" height="{{ $height }}" decoding="async" {{ $attributes }}>
