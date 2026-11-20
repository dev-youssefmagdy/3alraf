{{-- One compare / payload value: null → —, staged images, html (sanitised again), colour swatch, scalar, list or JSON. --}}
@php $v = $value ?? null; @endphp
@if ($v === null || $v === '' || $v === [])
    <span class="text-panel-text-subtle">—</span>
@elseif (($images ?? false) && is_array($v))
    {{-- staged new_product images: thumbnail (marketer-only route) + the source URL --}}
    <div class="grid grid-cols-3 gap-2 sm:grid-cols-4" data-images>
        @foreach ($v as $i => $img)
            <figure class="text-xs">
                @if ($img['src'] ?? null)
                    <a href="{{ $img['src'] }}" target="_blank" rel="noopener"><img src="{{ $img['src'] }}" alt="" class="aspect-square w-full rounded border border-panel-border object-cover" loading="lazy"></a>
                @else
                    <div class="flex aspect-square w-full items-center justify-center rounded border border-dashed border-panel-danger-solid text-panel-danger-fg">!</div>
                @endif
                <figcaption class="mt-1 truncate text-panel-text-muted" dir="ltr" title="{{ $img['url'] ?? '' }}">{{ $i + 1 }} · {{ $img['size'] ?? '' }}</figcaption>
            </figure>
        @endforeach
    </div>
@elseif (($html ?? false) && is_string($v))
    <div class="prose prose-sm max-w-none rounded border border-panel-border bg-panel-surface-sunken p-2">{!! \App\Support\Marketing\RichText::render($v) !!}</div>
@elseif (($color ?? false) && is_string($v))
    <span class="inline-flex items-center gap-2"><span class="inline-block size-5 rounded border border-panel-border-strong" style="background: {{ \App\Support\Marketing\Contrast::normalizeHex($v) ?? 'transparent' }}"></span><code dir="ltr">{{ $v }}</code></span>
@elseif (is_bool($v))
    {{ $v ? '✓' : '✗' }}
@elseif (is_scalar($v))
    <span class="whitespace-pre-line break-words">{{ $v }}</span>
@elseif (is_array($v) && array_is_list($v) && collect($v)->every(fn ($x) => is_scalar($x)))
    <span class="break-words">{{ implode('، ', $v) }}</span>
@else
    <pre class="max-h-60 overflow-auto whitespace-pre-wrap break-words rounded bg-panel-surface-sunken p-2 text-xs" dir="ltr">{{ json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
@endif
