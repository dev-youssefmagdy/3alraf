{{-- Progress card (Figma Affiliate/Progress card): title, rule, done / remaining labels, bar filling from the start edge. tone: pop | panel --}}
@props(['title', 'rule' => null, 'done', 'remaining' => null, 'percent' => 0, 'tone' => 'panel', 'id' => null])
@php
    [$track, $fill, $remCls] = $tone === 'pop' ? ['bg-pop-50', 'bg-pop-600', 'text-pop-600'] : ['bg-panel-soft', 'bg-panel', 'text-panel-text'];
    $id ??= 'p'.substr(md5($title), 0, 6);
@endphp
<section {{ $attributes->merge(['class' => 'rounded-3xl border border-slate-200 bg-white p-5']) }} aria-labelledby="{{ $id }}-t">
    <h2 id="{{ $id }}-t" class="text-base font-bold text-slate-950">{{ $title }}</h2>
    @if ($rule)<p class="mt-1 text-sm text-slate-600">{{ $rule }}</p>@endif
    {{ $slot }}
    <div class="mt-3 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 text-xs font-bold">
        <span id="{{ $id }}-l" class="text-slate-950">{{ $done }}</span>
        @if ($remaining)<span class="{{ $remCls }}">{{ $remaining }}</span>@endif
    </div>
    <div class="mt-2 h-3 w-full overflow-hidden rounded-full {{ $track }}" role="progressbar" aria-labelledby="{{ $id }}-l" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $percent }}">
        <div class="h-full rounded-full {{ $fill }}" style="width: {{ min(100, max(0, (float) $percent)) }}%"></div>
    </div>
</section>
