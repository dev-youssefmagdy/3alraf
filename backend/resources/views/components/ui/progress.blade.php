{{-- Progress (Figma Progress): kind bar | ring with the value always shown as text. Props: value (0-100), label, kind, tone accent|success|warning|danger.
     Legacy affiliate card (`title`, `rule`, `done`, `remaining`, `percent`, `tone` pop|panel + slot) keeps working: pass `title`. --}}
@props(['value' => 0, 'kind' => 'bar', 'label' => null, 'tone' => 'accent', 'title' => null, 'rule' => null, 'done' => null, 'remaining' => null, 'percent' => null, 'id' => null])
@if ($title !== null)
    @php
        [$track, $fill, $remCls] = $tone === 'pop' ? ['bg-pop-50', 'bg-pop-600', 'text-pop-600'] : ['bg-panel-accent-soft', 'bg-panel-accent', 'text-panel-accent-text'];
        $id ??= 'p'.substr(md5($title), 0, 6);
        $percent ??= $value;
    @endphp
    <section {{ $attributes->merge(['class' => 'rounded-2xl border border-panel-border bg-panel-surface p-5 shadow-panel-xs']) }} aria-labelledby="{{ $id }}-t">
        <h2 id="{{ $id }}-t" class="text-panel-h4 font-bold text-panel-text">{{ $title }}</h2>
        @if ($rule)<p class="mt-1 text-sm text-panel-text-muted">{{ $rule }}</p>@endif
        {{ $slot }}
        <div class="mt-3 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 text-xs font-bold">
            <span id="{{ $id }}-l" class="text-panel-text">{{ $done }}</span>
            @if ($remaining)<span class="{{ $remCls }}">{{ $remaining }}</span>@endif
        </div>
        <div class="mt-2 h-3 w-full overflow-hidden rounded-full {{ $track }}" role="progressbar" aria-labelledby="{{ $id }}-l" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $percent }}">
            <div class="h-full rounded-full {{ $fill }}" style="width: {{ min(100, max(0, (float) $percent)) }}%"></div>
        </div>
    </section>
@else
    @php
        $v = min(100, max(0, (float) $value));
        $c = ['accent' => 'text-panel-accent', 'success' => 'text-panel-success-solid', 'warning' => 'text-panel-warning-solid', 'danger' => 'text-panel-danger-solid'][$tone] ?? 'text-panel-accent';
    @endphp
    @if ($kind === 'ring')
        <div {{ $attributes->merge(['class' => 'relative inline-grid size-16 place-items-center']) }} role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $v }}" @if ($label) aria-label="{{ $label }}" @endif>
            <svg viewBox="0 0 36 36" class="size-full -rotate-90 rtl:rotate-90 {{ $c }}" fill="none" aria-hidden="true"><circle cx="18" cy="18" r="15.9" stroke="var(--p-surface-sunken)" stroke-width="3.5"/><circle cx="18" cy="18" r="15.9" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-dasharray="{{ $v }} 100" pathLength="100"/></svg>
            <span class="absolute text-sm font-bold tabular-nums text-panel-text" dir="ltr">{{ round($v) }}%</span>
        </div>
    @else
        <div {{ $attributes->merge(['class' => 'w-full']) }}>
            @if ($label)<div class="mb-1.5 flex items-baseline justify-between text-xs font-semibold"><span class="text-panel-text-muted">{{ $label }}</span><span class="tabular-nums text-panel-text" dir="ltr">{{ round($v) }}%</span></div>@endif
            <div class="h-2.5 w-full overflow-hidden rounded-full bg-panel-surface-sunken" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $v }}" @if ($label) aria-label="{{ $label }}" @endif><div class="h-full rounded-full bg-current {{ $c }}" style="width: {{ $v }}%"></div></div>
        </div>
    @endif
@endif
