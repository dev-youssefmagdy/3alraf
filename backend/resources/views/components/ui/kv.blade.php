{{-- KeyValue (Figma KeyValue): a detail row, label then value. kind text | money | status (value slot or text; money stays LTR). --}}
@props(['label', 'value' => null, 'kind' => 'text'])
<div {{ $attributes->merge(['class' => 'flex items-center justify-between gap-4 py-2.5 text-sm']) }}>
    <dt class="text-panel-text-muted">{{ $label }}</dt>
    <dd class="min-w-0 text-end font-semibold text-panel-text {{ $kind === 'money' ? 'tabular-nums' : '' }}">@if ($kind === 'money')<bdi dir="ltr">{{ $value }}</bdi>@else{{ $value }}{{ $slot }}@endif</dd>
</div>
