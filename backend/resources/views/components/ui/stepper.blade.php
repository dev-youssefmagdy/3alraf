{{-- Stepper (Figma Stepper): multi-step flows. steps = list of labels, current = 1-based index. --}}
@props(['steps' => [], 'current' => 1])
<ol {{ $attributes->merge(['class' => 'flex items-center gap-2']) }} aria-label="{{ __('ui.steps') }}">
    @foreach ($steps as $i => $label)
        @php($n = $i + 1)
        <li class="flex min-w-0 flex-1 items-center gap-2" @if ($n === $current) aria-current="step" @endif>
            <span class="grid size-8 shrink-0 place-items-center rounded-full text-sm font-bold {{ $n < $current ? 'bg-panel-success-solid text-white' : ($n === $current ? 'bg-panel-accent text-panel-on-accent' : 'border-2 border-panel-border-strong text-panel-text-subtle') }}">@if ($n < $current)<x-ui.icon name="check" class="size-4" stroke="2.5" />@else{{ $n }}@endif</span>
            <span class="hidden truncate text-sm font-semibold sm:block {{ $n === $current ? 'text-panel-text' : 'text-panel-text-subtle' }}">{{ $label }}</span>
            @if (! $loop->last)<span class="h-px flex-1 {{ $n < $current ? 'bg-panel-success-solid' : 'bg-panel-border' }}"></span>@endif
        </li>
    @endforeach
</ol>
