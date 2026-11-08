{{-- One compare / payload value: null → —, html (sanitised again), colour swatch, scalar, list or JSON. --}}
@php $v = $value ?? null; @endphp
@if ($v === null || $v === '' || $v === [])
    <span class="text-slate-400">—</span>
@elseif (($html ?? false) && is_string($v))
    <div class="prose prose-sm max-w-none rounded border border-slate-100 bg-slate-50 p-2">{!! \App\Support\Marketing\RichText::render($v) !!}</div>
@elseif (($color ?? false) && is_string($v))
    <span class="inline-flex items-center gap-2"><span class="inline-block size-5 rounded border border-slate-300" style="background: {{ \App\Support\Marketing\Contrast::normalizeHex($v) ?? 'transparent' }}"></span><code dir="ltr">{{ $v }}</code></span>
@elseif (is_bool($v))
    {{ $v ? '✓' : '✗' }}
@elseif (is_scalar($v))
    <span class="whitespace-pre-line break-words">{{ $v }}</span>
@elseif (is_array($v) && array_is_list($v) && collect($v)->every(fn ($x) => is_scalar($x)))
    <span class="break-words">{{ implode('، ', $v) }}</span>
@else
    <pre class="max-h-60 overflow-auto whitespace-pre-wrap break-words rounded bg-slate-50 p-2 text-xs" dir="ltr">{{ json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
@endif
